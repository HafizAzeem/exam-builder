<?php

namespace App\Http\Requests\SuperAdmin;

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
        return [
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('grade_id', $this->integer('grade_id'))),
            ],
            'book_type' => ['required', 'in:text_book,past_paper,additional_questions'],
            'board' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'session' => ['nullable', 'in:morning,evening'],
            'language' => ['required', 'string', 'max:50'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,docx,txt'],
        ];
    }
}
