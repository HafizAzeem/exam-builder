<?php

namespace Database\Seeders;

use App\Models\Board;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    public function run(): void
    {
        Board::query()->firstOrCreate(
            ['name' => 'Lahore Board'],
            [
                'region' => 'Punjab',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );
    }
}
