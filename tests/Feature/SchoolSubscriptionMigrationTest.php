<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchoolSubscriptionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollout_preserves_expiry_and_gives_existing_unverified_users_one_hour(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('DDL rollback is checked in SQLite; MySQL implicitly commits DDL inside RefreshDatabase transactions.');
        }
        $this->travelTo(now()->startOfSecond());
        $school = Institution::create(['name' => 'Existing School', 'expiry_date' => '2026-12-01', 'license_type' => 'full']);
        $user = User::factory()->unverified()->create(['institution_id' => $school->id]);
        $migration = require database_path('migrations/2026_10_26_000001_add_school_subscriptions_and_contribution_tracking.php');
        $migration->down();
        $migration->up();
        $this->assertSame('2026-12-01 23:59:59', $school->fresh()->access_expires_at->format('Y-m-d H:i:s'));
        $this->assertTrue($user->fresh()->verification_due_at->equalTo(now()->addHour()));
        $this->assertNull($school->fresh()->board_id);
    }
}
