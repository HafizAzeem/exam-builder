<?php

namespace Database\Seeders;

use App\Models\Diagram;
use Illuminate\Database\Seeder;

class DiagramSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = json_decode(file_get_contents(database_path('seeders/data/diagram-catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($catalogue as $entry) {
            Diagram::query()->updateOrCreate(['builtin_key' => $entry['builtin_key']], $entry + ['status' => 'approved', 'is_builtin' => true, 'source' => 'Original editable teaching diagram; schematic, not to scale.']);
        }
    }
}
