<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Institution;
use App\Models\User;
use App\Support\SubscriptionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionPlansTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $user;
    }

    private function plans(): array
    {
        return DB::table('subscription_plans')->orderBy('sort_order')->get()->map(fn ($plan) => [
            'key' => $plan->key, 'label' => $plan->label, 'label_ur' => $plan->label_ur,
            'amount' => (int) $plan->amount, 'months' => (int) $plan->months,
            'sort_order' => (int) $plan->sort_order, 'is_active' => (bool) $plan->is_active,
        ])->all();
    }

    public function test_defaults_and_super_admin_only_settings(): void
    {
        $this->get('/super-admin/plans')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/super-admin/plans')->assertForbidden();
        $this->put('/super-admin/plans', [])->assertForbidden();
        $this->actingAs($this->admin())->get('/super-admin/plans')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Plans/Index')->where('trialDays', 14)->has('plans', 3));
        $this->assertSame([500, 1350, 4500], array_column(SubscriptionCatalog::active(), 'amount'));
    }

    public function test_admin_edits_feed_public_prices_schema_and_renewals(): void
    {
        $plans = $this->plans();
        $plans[0]['label'] = 'School "Monthly" <plan>';
        $plans[0]['amount'] = 777;
        $plans[1]['is_active'] = false;
        $plans[] = ['key' => 'semester', 'label' => 'Semester', 'label_ur' => 'چھ ماہ', 'amount' => 2500, 'months' => 6, 'sort_order' => 4, 'is_active' => true];
        $this->actingAs($this->admin())->put('/super-admin/plans', ['trial_days' => 21, 'plans' => $plans])->assertSessionHasNoErrors()->assertRedirect();
        foreach (['/', '/ur', '/pricing', '/ur/pricing'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('777', $html);
            $this->assertStringContainsString('2,500', $html);
            $this->assertStringNotContainsString('1,350', $html);
            preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $html, $match);
            $schema = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
            $app = collect($schema['@graph'])->firstWhere('@type', 'SoftwareApplication');
            $this->assertSame([0, 777, 4500, 2500], array_column($app['offers'], 'price'));
            $this->assertStringContainsString('21', $app['offers'][0]['name']);
        }
        $school = Institution::create(['name' => 'Test School', 'license_type' => 'full', 'expiry_date' => now()->addDay(), 'access_expires_at' => now()->addDay()]);
        $owner = User::factory()->create(['institution_id' => $school->id]);
        $owner->assignRole(Role::findOrCreate('institution_admin', 'web'));
        $this->actingAs($owner)->get('/subscription')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('plans.monthly.amount', 777)->missing('plans.quarterly')->where('plans.semester.months', 6));
    }

    public function test_settings_validation_preserves_existing_plans_and_requires_an_active_plan(): void
    {
        $this->actingAs($this->admin());
        $plans = $this->plans();
        foreach ($plans as &$plan) {
            $plan['is_active'] = false;
        }
        unset($plan);
        $this->put('/super-admin/plans', ['trial_days' => 14, 'plans' => $plans])->assertSessionHasErrors('plans');
        $plans = $this->plans();
        $plans[0]['amount'] = -1;
        $plans[0]['months'] = 0;
        $this->put('/super-admin/plans', ['trial_days' => 0, 'plans' => $plans])->assertSessionHasErrors(['trial_days', 'plans.0.amount', 'plans.0.months']);
        $this->put('/super-admin/plans', ['trial_days' => 14, 'plans' => array_slice($this->plans(), 1)])->assertSessionHasErrors('plans');
        $this->assertSame([500, 1350, 4500], array_column(SubscriptionCatalog::active(), 'amount'));
    }

    public function test_annual_activation_and_price_edits_preserve_expiry_and_history(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));
        $school = Institution::create(['name' => 'Renew School', 'license_type' => 'full', 'expiry_date' => now()->addDays(5), 'access_expires_at' => now()->addDays(5)]);
        $this->actingAs($this->admin())->post("/super-admin/schools/{$school->id}/adjust", [
            'action' => 'renew', 'plan' => 'annual', 'confirmed' => true, 'request_id' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $expiry = $school->fresh()->access_expires_at;
        $this->assertTrue($expiry->equalTo(now()->addDays(5)->addMonthsNoOverflow(12)));
        $this->assertDatabaseHas('school_account_events', ['plan' => 'annual', 'amount' => 4500]);
        $plans = $this->plans();
        $plans[2]['amount'] = 6000;
        $plans[2]['is_active'] = false;
        $this->put('/super-admin/plans', ['trial_days' => 30, 'plans' => $plans])->assertSessionHasNoErrors();
        $this->assertTrue($school->fresh()->access_expires_at->equalTo($expiry));
        $this->assertDatabaseHas('school_account_events', ['plan' => 'annual', 'amount' => 4500]);
        $this->post("/super-admin/schools/{$school->id}/adjust", ['action' => 'renew', 'plan' => 'annual', 'confirmed' => true, 'request_id' => (string) Str::uuid()])->assertSessionHasErrors('plan');
        $this->assertDatabaseCount('school_account_events', 1);
    }

    public function test_edited_trial_controls_new_registration(): void
    {
        Notification::fake();
        $this->travelTo(now()->startOfSecond());
        DB::table('subscription_settings')->where('id', 1)->update(['trial_days' => 21]);
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $page) => $page->where('trialDays', 21));
        $board = Board::create(['name' => 'Test Board', 'is_active' => true]);
        $this->post('/register', ['name' => 'Owner', 'school_name' => 'New School', 'phone' => '03001234567', 'board_id' => $board->id,
            'email' => 'new@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect('/dashboard');
        $this->assertTrue(User::where('email', 'new@example.com')->firstOrFail()->institution->access_expires_at->equalTo(now()->addDays(21)));
    }

    public function test_plan_payload_cannot_overwrite_internal_database_fields(): void
    {
        $plans = $this->plans();
        $plans[0]['id'] = 9999;
        $this->actingAs($this->admin())->put('/super-admin/plans', ['trial_days' => 14, 'plans' => $plans])
            ->assertSessionHasErrors('plans.0');
        $this->assertDatabaseMissing('subscription_plans', ['id' => 9999]);
    }
}
