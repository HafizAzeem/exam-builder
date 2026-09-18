<?php

namespace App\Http\Requests\SuperAdmin;

use App\Support\CurriculumLookup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAIImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        $isPastPaper = $this->input('book_type') === 'past_paper';

        return [
            'grade_id' => CurriculumLookup::activeGradeId(),
            'subject_id' => CurriculumLookup::activeSubjectId($this->integer('grade_id')),
            'book_type' => ['required', 'in:text_book,past_paper,additional_questions'],
            'board' => CurriculumLookup::activeBoardName($isPastPaper),
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100', Rule::prohibitedIf(! $isPastPaper)],
            'session' => ['nullable', 'in:morning,evening', Rule::prohibitedIf(! $isPastPaper)],
            'language' => ['required', 'string', 'max:50'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,docx,txt'],
        ];
    }
}
