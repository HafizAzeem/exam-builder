<?php

namespace App\Http\Requests\PaperBuilder;

use App\Support\CurriculumLookup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTeacherAIPasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isPastPaper = $this->input('book_type') === 'past_paper';
        $allowedGrades = config('exam.teacher_ai_grade_numbers', [5, 6, 7, 8, 9, 10, 11, 12]);

        return [
            'mode' => ['required', 'in:format,search,upload'],
            'request_key' => ['required', 'uuid'],
            'question_count' => ['required', 'integer', 'min:1', 'max:30'],
            'confirm_context' => ['sometimes', 'boolean'],
            'file' => ['required_if:mode,upload', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120', Rule::prohibitedIf($this->input('mode') !== 'upload')],
            'grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')->where(fn ($q) => $q->where('is_active', true)->whereIn('number', $allowedGrades)),
            ],
            'subject_id' => CurriculumLookup::activeSubjectId($this->integer('grade_id')),
            'chapter_ids' => ['required', 'array', 'min:1'],
            'chapter_ids.*' => ['integer'],
            'book_type' => ['required', 'in:text_book,past_paper,additional_questions'],
            'board' => CurriculumLookup::activeBoardName($isPastPaper),
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100', Rule::prohibitedIf(! $isPastPaper)],
            'session' => ['nullable', 'in:morning,evening', Rule::prohibitedIf(! $isPastPaper)],
            'language' => ['required', 'in:english,urdu,both'],
            'raw_text' => ['required_unless:mode,upload', 'nullable', 'string', 'max:20000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
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
        });
    }
}
