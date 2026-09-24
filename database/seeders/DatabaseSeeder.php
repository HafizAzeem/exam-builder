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
            PreferredQuestionSiteSeeder::class,
            CurriculumSeeder::class,
            // Punjab / Lahore Board (PCTB–PECTAA) real subject + chapter titles for 9–12
            Class9PunjabChaptersSeeder::class,
            Class10PunjabChaptersSeeder::class,
            Class11PunjabChaptersSeeder::class,
            Class12PunjabChaptersSeeder::class,
            // Class9TopicsSeeder::class,
            // QuestionBankSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
