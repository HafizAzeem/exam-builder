<?php

namespace App\Services;

use App\Models\McqOption;
use App\Models\PastPaperTag;
use App\Models\Question;
use App\Support\CurriculumLookup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuestionJsonImportService
{
    /**
     * Parse and validate pasted JSON without writing to the database.
     *
     * @return array{ok: bool, error?: string, meta?: array<string, mixed>, rows?: list<array<string, mixed>>, valid_count?: int, error_count?: int}
     */
    public function preview(string $rawJson, ?int $defaultGradeId = null, ?int $defaultSubjectId = null): array
    {
        $payload = $this->decodeJson($rawJson);

        if ($payload === null) {
            return ['ok' => false, 'error' => 'Invalid JSON. Paste a valid JSON object or array.'];
        }

        [$meta, $questions] = $this->normalizePayload($payload, $defaultGradeId, $defaultSubjectId);

        if ($questions === []) {
            return ['ok' => false, 'error' => 'No questions found. Expect { "questions": [ ... ] } or a JSON array.'];
        }

        $grade = null;
        $subject = null;

        if (! empty($meta['grade_id'])) {
            $grade = CurriculumLookup::grades()->find($meta['grade_id']);
        } elseif (isset($meta['grade'])) {
            $grade = CurriculumLookup::grades()->where('number', (int) $meta['grade'])->first();
        }

        if (! empty($meta['subject_id'])) {
            $subject = CurriculumLookup::subjects($grade?->id)->find($meta['subject_id']);
        } elseif ($grade && ! empty($meta['subject_en'])) {
            $subject = CurriculumLookup::subjects($grade->id)
                ->whereRaw('LOWER(name_en) = ?', [Str::lower(trim($meta['subject_en']))])
                ->first();
        }

        if (! $grade) {
            return ['ok' => false, 'error' => 'Could not resolve grade. Set grade / grade_id in JSON or select Grade on the form.'];
        }

        if (! $subject || (int) $subject->grade_id !== (int) $grade->id) {
            return ['ok' => false, 'error' => 'Could not resolve subject for the selected grade. Set subject_en / subject_id or select Subject.'];
        }

        $chapters = CurriculumLookup::chapters($subject->id)
            ->get(['id', 'number', 'title_en', 'title_ur']);

        $topicsByChapter = CurriculumLookup::topics()
            ->whereIn('chapter_id', $chapters->pluck('id'))
            ->get(['id', 'chapter_id', 'code', 'title_en', 'title_ur'])
            ->groupBy('chapter_id');

        $defaultSource = $this->normalizeSource($meta['source'] ?? 'additional') ?? 'additional';
        $rows = [];
        $validCount = 0;
        $errorCount = 0;

        foreach ($questions as $index => $raw) {
            $row = $this->normalizeQuestionRow($raw, $index, $defaultSource, $chapters, $topicsByChapter);
            if ($row['valid']) {
                $validCount++;
            } else {
                $errorCount++;
            }
            $rows[] = $row;
        }

        return [
            'ok' => true,
            'meta' => [
                'grade_id' => $grade->id,
                'grade_label' => $grade->label_en,
                'subject_id' => $subject->id,
                'subject_name' => $subject->name_en,
                'default_source' => $defaultSource,
            ],
            'rows' => $rows,
            'valid_count' => $validCount,
            'error_count' => $errorCount,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int}
     */
    public function save(array $rows): array
    {
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$imported, &$skipped) {
            foreach ($rows as $row) {
                if (empty($row['include']) || empty($row['valid']) || empty($row['chapter_id'])) {
                    $skipped++;

                    continue;
                }

                $type = $row['type'];
                $parts = is_array($row['parts'] ?? null) ? $row['parts'] : [];

                $question = Question::create([
                    'chapter_id' => $row['chapter_id'],
                    'topic_id' => $row['topic_id'] ?? null,
                    'type' => $type,
                    'source' => $row['source'],
                    'text_en' => $row['text_en'] ?? null,
                    'text_ur' => $row['text_ur'] ?? null,
                    'has_parts' => count($parts) > 0,
                    'is_active' => true,
                    'correct_answer' => $type === 'truefalse' ? ($row['correct_answer'] ?? null) : null,
                ]);

                if ($type === 'mcq' && is_array($row['mcq'] ?? null)) {
                    $mcq = $row['mcq'];
                    McqOption::create([
                        'question_id' => $question->id,
                        'option_a_en' => $mcq['option_a_en'] ?? '',
                        'option_a_ur' => $mcq['option_a_ur'] ?? null,
                        'option_b_en' => $mcq['option_b_en'] ?? '',
                        'option_b_ur' => $mcq['option_b_ur'] ?? null,
                        'option_c_en' => $mcq['option_c_en'] ?? '',
                        'option_c_ur' => $mcq['option_c_ur'] ?? null,
                        'option_d_en' => $mcq['option_d_en'] ?? '',
                        'option_d_ur' => $mcq['option_d_ur'] ?? null,
                        'correct_option' => $mcq['correct_option'] ?? 'a',
                    ]);
                }

                if (($row['source'] ?? null) === 'past_paper' && is_array($row['past'] ?? null)) {
                    PastPaperTag::create([
                        'question_id' => $question->id,
                        'board_name' => $row['past']['board_name'] ?: CurriculumLookup::defaultBoardName(),
                        'year' => (int) ($row['past']['year'] ?: date('Y')),
                        'session' => $row['past']['session'] ?? null,
                    ]);
                }

                foreach ($parts as $part) {
                    if (! is_array($part)) {
                        continue;
                    }
                    Question::create([
                        'chapter_id' => $question->chapter_id,
                        'topic_id' => $question->topic_id,
                        'type' => $question->type,
                        'source' => $question->source,
                        'text_en' => $part['text_en'] ?? null,
                        'text_ur' => $part['text_ur'] ?? null,
                        'parent_question_id' => $question->id,
                        'is_active' => true,
                    ]);
                }

                $imported++;
            }
        });

        return compact('imported', 'skipped');
    }

    protected function decodeJson(string $raw): mixed
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
            $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    protected function normalizePayload(mixed $payload, ?int $defaultGradeId, ?int $defaultSubjectId): array
    {
        if (array_is_list($payload)) {
            return [
                [
                    'grade_id' => $defaultGradeId,
                    'subject_id' => $defaultSubjectId,
                    'source' => 'additional',
                ],
                $payload,
            ];
        }

        if (! is_array($payload)) {
            return [[], []];
        }

        $questions = $payload['questions'] ?? [];
        if (! is_array($questions)) {
            $questions = [];
        }

        return [
            [
                'grade' => $payload['grade'] ?? null,
                'grade_id' => $payload['grade_id'] ?? $defaultGradeId,
                'subject_en' => $payload['subject_en'] ?? $payload['subject'] ?? null,
                'subject_id' => $payload['subject_id'] ?? $defaultSubjectId,
                'source' => $payload['source'] ?? 'additional',
            ],
            array_values(array_filter($questions, 'is_array')),
        ];
    }

    /**
     * @param  Collection<int, Chapter>  $chapters
     * @param  Collection<int, Collection<int, Topic>>  $topicsByChapter
     * @return array<string, mixed>
     */
    protected function normalizeQuestionRow(
        array $raw,
        int $index,
        string $defaultSource,
        $chapters,
        $topicsByChapter,
    ): array {
        $errors = [];
        $type = $this->normalizeType($raw['type'] ?? null);
        if (! $type) {
            $errors[] = 'Invalid or missing type (mcq, short, long, fill, truefalse).';
        }

        $source = $this->normalizeSource($raw['source'] ?? $defaultSource) ?? $defaultSource;
        $textEn = $this->nullableString($raw['text_en'] ?? null);
        $textUr = $this->nullableString($raw['text_ur'] ?? null);
        if (! $textEn && ! $textUr) {
            $errors[] = 'Question text (text_en or text_ur) is required.';
        }

        $chapterNumber = isset($raw['chapter']) ? (int) $raw['chapter'] : (isset($raw['chapter_number']) ? (int) $raw['chapter_number'] : null);
        $chapterTitle = $this->nullableString($raw['chapter_title_en'] ?? $raw['chapter_title'] ?? null);
        $chapter = $this->findChapter($chapters, $chapterNumber, $chapterTitle);
        if (! $chapter) {
            $errors[] = 'Chapter not matched. Set chapter number that exists for this subject.';
        }

        $topicId = null;
        $topicTitle = $this->nullableString($raw['topic'] ?? $raw['topic_title'] ?? null);
        if ($chapter && $topicTitle) {
            $topic = ($topicsByChapter->get($chapter->id) ?? collect())->first(function (Topic $t) use ($topicTitle) {
                $needle = Str::lower($topicTitle);

                return Str::lower($t->title_en) === $needle
                    || ($t->title_ur && Str::lower($t->title_ur) === $needle)
                    || Str::lower($t->code) === $needle;
            });
            $topicId = $topic?->id;
        }

        $mcq = null;
        $correctAnswer = null;
        $answerLabel = null;

        if ($type === 'mcq') {
            $mcq = $this->normalizeMcq($raw);
            if (! $mcq) {
                $errors[] = 'MCQ requires options a–d.';
            } else {
                $answerLabel = 'Option '.strtoupper($mcq['correct_option']);
            }
        }

        if ($type === 'truefalse') {
            $correctAnswer = $this->normalizeTrueFalseAnswer(
                $raw['correct_answer'] ?? $raw['answer'] ?? $raw['correct'] ?? null
            );
            if ($correctAnswer) {
                $answerLabel = $correctAnswer === 'true' ? 'True' : 'False';
            }
        }

        $parts = null;
        if ($type === 'long' && ! empty($raw['parts']) && is_array($raw['parts'])) {
            $parts = [];
            foreach ($raw['parts'] as $part) {
                if (! is_array($part)) {
                    continue;
                }
                $parts[] = [
                    'text_en' => $this->nullableString($part['text_en'] ?? null),
                    'text_ur' => $this->nullableString($part['text_ur'] ?? null),
                ];
            }
            $parts = $parts ?: null;
        }

        $past = null;
        if ($source === 'past_paper') {
            $pastRaw = is_array($raw['past_paper'] ?? null) ? $raw['past_paper'] : $raw;
            $past = [
                'board_name' => $this->nullableString($pastRaw['board_name'] ?? $pastRaw['board'] ?? null) ?: CurriculumLookup::defaultBoardName(),
                'year' => isset($pastRaw['year']) ? (int) $pastRaw['year'] : null,
                'session' => in_array(($pastRaw['session'] ?? null), ['morning', 'evening'], true)
                    ? $pastRaw['session']
                    : null,
            ];
        }

        $valid = $errors === [];

        return [
            'index' => $index,
            'include' => $valid,
            'valid' => $valid,
            'errors' => $errors,
            'type' => $type,
            'source' => $source,
            'text_en' => $textEn,
            'text_ur' => $textUr,
            'chapter_id' => $chapter?->id,
            'chapter_label' => $chapter ? "Ch {$chapter->number}: {$chapter->title_en}" : ($chapterTitle ?: ($chapterNumber ? "Ch {$chapterNumber}" : '—')),
            'topic_id' => $topicId,
            'topic_title' => $topicTitle,
            'mcq' => $mcq,
            'correct_answer' => $correctAnswer,
            'answer_label' => $answerLabel,
            'parts' => $parts,
            'past' => $past,
        ];
    }

    protected function findChapter($chapters, ?int $number, ?string $title): ?Chapter
    {
        if ($number) {
            $byNumber = $chapters->firstWhere('number', $number);
            if ($byNumber) {
                return $byNumber;
            }
        }

        if (! $title) {
            return null;
        }

        $needle = Str::lower(trim($title));

        return $chapters->first(function (Chapter $c) use ($needle) {
            return Str::lower($c->title_en) === $needle
                || ($c->title_ur && Str::lower($c->title_ur) === $needle)
                || str_contains(Str::lower($c->title_en), $needle);
        });
    }

    protected function normalizeMcq(array $raw): ?array
    {
        $options = is_array($raw['options'] ?? null) ? $raw['options'] : $raw;
        $mcq = is_array($raw['mcq'] ?? null) ? $raw['mcq'] : null;

        $a = $this->nullableString($mcq['option_a_en'] ?? $options['option_a_en'] ?? $options['a_en'] ?? $options['a'] ?? null);
        $b = $this->nullableString($mcq['option_b_en'] ?? $options['option_b_en'] ?? $options['b_en'] ?? $options['b'] ?? null);
        $c = $this->nullableString($mcq['option_c_en'] ?? $options['option_c_en'] ?? $options['c_en'] ?? $options['c'] ?? null);
        $d = $this->nullableString($mcq['option_d_en'] ?? $options['option_d_en'] ?? $options['d_en'] ?? $options['d'] ?? null);

        if (! $a || ! $b || ! $c || ! $d) {
            return null;
        }

        $correct = strtolower((string) (
            $mcq['correct_option']
            ?? $options['correct_option']
            ?? $options['correct']
            ?? $raw['correct_option']
            ?? $raw['answer']
            ?? 'a'
        ));
        $correct = preg_replace('/^option\s*/i', '', $correct) ?? $correct;
        if (! in_array($correct, ['a', 'b', 'c', 'd'], true)) {
            $correct = 'a';
        }

        return [
            'option_a_en' => $a,
            'option_a_ur' => $this->nullableString($mcq['option_a_ur'] ?? $options['a_ur'] ?? null),
            'option_b_en' => $b,
            'option_b_ur' => $this->nullableString($mcq['option_b_ur'] ?? $options['b_ur'] ?? null),
            'option_c_en' => $c,
            'option_c_ur' => $this->nullableString($mcq['option_c_ur'] ?? $options['c_ur'] ?? null),
            'option_d_en' => $d,
            'option_d_ur' => $this->nullableString($mcq['option_d_ur'] ?? $options['d_ur'] ?? null),
            'correct_option' => $correct,
        ];
    }

    protected function normalizeTrueFalseAnswer(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $v = Str::lower(trim((string) $value));
        if (in_array($v, ['true', 't', 'yes', '1'], true)) {
            return 'true';
        }
        if (in_array($v, ['false', 'f', 'no', '0'], true)) {
            return 'false';
        }

        return null;
    }

    protected function normalizeType(mixed $type): ?string
    {
        $type = Str::lower(trim((string) $type));
        $map = [
            'mcq' => 'mcq',
            'multiple choice' => 'mcq',
            'short' => 'short',
            'short answer' => 'short',
            'long' => 'long',
            'long answer' => 'long',
            'fill' => 'fill',
            'fill in blank' => 'fill',
            'fill in the blank' => 'fill',
            'truefalse' => 'truefalse',
            'true/false' => 'truefalse',
            'true false' => 'truefalse',
            'true_false' => 'truefalse',
        ];

        return $map[$type] ?? null;
    }

    protected function normalizeSource(mixed $source): ?string
    {
        $source = Str::lower(trim((string) $source));
        $map = [
            'exercise' => 'exercise',
            'textbook' => 'exercise',
            'text_book' => 'exercise',
            'additional' => 'additional',
            'additional_questions' => 'additional',
            'past_paper' => 'past_paper',
            'past paper' => 'past_paper',
        ];

        return $map[$source] ?? null;
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
