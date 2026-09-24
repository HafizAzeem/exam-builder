<?php

namespace App\Http\Requests\PaperBuilder;

use App\Support\CurriculumLookup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTeacherAIGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $allowedGrades = config('exam.teacher_ai_grade_numbers', [9, 10, 11, 12]);

        return [
            'grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')->where(fn ($q) => $q->where('is_active', true)->whereIn('number', $allowedGrades)),
            ],
            'subject_id' => CurriculumLookup::activeSubjectId($this->integer('grade_id')),
            'chapter_ids' => ['required', 'array', 'min:1'],
            'chapter_ids.*' => ['integer'],
            'content_source' => ['required', 'in:exercise,past_paper,online_practice'],
            'book_type' => ['nullable', 'in:text_book,past_paper,additional_questions'],
            'board' => ['nullable', 'string', 'max:100'],
            'language' => ['required', 'string', 'in:english,urdu,both'],
            'counts' => ['required', 'array'],
            'counts.mcq' => ['nullable', 'integer', 'min:0', 'max:50'],
            'counts.short' => ['nullable', 'integer', 'min:0', 'max:50'],
            'counts.long' => ['nullable', 'integer', 'min:0', 'max:20'],
            'counts.fill' => ['nullable', 'integer', 'min:0', 'max:30'],
            'counts.truefalse' => ['nullable', 'integer', 'min:0', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $counts = $this->input('counts', []);
            $total = (int) ($counts['mcq'] ?? 0)
                + (int) ($counts['short'] ?? 0)
                + (int) ($counts['long'] ?? 0)
                + (int) ($counts['fill'] ?? 0)
                + (int) ($counts['truefalse'] ?? 0);

            if ($total < 1) {
                $validator->errors()->add('counts', 'Request at least one question.');
            }

            if ($total > 60) {
                $validator->errors()->add('counts', 'Request at most 60 questions at a time.');
            }

            $subjectId = $this->integer('subject_id');
            $chapterIds = collect($this->input('chapter_ids', []))->map(fn ($id) => (int) $id)->filter()->all();

            if (! $subjectId || ! $chapterIds) {
                return;
            }

            $valid = CurriculumLookup::chapters($subjectId)
                ->whereIn('id', $chapterIds)
                ->pluck('id')
                ->all();

            if (count($valid) !== count(array_unique($chapterIds))) {
                $validator->errors()->add('chapter_ids', 'One or more chapters are invalid for this subject.');
            }

            $board = $this->input('board');
            if ($board && ! CurriculumLookup::boards()->where('name', $board)->exists()) {
                $validator->errors()->add('board', 'Selected board is invalid.');
            }
        });
    }
}
