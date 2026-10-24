<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\SyllabusChapter;
use App\Models\SyllabusEdition;
use Database\Seeders\Concerns\SeedsSubjectChapters;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Latest published PECTAA books and reviewed contents, as of 24 October 2026.
 * Runs after the legacy seeders so the paper builder uses the new titles.
 */
class LatestPunjabTextbooksSeeder extends Seeder
{
    use SeedsSubjectChapters;

    public function run(): void
    {
        $books = json_decode(file_get_contents(database_path('seeders/data/punjab-latest-textbooks.json')), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($books): void {
            $this->call(GradeSeeder::class);

            foreach ($books as $index => $book) {
                $grade = Grade::query()->where('number', $book['grade'])->firstOrFail();
                $aliases = match ($book['subject']) {
                    'Islamiyat' => ['Islamiat'],
                    'Waqfiyat e Aama' => ['General Knowledge'],
                    'Applied Electricity' => ['Itlaqi Barqiat'],
                    'Agriculture' => ['Zari Taleem'],
                    'Pakistan Studies' => ['Pak Studies'],
                    'Art and Model Drawing' => ['Art and Drawing'],
                    'Farsi' => ['Persian'],
                    'Logic' => $book['grade'] === 12 ? ['Philosophy'] : [],
                    'Computer Science and Entrepreneurship' => ['Computer Science', 'Computer and Entrepreneurship'],
                    'Computer Science and Entrepreneurship (Matric Tech)' => ['Computer Science (Matric Tech)', 'Computer and Entrepreneurship (Matric Tech)'],
                    default => [],
                };
                $subject = $grade->subjects()->where('name_en', $book['subject'])->first()
                    ?? $grade->subjects()->whereIn('name_en', $aliases)->first();
                if (! $subject) {
                    $subject = Subject::query()->create([
                        'grade_id' => $grade->id,
                        'name_en' => $book['subject'],
                        'name_ur' => $book['subject_ur'] ?? $book['subject'],
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ]);
                } else {
                    $subject->update(['name_en' => $book['subject'], 'is_active' => true]);
                }
                $grade->subjects()->whereIn('name_en', $aliases)->where('id', '!=', $subject->id)
                    ->update(['is_active' => false]);

                // A published book is not evidence of its exam-session applicability.
                $edition = SyllabusEdition::query()->firstOrNew([
                    'academic_session' => '2026-2027',
                    'grade_id' => $grade->id,
                    'subject_id' => $subject->id,
                    'board' => 'Punjab Boards',
                    'group_name' => $book['group'],
                    'textbook_edition' => $book['book_name'],
                ]);
                if (! $edition->exists || (! $edition->verified_at && $edition->status === 'unverified')) {
                    $edition->fill([
                        'effective_session' => '2026-2027',
                        'source_url' => $book['source_url'],
                        'source_type' => $book['chapters'] ? 'official_textbook_contents' : 'official_catalog_listing',
                        'status' => 'unverified',
                    ])->save();
                }

                if (! $book['chapters']) {
                    continue;
                }

                $chapters = [];
                foreach ($book['chapters'] as $offset => $chapter) {
                    $number = is_array($chapter) ? ($chapter['number'] ?? $offset + 1) : $offset + 1;
                    $chapters[$number] = $chapter;
                    $syllabusChapter = SyllabusChapter::query()->firstOrNew([
                        'syllabus_edition_id' => $edition->id, 'chapter_number' => $number,
                    ]);
                    if (! $syllabusChapter->exists || (! $syllabusChapter->verified_at && $syllabusChapter->status === 'unverified')) {
                        $syllabusChapter->fill([
                            'chapter_title' => is_array($chapter) ? $chapter['en'] : $chapter,
                            'source_url' => $book['contents_source_url'] ?? $book['source_url'],
                            'status' => 'unverified',
                            'change_type' => 'unverified',
                            'change_notes' => 'Title checked against the published textbook contents. Full book contents; no examination exclusions are applied.',
                        ])->save();
                    }
                }
                $this->syncChapters($subject, $chapters);
                $subject->chapters()->whereNotIn('number', array_keys($chapters))->update(['is_active' => false]);
            }
        });
    }
}
