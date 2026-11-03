<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_month_renewal_keeps_remaining_access_and_full_licence(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        $school = $this->school();
        $school->update(['access_expires_at' => '2026-10-31 12:00:00']);
        $this->actingAs($this->admin())->post("/super-admin/schools/{$school->id}/adjust", [
            'action' => 'renew', 'plan' => 'quarterly', 'request_id' => (string) Str::uuid(), 'confirmed' => true,
        ])->assertRedirect();
        $this->assertSame('2027-01-31 12:00:00', $school->fresh()->access_expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('full', $school->fresh()->license_type);
        $this->assertDatabaseHas('school_account_events', ['amount' => 1350, 'plan' => 'quarterly']);
    }

    public function test_manual_verification_cannot_target_an_owner_of_another_school(): void
    {
        $school = $this->school();
        $other = $this->school();
        $owner = User::factory()->unverified()->create(['institution_id' => $other->id]);
        $owner->assignRole(Role::findOrCreate('institution_admin', 'web'));
        $this->actingAs($this->admin())->post("/super-admin/schools/{$school->id}/verify-owner", [
            'user_id' => $owner->id, 'request_id' => (string) Str::uuid(), 'confirmed' => true,
        ])->assertNotFound();
        $this->assertFalse($owner->fresh()->hasVerifiedEmail());
        $this->assertDatabaseCount('school_account_events', 0);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $user;
    }

    private function school(): Institution
    {
        return Institution::create(['name' => 'Renewal School', 'expiry_date' => now(), 'access_expires_at' => now()->subDay(), 'license_type' => 'full', 'subscription_plan' => 'trial']);
    }

    public function test_manual_renewal_uses_calendar_months_and_cannot_be_applied_twice(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 31)->setTime(12, 0));
        $school = $this->school();
        $data = ['action' => 'renew', 'plan' => 'monthly', 'request_id' => (string) Str::uuid(), 'confirmed' => true, 'payment_reference' => 'JC-123'];
        $this->actingAs($this->admin())->post("/super-admin/schools/{$school->id}/adjust", $data)->assertRedirect();
        $this->assertSame('2026-02-28 12:00:00', $school->fresh()->access_expires_at->format('Y-m-d H:i:s'));
        $this->post("/super-admin/schools/{$school->id}/adjust", $data)->assertRedirect();
        $this->assertDatabaseCount('school_account_events', 1);
        $this->assertDatabaseHas('school_account_events', ['amount' => 500, 'payment_reference' => 'JC-123']);
    }

    public function test_trial_extension_starts_from_future_expiry_and_records_audit(): void
    {
        $school = $this->school();
        $expiry = now()->addDays(5)->startOfSecond();
        $school->update(['access_expires_at' => $expiry]);
        $this->actingAs($this->admin())->post("/super-admin/schools/{$school->id}/adjust", [
            'action' => 'extend_trial', 'weeks' => 10, 'request_id' => (string) Str::uuid(), 'confirmed' => true,
        ])->assertRedirect();
        $this->assertTrue($school->fresh()->access_expires_at->equalTo($expiry->copy()->addWeeks(10)));
        $this->assertDatabaseHas('school_account_events', ['action' => 'extend_trial', 'amount' => 0]);
    }

    public function test_super_admin_can_verify_owner_but_school_owner_cannot_adjust_subscriptions(): void
    {
        $school = $this->school();
        $owner = User::factory()->unverified()->create(['institution_id' => $school->id]);
        $owner->assignRole(Role::findOrCreate('institution_admin', 'web'));
        $this->actingAs($this->admin())->post("/super-admin/schools/{$school->id}/verify-owner", [
            'user_id' => $owner->id, 'request_id' => (string) Str::uuid(), 'confirmed' => true,
        ])->assertRedirect();
        $this->assertTrue($owner->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('school_account_events', ['action' => 'verify_email', 'user_id' => $owner->id]);
        $this->actingAs($owner)->post("/super-admin/schools/{$school->id}/adjust", [])->assertForbidden();
    }

    public function test_adjustments_require_confirmation_and_valid_plan(): void
    {
        $school = $this->school();
        $this->actingAs($this->admin())->post("/super-admin/schools/{$school->id}/adjust", [
            'action' => 'renew', 'plan' => 'free', 'request_id' => (string) Str::uuid(),
        ])->assertSessionHasErrors(['confirmed', 'plan']);
        $this->assertDatabaseCount('school_account_events', 0);
    }
}
