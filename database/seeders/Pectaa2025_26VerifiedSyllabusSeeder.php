<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\SyllabusChapter;
use App\Models\SyllabusEdition;
use Illuminate\Database\Seeder;

/** Verified from PECTAA's Grade 9 English 2025–26 e-book and revised ALP. */
class Pectaa2025_26VerifiedSyllabusSeeder extends Seeder
{
    private const SESSION = '2025-2026';

    private const BOOK_URL = 'https://drive.google.com/file/d/1mWBO-wzXqv0Oq9oazcjM-Y16EqmPqBtj/view';

    private const ALP_URL = 'https://pectaa.edu.pk/uploads/Revised%20ALP%20Notification%2007.11.2025.pdf';

    private const COMPUTER_BOOK_URL = 'https://drive.google.com/file/d/1K9AWmNIjYFUNWE_F8JkELmlEkZO9Ms85/view';

    /** @var array<int, string> */
    private const CHAPTERS = [
        1 => 'The Saviour of Mankind',
        2 => 'Patriotism',
        3 => 'Daffodils (Poem)',
        4 => 'Hazrat Asma (R.A.)',
        5 => 'Women Empowerment through Entrepreneurship',
        6 => 'The Value of Time',
        7 => 'If (Poem)',
        8 => 'The Impact of Globalisation on Culture and Economy',
        9 => 'Quality Education: A Key to Success',
        10 => 'The Silent Predator and the Majestic Prey – Snow Leopard and Markhor',
        11 => 'The Dear Departed (one-act play)',
    ];

    /** Units excluded from the Grade 9 Annual Exam 2026 by PECTAA's revised ALP. */
    private const EXCLUDED_UNITS = [5, 8, 10];

    public function run(): void
    {
        $grade = Grade::query()->where('number', 9)->first();
        $subject = $grade?->subjects()->where('name_en', 'English')->first();
        if (! $grade || ! $subject) {
            return;
        }

        $edition = SyllabusEdition::query()
            ->where('academic_session', self::SESSION)
            ->where('grade_id', $grade->id)
            ->where('subject_id', $subject->id)
            ->where('board', 'Punjab Boards')
            ->whereNull('group_name')
            ->where('textbook_edition', 'PECTAA English 9 2025-26')
            ->first();

        $attributes = [
            'academic_session' => self::SESSION,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'board' => 'Punjab Boards',
            'group_name' => null,
            'textbook_edition' => 'PECTAA English 9 2025-26',
            'effective_session' => self::SESSION,
            'status' => 'current',
            'source_url' => self::BOOK_URL,
            'verified_at' => now()->toDateString(),
            'source_type' => 'official_textbook_and_smart_syllabus',
        ];

        if ($edition && ! $edition->verified_at && $edition->status === 'unverified') {
            $edition->update($attributes);
        } elseif (! $edition) {
            $edition = SyllabusEdition::query()->create($attributes);
        }

        foreach (self::CHAPTERS as $number => $title) {
            $existing = SyllabusChapter::query()->where('syllabus_edition_id', $edition->id)
                ->where('chapter_number', $number)->first();
            if ($existing && ($existing->verified_at || $existing->status !== 'unverified')) {
                continue;
            }
            $excluded = in_array($number, self::EXCLUDED_UNITS, true);
            $sourceUrl = $excluded ? self::ALP_URL : self::BOOK_URL;

            SyllabusChapter::query()->updateOrCreate(
                [
                    'syllabus_edition_id' => $edition->id,
                    'chapter_number' => $number,
                ],
                [
                    'chapter_title' => $title,
                    'topics' => null,
                    // These units remain in the textbook but PECTAA excludes
                    // the complete units from the 2026 examination syllabus.
                    'status' => $excluded ? 'deleted' : 'current',
                    // Previous-edition comparison has not been source-verified.
                    'change_type' => 'unverified',
                    'source_url' => $sourceUrl,
                    'verified_at' => now()->toDateString(),
                    'change_notes' => $this->notes($number, $excluded),
                ]
            );
        }

        $this->seedComputerScience($grade);
    }

    private function seedComputerScience(Grade $grade): void
    {
        $subject = $grade->subjects()->where('name_en', 'Computer Science')->first();
        if (! $subject) {
            return;
        }

        $edition = SyllabusEdition::query()->firstOrNew(
            [
                'academic_session' => self::SESSION,
                'grade_id' => $grade->id,
                'subject_id' => $subject->id,
                'board' => 'Punjab Boards',
                'group_name' => null,
                'textbook_edition' => 'PECTAA Computer Science 9 2025-26',
            ]
        );
        if (! $edition->exists || (! $edition->verified_at && $edition->status === 'unverified')) {
            $edition->fill([
                'effective_session' => self::SESSION,
                'status' => 'current',
                'source_url' => self::COMPUTER_BOOK_URL,
                'verified_at' => now()->toDateString(),
                'source_type' => 'official_textbook',
            ])->save();
        }

        $titles = [
            'Introduction to Systems', 'Number Systems', 'Digital Systems and Logic Design',
            'System Troubleshooting', 'Software System', 'Introduction to Computer Networks',
            'Computational Thinking', 'Web Development with HTML, CSS and JavaScript',
            'Data Science and Data Gathering', 'Emerging Technologies in Computer Science',
            'Ethical, Social, and Legal Concerns in Computer Usage', 'Entrepreneurship in Digital Age',
        ];

        foreach ($titles as $index => $title) {
            $existing = SyllabusChapter::query()->where('syllabus_edition_id', $edition->id)
                ->where('chapter_number', $index + 1)->first();
            if ($existing && ($existing->verified_at || $existing->status !== 'unverified')) {
                continue;
            }
            SyllabusChapter::query()->updateOrCreate(
                ['syllabus_edition_id' => $edition->id, 'chapter_number' => $index + 1],
                [
                    'chapter_title' => $title,
                    'topics' => null,
                    'status' => 'current',
                    'change_type' => 'unverified',
                    'source_url' => self::COMPUTER_BOOK_URL,
                    'verified_at' => now()->toDateString(),
                    'change_notes' => 'Chapter title verified against the PECTAA Computer Science 9 2025-26 textbook. Previous-edition comparison and exam exclusions have not been verified.',
                ]
            );
        }
    }

    private function notes(int $number, bool $excluded): string
    {
        $book = 'Chapter title verified against the PECTAA English 9 2025-26 e-book (' . self::BOOK_URL . ').';
        $comparison = 'Change against earlier editions is unverified.';

        if ($excluded) {
            return $book . ' Entire unit excluded from Annual Exam 2026 by PECTAA Revised ALP (' . self::ALP_URL . '). This is an exam-syllabus exclusion; the unit remains in the textbook. ' . $comparison;
        }

        return $book . ' ' . $comparison;
    }
}
