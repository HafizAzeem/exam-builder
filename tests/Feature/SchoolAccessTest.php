<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Institution;
use App\Models\SavedPaper;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_information_stays_accessible_after_account_deadlines_without_unlocking_app(): void
    {
        $owner = $this->owner(false);
        $owner->update(['verification_due_at' => now()->subHour()]);
        $owner->institution->update(['access_expires_at' => now()->subDay()]);
        $this->actingAs($owner)->get('/pricing')->assertOk();
        $this->get('/ur/for-schools-and-academies')->assertOk();
        $this->get('/builder')->assertRedirect('/verify-email');
    }

    public function test_expired_school_can_load_diagram_images_used_in_saved_papers(): void
    {
        Storage::fake('local');
        $owner = $this->owner();
        $owner->institution->update(['access_expires_at' => now()->subSecond()]);
        Storage::disk('local')->put('diagrams/saved.png', 'test-image');
        $diagram = Diagram::create(['user_id' => $owner->id, 'title' => 'Saved figure', 'category' => 'Science', 'status' => 'private', 'image_path' => 'diagrams/saved.png']);
        $this->actingAs($owner)->get(route('diagrams.image', $diagram))->assertOk();
        $this->get('/diagrams')->assertRedirect('/subscription');
    }

    public function test_expired_school_can_view_and_print_own_paper_but_not_another_schools_paper(): void
    {
        $owner = $this->owner();
        $paper = SavedPaper::create([
            'institution_id' => $owner->institution_id, 'user_id' => $owner->id, 'title' => 'Private exam',
            'config_snapshot' => ['question_ids' => []], 'layout_snapshot' => [],
        ]);
        $owner->institution->update(['access_expires_at' => now()->subSecond()]);
        $this->actingAs($owner)->get("/editor/{$paper->id}")->assertOk();
        $this->get("/editor/{$paper->id}/print")->assertOk();
        $this->patchJson("/editor/{$paper->id}", ['layout_snapshot' => []])->assertForbidden();
        $other = $this->owner();
        $other->institution->update(['access_expires_at' => now()->subSecond()]);
        $this->actingAs($other)->get("/editor/{$paper->id}")->assertForbidden();
        $this->get("/editor/{$paper->id}/print")->assertForbidden();
    }

    public function test_email_change_starts_a_new_grace_period_and_sends_verification(): void
    {
        Notification::fake();
        $this->travelTo(now()->startOfSecond());
        $owner = $this->owner();
        $this->actingAs($owner)->patch('/profile', ['name' => $owner->name, 'email' => 'new-owner@example.com'])->assertRedirect('/profile');
        $this->assertNull($owner->fresh()->email_verified_at);
        $this->assertTrue($owner->fresh()->verification_due_at->equalTo(now()->addHour()));
        Notification::assertSentTo($owner, VerifyEmail::class);
    }

    public function test_resend_is_throttled_and_does_not_extend_verification_deadline(): void
    {
        Notification::fake();
        $owner = $this->owner(false);
        $due = $owner->fresh()->verification_due_at;
        $this->actingAs($owner);
        for ($i = 0; $i < 6; $i++) {
            $this->post('/email/verification-notification')->assertRedirect();
        }
        $this->post('/email/verification-notification')->assertStatus(429);
        $this->assertTrue($owner->fresh()->verification_due_at->equalTo($due));
        Notification::assertSentToTimes($owner, VerifyEmail::class, 6);
    }

    public function test_exact_expiry_boundary_and_status_endpoint(): void
    {
        $this->travelTo(now()->startOfSecond());
        $owner = $this->owner();
        $owner->institution->update(['access_expires_at' => now()->addSecond()]);
        $this->actingAs($owner)->getJson('/access-status')->assertOk()->assertJsonPath('subscription.expired', false);
        $this->travel(1)->seconds();
        $this->getJson('/access-status')->assertOk()->assertJsonPath('subscription.expired', true);
        $this->postJson('/diagrams', [])->assertForbidden();
    }

    private function owner(bool $verified = true): User
    {
        $school = Institution::create(['name' => 'Access School', 'expiry_date' => now()->addWeek(), 'access_expires_at' => now()->addWeek(), 'license_type' => 'full']);
        $user = User::factory()->create(['institution_id' => $school->id, 'email_verified_at' => $verified ? now() : null, 'verification_due_at' => now()->addHour()]);
        $user->assignRole(Role::findOrCreate('institution_admin', 'web'));

        return $user;
    }

    public function test_owner_has_one_hour_then_must_verify_and_cannot_bypass_via_api(): void
    {
        $user = $this->owner(false);
        $this->actingAs($user)->get('/saved-papers')->assertOk();
        $this->travel(1)->hours();
        $this->get('/saved-papers')->assertRedirect('/verify-email');
        $this->postJson('/questions', [])->assertStatus(403)->assertJsonPath('code', 'email_verification_required');
        $this->get('/verify-email')->assertOk();
        $this->get('/profile')->assertOk();
    }

    public function test_expired_school_can_read_saved_papers_but_cannot_mutate_or_open_builder(): void
    {
        $user = $this->owner();
        $user->institution->update(['access_expires_at' => now()->subSecond()]);
        $this->actingAs($user)->get('/saved-papers')->assertOk();
        $this->get('/subscription')->assertOk();
        $this->get('/builder')->assertRedirect('/subscription');
        $this->postJson('/questions', [])->assertStatus(403)->assertJsonPath('code', 'subscription_expired');
        $this->postJson('/diagrams', [])->assertStatus(403);
        $this->getJson('/api/builder/questions')->assertStatus(403);
    }

    public function test_teacher_without_email_and_super_admin_are_exempt_from_verification(): void
    {
        $owner = $this->owner();
        $teacher = User::factory()->create(['institution_id' => $owner->institution_id, 'email' => null, 'email_verified_at' => null]);
        $teacher->assignRole(Role::findOrCreate('teacher', 'web'));
        $this->actingAs($teacher)->get('/saved-papers')->assertOk();
        $admin = User::factory()->unverified()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin)->get('/super-admin/schools')->assertOk();
    }

    public function test_production_log_mailer_does_not_lock_owner_out(): void
    {
        $user = $this->owner(false);
        $user->update(['verification_due_at' => now()->subHour()]);
        $this->app->detectEnvironment(fn () => 'production');
        config(['mail.default' => 'log']);
        $this->actingAs($user)->get('/saved-papers')->assertOk();
    }
}
