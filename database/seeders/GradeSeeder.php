<?php

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        if (Grade::exists()) {
            return;
        }

        for ($n = 1; $n <= 12; $n++) {
            Grade::create([
                'number' => $n,
                'label_en' => "Class {$n}",
                'label_ur' => "جماعت {$n}",
            ]);
        }
    }
}
