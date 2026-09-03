<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePastPaperCollectionRequest extends FormRequest
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
            'board' => ['required', 'string', 'in:Lahore Board'],
            'year' => ['required', 'integer', 'min:1990', 'max:'.((int) date('Y') + 1)],
            'session' => ['nullable', 'in:morning,evening'],
            'paper_type' => ['nullable', 'in:objective,subjective,complete'],
            'language' => ['nullable', 'string', 'max:50'],
            'max_results' => ['nullable', 'integer', 'min:1', 'max:20'],
            'keywords_override' => ['nullable', 'string', 'max:500'],
            'country' => ['nullable', 'in:Pakistan'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'board' => $this->input('board', 'Lahore Board'),
            'country' => 'Pakistan',
            'paper_type' => $this->input('paper_type', 'complete'),
            'language' => $this->input('language', 'english'),
        ]);
    }
}
