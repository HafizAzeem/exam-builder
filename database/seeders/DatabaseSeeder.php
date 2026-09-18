<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            GradeSeeder::class,
            BoardSeeder::class,
            CurriculumSeeder::class,
            Class9EnglishChaptersSeeder::class,
            Class10SubjectsSeeder::class,
            Class11SubjectsSeeder::class,
            Class12SubjectsSeeder::class,
            // Class9TopicsSeeder::class,
            // QuestionBankSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
