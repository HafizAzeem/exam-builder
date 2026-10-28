<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_rolls_back_school_user_and_logo_on_failure(): void
    {
        Storage::fake('public');
        $data = $this->payload();
        $data['logo'] = UploadedFile::fake()->image('school.png');
        Event::listen('eloquent.created: '.User::class, function () {
            throw new \RuntimeException('Simulated account creation failure');
        });
        try {
            $this->post('/register', $data)->assertStatus(500);
            $this->assertDatabaseCount('institutions', 0);
            $this->assertDatabaseCount('users', 0);
            $this->assertSame([], Storage::disk('public')->allFiles('logos'));
        } finally {
            Event::forget('eloquent.created: '.User::class);
        }
    }

    private function payload(): array
    {
        return ['name' => 'School Owner', 'school_name' => 'Bright School', 'email' => 'owner@example.com',
            'phone' => '+92 328 4177318', 'board_id' => Board::create(['name' => 'Punjab', 'is_active' => true])->id,
            'password' => 'password', 'password_confirmation' => 'password'];
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_signup_creates_a_school_owner_with_exact_fourteen_day_trial_and_logo(): void
    {
        Notification::fake();
        Storage::fake('public');
        $this->freezeTime();
        $this->travelTo(now()->startOfSecond());
        $data = $this->payload();
        $data['logo'] = UploadedFile::fake()->image('school.png');
        $this->post('/register', $data)->assertRedirect('/dashboard');
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('institution_admin'));
        $this->assertSame('03284177318', $user->phone);
        $this->assertSame($data['board_id'], $user->institution->board_id);
        $this->assertSame('trial', $user->institution->subscription_plan);
        $this->assertTrue($user->institution->access_expires_at->equalTo(now()->addDays(14)));
        $this->assertTrue($user->verification_due_at->equalTo(now()->addHour()));
        Storage::disk('public')->assertExists($user->institution->logo_path);
        Notification::assertSentTo($user, VerifyEmail::class);
        if (DB::getDriverName() === 'mysql') {
            $this->get('/dashboard')->assertOk();
        }
    }

    public function test_invalid_signup_creates_no_school_or_user(): void
    {
        $data = $this->payload();
        Board::find($data['board_id'])->update(['is_active' => false]);
        $this->post('/register', $data)->assertSessionHasErrors('board_id');
        $this->assertDatabaseCount('institutions', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signup_rejects_invalid_whatsapp_and_non_image_logo(): void
    {
        $data = $this->payload();
        $data['phone'] = '123';
        $data['logo'] = UploadedFile::fake()->create('logo.svg', 2, 'image/svg+xml');
        $this->post('/register', $data)->assertSessionHasErrors(['phone', 'logo']);
        $this->assertSame(0, Institution::count());
    }
}
