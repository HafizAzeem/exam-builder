<?php

namespace Tests\Feature;

use App\Models\AssistantRequest;
use App\Models\Institution;
use App\Models\TeacherPermission;
use App\Models\User;
use App\Services\QuestionAssistant\AssistantBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssistantBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function owner(?Institution $school = null): User
    {
        $school ??= Institution::create(['name' => 'Assistant School', 'expiry_date' => now()->addYear(), 'access_expires_at' => now()->addYear()]);
        $user = User::factory()->create(['institution_id' => $school->id, 'is_active' => true]);
        $user->assignRole(Role::findOrCreate('institution_admin', 'web'));

        return $user;
    }

    private function budget(): AssistantBudgetService
    {
        return app(AssistantBudgetService::class);
    }

    private function reserve(User $user): AssistantRequest
    {
        return $this->budget()->reserve($user, (string) Str::uuid(), 'format');
    }

    public function test_retries_return_same_request_without_charging_and_other_actors_cannot_reuse_key(): void
    {
        $owner = $this->owner();
        $request = $this->reserve($owner);
        $retry = $this->budget()->reserve($owner, $request->request_key, 'format');
        $this->assertSame($request->id, $retry->id);
        $this->assertSame(1, $this->budget()->summary($owner->institution)['reserved']);
        $other = $this->owner($owner->institution);
        try {
            $this->budget()->reserve($other, $request->request_key, 'format');
            $this->fail('Another actor must not reuse a request key.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_school_running_guard_and_exactly_once_cancellation_prevent_late_publication(): void
    {
        $owner = $this->owner();
        $request = $this->reserve($owner);
        try {
            $this->reserve($this->owner($owner->institution));
            $this->fail('School running guard missing.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already', $e->getMessage());
        }
        $this->assertTrue($this->budget()->start($request));
        $this->assertFalse($this->budget()->start($request));
        $this->budget()->cancel($request);
        $this->budget()->cancel($request);
        $this->budget()->fail($request, 'late failure');
        $this->assertFalse($this->budget()->complete($request, fn () => $this->fail('Canceled request published.')));
        $totals = $this->budget()->summary($owner->institution);
        $this->assertSame(20, $totals['remaining']);
        $this->assertSame(1, $totals['refunded']);
        $this->assertSame(1, $totals['daily_used']);
        $this->assertSame('canceled', $request->fresh()->status);
    }

    public function test_completed_preview_consumes_limit_and_cannot_be_refunded(): void
    {
        $owner = $this->owner();
        $owner->institution->update(['assistant_request_limit' => 1]);
        $request = $this->reserve($owner);
        $this->assertTrue($this->budget()->start($request));
        $published = 0;
        $this->assertTrue($this->budget()->complete($request, function () use (&$published) {
            $published++;
        }));
        $this->assertFalse($this->budget()->complete($request, function () use (&$published) {
            $published++;
        }));
        $this->budget()->cancel($request);
        $this->assertSame(1, $published);
        $this->assertSame(0, $this->budget()->summary($owner->institution)['remaining']);
        $this->expectException(ValidationException::class);
        $this->reserve($owner);
    }

    public function test_expired_leases_and_revoked_teachers_fail_without_provider_start(): void
    {
        $owner = $this->owner();
        $expired = $this->reserve($owner);
        $expired->update(['expires_at' => now()->subSecond()]);
        $this->assertSame(1, $this->budget()->summary($owner->institution)['refunded']);
        $this->assertFalse($this->budget()->complete($expired, fn () => $this->fail('Expired worker published.')));
        $teacher = User::factory()->create(['institution_id' => $owner->institution_id, 'is_active' => true]);
        $teacher->assignRole(Role::findOrCreate('teacher', 'web'));
        $permission = TeacherPermission::create(['user_id' => $teacher->id, 'can_use_assistant' => true]);
        $request = $this->reserve($teacher);
        $permission->update(['can_use_assistant' => false]);
        $this->assertFalse($this->budget()->start($request));
        $this->assertNull($request->fresh()->provider_started_at);
        $this->assertSame('failed', $request->fresh()->status);
    }

    public function test_daily_limit_survives_refunds_and_budget_reset_and_reset_accounts_same_second_requests(): void
    {
        $this->travelTo(now()->startOfSecond());
        $owner = $this->owner();
        $owner->institution->update(['assistant_daily_limit' => 2]);
        $request = $this->reserve($owner);
        $this->assertTrue($this->budget()->start($request));
        $this->budget()->cancel($request);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $reset = ['action' => 'reset', 'request_id' => (string) Str::uuid(), 'confirmed' => true, 'note' => 'New allocation'];
        $url = '/super-admin/schools/'.$owner->institution_id.'/assistant-budget';
        $this->actingAs($admin)->post($url, $reset)->assertRedirect();
        $this->post($url, $reset)->assertRedirect();
        $this->assertDatabaseCount('assistant_budget_events', 1);
        $request = $this->reserve($owner);
        $this->assertSame(1, $this->budget()->summary($owner->institution)['reserved']);
        $this->assertTrue($this->budget()->start($request));
        $this->budget()->fail($request, 'No usable questions');
        $this->assertSame(2, $this->budget()->summary($owner->institution)['daily_used']);
        $this->expectException(ValidationException::class);
        $this->reserve($owner);
    }

    public function test_processing_deadline_fails_and_publication_exception_rolls_back(): void
    {
        $owner = $this->owner();
        $request = $this->reserve($owner);
        $this->budget()->start($request);
        try {
            $this->budget()->complete($request, function () use ($owner) {
                DB::table('institutions')->where('id', $owner->institution_id)->update(['name' => 'Uncommitted']);
                throw new \RuntimeException('Publication failed');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Publication failed', $e->getMessage());
        }
        $this->assertSame('Assistant School', $owner->institution->fresh()->name);
        $this->assertSame('processing', $request->fresh()->status);
        $this->travel(121)->seconds();
        $this->assertFalse($this->budget()->complete($request, fn () => $this->fail('Deadline ignored.')));
        $this->assertSame('failed', $request->fresh()->status);
    }

    public function test_submission_rate_guard_counts_refunded_submissions_and_clears_after_a_minute(): void
    {
        $owner = $this->owner();
        for ($i = 0; $i < 3; $i++) {
            $this->budget()->cancel($this->reserve($owner));
        }
        try {
            $this->reserve($owner);
            $this->fail('Repeated refunded submissions bypassed rate limit.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('minute', $e->getMessage());
        }
        $this->travel(61)->seconds();
        $this->assertSame('reserved', $this->reserve($owner)->status);
    }

    public function test_limit_override_keeps_usage_and_school_access_extension_does_not_reset_trial_budget(): void
    {
        $owner = $this->owner();
        $request = $this->reserve($owner);
        $this->budget()->start($request);
        $this->budget()->complete($request, fn () => null);
        $owner->institution->update(['access_expires_at' => now()->addYears(2)]);
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin)->post('/super-admin/schools/'.$owner->institution_id.'/assistant-budget', [
            'action' => 'override', 'request_id' => (string) Str::uuid(), 'confirmed' => true,
            'assistant_request_limit' => 0, 'assistant_daily_limit' => 5, 'note' => 'Pause allocation',
        ])->assertRedirect();
        $summary = $this->budget()->summary($owner->institution);
        $this->assertSame(1, $summary['used']);
        $this->assertSame(0, $summary['remaining']);
        $this->assertSame(5, $summary['daily_limit']);
        $this->assertDatabaseHas('assistant_budget_events', ['action' => 'override', 'note' => 'Pause allocation']);
        $this->assertSame('completed', $request->fresh()->status);
        $this->expectException(ValidationException::class);
        $this->reserve($owner);
    }

    public function test_non_platform_admin_cannot_override_budget(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/super-admin/schools/'.$owner->institution_id.'/assistant-budget', [
            'action' => 'override', 'request_id' => (string) Str::uuid(), 'confirmed' => true,
            'assistant_request_limit' => 999, 'assistant_daily_limit' => 999, 'note' => 'Denied',
        ])->assertForbidden();
        $this->assertDatabaseCount('assistant_budget_events', 0);
    }
}
