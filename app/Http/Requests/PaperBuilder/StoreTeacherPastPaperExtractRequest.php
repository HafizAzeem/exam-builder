<?php

namespace App\Http\Requests\PaperBuilder;

use App\Support\CurriculumLookup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherPastPaperExtractRequest extends FormRequest
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
            'board' => CurriculumLookup::activeBoardName(true),
            'year' => ['required', 'integer', 'min:1990', 'max:'.((int) date('Y') + 1)],
            'session' => ['nullable', 'in:morning,evening'],
            'paper_type' => ['nullable', 'in:objective,subjective,complete'],
            'language' => ['nullable', 'string', 'in:english,urdu,both'],
            'max_results' => ['nullable', 'integer', 'min:1', 'max:15'],
            'chapter_ids' => ['nullable', 'array'],
            'chapter_ids.*' => ['integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'paper_type' => $this->input('paper_type', 'complete'),
            'language' => $this->input('language', 'english'),
            'max_results' => $this->input('max_results', 8),
            'country' => 'Pakistan',
        ]);
    }
}
