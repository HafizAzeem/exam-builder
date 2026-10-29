<?php

namespace Tests\Feature\Auth;

use App\Models\Board;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'school_name' => 'Test School',
            'phone' => '03284177318',
            'board_id' => Board::create(['name' => 'Punjab', 'is_active' => true])->id,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_requires_an_active_education_board(): void
    {
        $inactive = Board::create(['name' => 'Inactive Signup Board', 'is_active' => false]);
        $schoolsBefore = \App\Models\Institution::count();
        $usersBefore = \App\Models\User::count();
        $signup = [
            'name' => 'Test Owner', 'school_name' => 'Test School',
            'email' => 'owner@example.com', 'phone' => '03001234567',
            'password' => 'password', 'password_confirmation' => 'password',
        ];

        foreach ([null, '', $inactive->id, 999999] as $boardId) {
            $response = $this->from('/register')->post('/register', $signup + ['board_id' => $boardId]);
            $response->assertRedirect('/register')->assertSessionHasErrors('board_id');
            $this->assertGuest();
            $this->assertSame($schoolsBefore, \App\Models\Institution::count());
            $this->assertSame($usersBefore, \App\Models\User::count());
        }
    }
}
