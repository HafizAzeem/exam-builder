<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAIImportPasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        $isPastPaper = $this->input('book_type') === 'past_paper';

        return [
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('grade_id', $this->integer('grade_id'))),
            ],
            'book_type' => ['required', 'in:text_book,past_paper,additional_questions'],
            'board' => [$isPastPaper ? 'required' : 'nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100', Rule::prohibitedIf(! $isPastPaper)],
            'session' => ['nullable', 'in:morning,evening', Rule::prohibitedIf(! $isPastPaper)],
            'language' => ['required', 'string', 'max:50'],
            'raw_text' => ['required', 'string', 'min:20', 'max:500000'],
        ];
    }

    public function messages(): array
    {
        return [
            'raw_text.required' => 'Paste question text into the box.',
            'raw_text.min' => 'Paste more text so AI can classify questions.',
        ];
    }
}
