<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $adminPhone = Phone::normalize('03211414695') ?? '03211414695';

        $institution = Institution::query()
            ->where('name', 'The Grammar High School Kamal Pura')
            ->orWhere('name', 'Demo High School')
            ->first();

        $institutionData = [
            'name' => 'The Grammar High School Kamal Pura',
            'owner_name' => 'MS',
            'phone' => $adminPhone,
            'address' => 'Kamal Pura',
            'city' => 'Lahore',
            'license_type' => 'full',
        ];

        if ($institution) {
            $institution->fill($institutionData);
            if (! $institution->expiry_date || $institution->expiry_date->isPast()) {
                $institution->expiry_date = now()->addYear();
            }
            $institution->save();
        } else {
            $institution = Institution::create([
                ...$institutionData,
                'expiry_date' => now()->addYear(),
            ]);
        }

        $admin = User::query()
            ->where('email', 'ms@gmail.com')
            ->orWhere('email', 'admin@demo.edu')
            ->first();

        $adminData = [
            'institution_id' => $institution->id,
            'name' => 'MS',
            'email' => 'ms@gmail.com',
            'phone' => $adminPhone,
            'is_active' => true,
        ];

        if ($admin) {
            $admin->fill($adminData)->save();
        } else {
            $admin = User::create([
                ...$adminData,
                'password' => Hash::make('password'),
            ]);
        }

        if (! $admin->hasRole('institution_admin')) {
            $admin->assignRole('institution_admin');
        }

        $teacher = User::query()
            ->where('email', 'teacher@demo.edu')
            ->first();

        $teacherData = [
            'institution_id' => $institution->id,
            'name' => 'Demo Teacher',
            'email' => 'teacher@demo.edu',
            'phone' => Phone::normalize('03002222222') ?? '03002222222',
            'is_active' => true,
        ];

        if ($teacher) {
            $teacher->fill($teacherData)->save();
        } else {
            $teacher = User::create([
                ...$teacherData,
                'password' => Hash::make('password'),
            ]);
        }

        if (! $teacher->hasRole('teacher')) {
            $teacher->assignRole('teacher');
        }

        $superAdmin = User::query()
            ->where('email', 'super@exambuilder.test')
            ->first();

        $superData = [
            'institution_id' => null,
            'name' => 'Super Admin',
            'email' => 'super@exambuilder.test',
            'phone' => Phone::normalize('03009999999') ?? '03009999999',
            'is_active' => true,
        ];

        if ($superAdmin) {
            $superAdmin->fill($superData)->save();
        } else {
            $superAdmin = User::create([
                ...$superData,
                'password' => Hash::make('password'),
            ]);
        }

        if (! $superAdmin->hasRole('super_admin')) {
            $superAdmin->assignRole('super_admin');
        }
    }
}
