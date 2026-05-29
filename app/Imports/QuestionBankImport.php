<?php

namespace App\Imports;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\McqOption;
use App\Models\PastPaperTag;
use App\Models\Question;
use App\Models\Subject;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use App\Services\QuestionImageImportService;

/**
 * Supported columns (heading row):
 * - grade (1-12)
 * - subject_en, subject_ur (optional)
 * - chapter (number)
 * - chapter_title_en, chapter_title_ur (optional)
 * - type: mcq|short|long|fill|truefalse
 * - source: exercise|additional|past_paper
 * - text_en, text_ur
 * - image_path (optional) [expects already-uploaded path under public storage]
 * - option_a_en, option_b_en, option_c_en, option_d_en, correct_option (mcq only)
 * - board_name, year, session (past_paper only)
 * - parent_key (optional): groups long parts under same parent (same parent_key rows)
 * - part_label (optional): a|b|c (if present row will be treated as part)
 */
class QuestionBankImport implements ToCollection, WithHeadingRow
{
    /** @param  array<string, string>  $imageMap  filename => storage path */
    public function __construct(
        protected array $imageMap = [],
        protected QuestionImageImportService $imageResolver = new QuestionImageImportService,
    ) {}

    public function collection(Collection $rows)
    {
        // Map parent_key => parent Question
        $parents = [];

        foreach ($rows as $row) {
            $gradeNumber = (int) ($row['grade'] ?? 0);
            if ($gradeNumber < 1 || $gradeNumber > 12) {
                continue;
            }

            $grade = Grade::query()->where('number', $gradeNumber)->first();
            if (! $grade) {
                $grade = Grade::create([
                    'number' => $gradeNumber,
                    'label_en' => "Class {$gradeNumber}",
                    'label_ur' => "جماعت {$gradeNumber}",
                ]);
            }

            $subjectEn = trim((string) ($row['subject_en'] ?? ''));
            if ($subjectEn === '') {
                continue;
            }

            $subject = Subject::query()->firstOrCreate(
                ['grade_id' => $grade->id, 'name_en' => $subjectEn],
                [
                    'name_ur' => $row['subject_ur'] ?? null,
                    'sort_order' => 0,
                ]
            );

            $chapterNumber = (int) ($row['chapter'] ?? 0);
            if ($chapterNumber <= 0) {
                continue;
            }

            $chapter = Chapter::query()->firstOrCreate(
                ['subject_id' => $subject->id, 'number' => $chapterNumber],
                [
                    'title_en' => $row['chapter_title_en'] ?? "{$subjectEn} Chapter {$chapterNumber}",
                    'title_ur' => $row['chapter_title_ur'] ?? null,
                ]
            );

            $type = (string) ($row['type'] ?? '');
            $source = (string) ($row['source'] ?? '');
            if (! in_array($type, ['mcq', 'short', 'long', 'fill', 'truefalse'], true)) {
                continue;
            }
            if (! in_array($source, ['exercise', 'additional', 'past_paper'], true)) {
                continue;
            }

            $parentKey = trim((string) ($row['parent_key'] ?? ''));
            $partLabel = strtolower(trim((string) ($row['part_label'] ?? '')));
            $isPart = $parentKey !== '' && $partLabel !== '';

            if (! $isPart) {
                $question = Question::create([
                    'chapter_id' => $chapter->id,
                    'type' => $type,
                    'source' => $source,
                    'text_en' => $row['text_en'] ?? null,
                    'text_ur' => $row['text_ur'] ?? null,
                    'image_path' => $this->resolveRowImage($row),
                    'has_parts' => $parentKey !== '' && $type === 'long',
                    'is_active' => true,
                ]);

                if ($parentKey !== '') {
                    $parents[$parentKey] = $question;
                }

                $this->maybeAttachMcqOptions($question, $row);
                $this->maybeAttachPastPaperTag($question, $row);
            } else {
                $parent = $parents[$parentKey] ?? null;
                if (! $parent) {
                    // If parent row appears later, skip part.
                    continue;
                }

                Question::create([
                    'chapter_id' => $parent->chapter_id,
                    'type' => $parent->type,
                    'source' => $parent->source,
                    'text_en' => $row['text_en'] ?? null,
                    'text_ur' => $row['text_ur'] ?? null,
                    'parent_question_id' => $parent->id,
                    'is_active' => true,
                ]);
            }
        }
    }

    protected function maybeAttachMcqOptions(Question $question, $row): void
    {
        if ($question->type !== 'mcq') {
            return;
        }

        $correct = strtolower(trim((string) ($row['correct_option'] ?? 'a')));
        if (! in_array($correct, ['a', 'b', 'c', 'd'], true)) {
            $correct = 'a';
        }

        McqOption::updateOrCreate(
            ['question_id' => $question->id],
            [
                'option_a_en' => (string) ($row['option_a_en'] ?? ''),
                'option_b_en' => (string) ($row['option_b_en'] ?? ''),
                'option_c_en' => (string) ($row['option_c_en'] ?? ''),
                'option_d_en' => (string) ($row['option_d_en'] ?? ''),
                'correct_option' => $correct,
            ]
        );
    }

    protected function maybeAttachPastPaperTag(Question $question, $row): void
    {
        if ($question->source !== 'past_paper') {
            return;
        }

        $board = trim((string) ($row['board_name'] ?? 'Unknown Board'));
        $year = (int) ($row['year'] ?? date('Y'));
        $session = strtolower(trim((string) ($row['session'] ?? '')));
        $session = in_array($session, ['morning', 'evening'], true) ? $session : null;

        PastPaperTag::updateOrCreate(
            ['question_id' => $question->id],
            [
                'board_name' => $board === '' ? 'Unknown Board' : $board,
                'year' => $year,
                'session' => $session,
            ]
        );
    }

    protected function resolveRowImage($row): ?string
    {
        $value = $row['image_path'] ?? $row['image_filename'] ?? null;

        return $this->imageResolver->resolveImagePath(
            $value !== null ? (string) $value : null,
            $this->imageMap,
        );
    }
}

