<?php

namespace Database\Seeders;

use App\Models\Board;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    public function run(): void
    {
        $boards = [
            ['name' => 'Lahore Board', 'sort_order' => 1],
            ['name' => 'Gujranwala Board', 'sort_order' => 2],
            ['name' => 'Rawalpindi Board', 'sort_order' => 3],
            ['name' => 'Faisalabad Board', 'sort_order' => 4],
            ['name' => 'Multan Board', 'sort_order' => 5],
            ['name' => 'Sargodha Board', 'sort_order' => 6],
            ['name' => 'Sahiwal Board', 'sort_order' => 7],
            ['name' => 'Bahawalpur Board', 'sort_order' => 8],
            ['name' => 'DG Khan Board', 'sort_order' => 9],
        ];

        foreach ($boards as $board) {
            Board::query()->firstOrCreate(
                ['name' => $board['name']],
                [
                    'region' => 'Punjab',
                    'sort_order' => $board['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
