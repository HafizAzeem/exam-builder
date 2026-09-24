<?php

namespace Database\Seeders;

use App\Models\PreferredQuestionSite;
use Illuminate\Database\Seeder;

class PreferredQuestionSiteSeeder extends Seeder
{
    public function run(): void
    {
        $sites = [
            [
                'name' => 'Free Ilm',
                'domain' => 'freeilm.com',
                'source_types' => ['online_practice', 'exercise'],
                'priority' => 10,
            ],
            [
                'name' => 'GoTest',
                'domain' => 'gotest.com.pk',
                'source_types' => ['online_practice', 'exercise', 'past_paper'],
                'priority' => 20,
            ],
            [
                'name' => 'Ilm Ki Dunya',
                'domain' => 'ilmkidunya.com',
                'source_types' => ['online_practice', 'exercise', 'past_paper'],
                'priority' => 30,
            ],
            [
                'name' => 'Study Plus',
                'domain' => 'studyplus.pk',
                'source_types' => ['online_practice', 'exercise'],
                'priority' => 40,
            ],
            [
                'name' => 'Class Notes',
                'domain' => 'classnotes.xyz',
                'source_types' => ['online_practice', 'exercise'],
                'priority' => 50,
            ],
            [
                'name' => 'Taleem 360',
                'domain' => 'taleem360.com',
                'source_types' => ['online_practice', 'exercise', 'past_paper'],
                'priority' => 60,
            ],
        ];

        foreach ($sites as $site) {
            PreferredQuestionSite::query()->updateOrCreate(
                ['domain' => PreferredQuestionSite::normalizeDomain($site['domain'])],
                [
                    'name' => $site['name'],
                    'source_types' => $site['source_types'],
                    'priority' => $site['priority'],
                    'is_active' => true,
                    'notes' => null,
                ]
            );
        }
    }
}
