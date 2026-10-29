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
            ['name' => 'Federal Board (FBISE), Islamabad', 'region' => 'Federal', 'sort_order' => 10],
            ['name' => 'Peshawar Board', 'region' => 'Khyber Pakhtunkhwa', 'sort_order' => 11],
            ['name' => 'Abbottabad Board', 'region' => 'Khyber Pakhtunkhwa', 'sort_order' => 12],
            ['name' => 'Bannu Board', 'region' => 'Khyber Pakhtunkhwa', 'sort_order' => 13],
            ['name' => 'Dera Ismail Khan Board', 'region' => 'Khyber Pakhtunkhwa', 'sort_order' => 14],
            ['name' => 'Kohat Board', 'region' => 'Khyber Pakhtunkhwa', 'sort_order' => 15],
            ['name' => 'Malakand Board', 'region' => 'Khyber Pakhtunkhwa', 'sort_order' => 16],
            ['name' => 'Mardan Board', 'region' => 'Khyber Pakhtunkhwa', 'sort_order' => 17],
            ['name' => 'Swat Board', 'region' => 'Khyber Pakhtunkhwa', 'sort_order' => 18],
            ['name' => 'Karachi Secondary Board (BSEK)', 'region' => 'Sindh', 'sort_order' => 19],
            ['name' => 'Karachi Intermediate Board (BIEK)', 'region' => 'Sindh', 'sort_order' => 20],
            ['name' => 'Hyderabad Board', 'region' => 'Sindh', 'sort_order' => 21],
            ['name' => 'Sukkur Board', 'region' => 'Sindh', 'sort_order' => 22],
            ['name' => 'Larkana Board', 'region' => 'Sindh', 'sort_order' => 23],
            ['name' => 'Mirpurkhas Board', 'region' => 'Sindh', 'sort_order' => 24],
            ['name' => 'Shaheed Benazirabad Board', 'region' => 'Sindh', 'sort_order' => 25],
            ['name' => 'Balochistan Board (BBISE), Quetta', 'region' => 'Balochistan', 'sort_order' => 26],
            ['name' => 'AJK Board, Mirpur', 'region' => 'Azad Jammu and Kashmir', 'sort_order' => 27],
            ['name' => 'KIU Examination Board', 'region' => 'Gilgit-Baltistan', 'sort_order' => 28],
            ['name' => 'Aga Khan University Examination Board', 'region' => 'Sindh', 'sort_order' => 29],
            ['name' => 'Ziauddin Examination Board', 'region' => 'Sindh', 'sort_order' => 30],
        ];

        foreach ($boards as $board) {
            Board::query()->firstOrCreate(
                ['name' => $board['name']],
                [
                    'region' => $board['region'] ?? 'Punjab',
                    'sort_order' => $board['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
