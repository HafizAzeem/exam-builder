<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\McqOption;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'admin@demo.edu')->exists()) {
            return;
        }

        $institution = Institution::create([
            'name' => 'Demo High School',
            'owner_name' => 'Admin User',
            'phone' => '03001234567',
            'address' => 'Main Boulevard, Lahore',
            'city' => 'Lahore',
            'expiry_date' => now()->addYear(),
            'license_type' => 'full',
        ]);

        $admin = User::create([
            'institution_id' => $institution->id,
            'name' => 'Institute Admin',
            'email' => 'admin@demo.edu',
            'phone' => '03001111111',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('institution_admin');

        $teacher = User::create([
            'institution_id' => $institution->id,
            'name' => 'Demo Teacher',
            'email' => 'teacher@demo.edu',
            'phone' => '03002222222',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');

        $superAdmin = User::create([
            'institution_id' => null,
            'name' => 'Super Admin',
            'email' => 'super@exambuilder.test',
            'phone' => '03009999999',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        // Question bank + dropdown data is seeded via CurriculumSeeder + QuestionBankSeeder.
    }
}
