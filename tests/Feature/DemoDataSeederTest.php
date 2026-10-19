<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_grammar_high_school_admin(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('institutions', [
            'name' => 'The Grammar High School Kamal Pura',
            'owner_name' => 'MS',
            'phone' => '03211414695',
        ]);

        $admin = User::query()->where('email', 'ms@gmail.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame('MS', $admin->name);
        $this->assertSame('03211414695', $admin->phone);
        $this->assertTrue($admin->hasRole('institution_admin'));
        $this->assertTrue(Hash::check('password', $admin->password));
    }

    public function test_seeder_updates_legacy_demo_school(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $institution = Institution::query()->create([
            'name' => 'Demo High School',
            'owner_name' => 'Admin User',
            'phone' => '03001234567',
            'address' => 'Main Boulevard, Lahore',
            'city' => 'Lahore',
            'expiry_date' => now()->addYear(),
            'license_type' => 'full',
        ]);

        $admin = User::factory()->create([
            'institution_id' => $institution->id,
            'name' => 'Institute Admin',
            'email' => 'admin@demo.edu',
            'phone' => '03001111111',
        ]);
        $admin->assignRole('institution_admin');

        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseMissing('institutions', ['name' => 'Demo High School']);
        $this->assertDatabaseHas('institutions', [
            'id' => $institution->id,
            'name' => 'The Grammar High School Kamal Pura',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'admin@demo.edu']);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => 'ms@gmail.com',
            'phone' => '03211414695',
            'name' => 'MS',
        ]);
    }
}
