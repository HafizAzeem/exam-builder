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
            DiagramSeeder::class,
            BoardSeeder::class,
            PreferredQuestionSiteSeeder::class,
            CurriculumSeeder::class,
            // Punjab / Lahore Board (PCTB–PECTAA) real subject + chapter titles for 5–12
            Class5PunjabChaptersSeeder::class,
            Class6PunjabChaptersSeeder::class,
            Class7PunjabChaptersSeeder::class,
            Class8PunjabChaptersSeeder::class,
            Class9PunjabChaptersSeeder::class,
            Class10PunjabChaptersSeeder::class,
            Class11PunjabChaptersSeeder::class,
            Class12PunjabChaptersSeeder::class,
            PunjabArtsCommerceChaptersSeeder::class,
            Pectaa2025_26VerifiedSyllabusSeeder::class,
            PectaaCurriculumCatalogueSeeder::class,
            PectaaVerifiedBookContentsSeeder::class,
            LatestPunjabTextbooksSeeder::class,
            // Class9TopicsSeeder::class,
            // QuestionBankSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
