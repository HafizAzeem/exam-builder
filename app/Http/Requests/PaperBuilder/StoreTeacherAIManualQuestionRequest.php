<?php

namespace App\Http\Requests\PaperBuilder;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherAIManualQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:mcq,short,long,fill,truefalse'],
            'chapter_id' => ['required', 'integer'],
            'text_en' => ['nullable', 'string', 'max:5000'],
            'text_ur' => ['nullable', 'string', 'max:5000'],
            'estimated_marks' => ['nullable', 'integer', 'min:1', 'max:20'],
            'mcq_options' => ['nullable', 'array'],
            'mcq_options.option_a_en' => ['nullable', 'string', 'max:1000'],
            'mcq_options.option_b_en' => ['nullable', 'string', 'max:1000'],
            'mcq_options.option_c_en' => ['nullable', 'string', 'max:1000'],
            'mcq_options.option_d_en' => ['nullable', 'string', 'max:1000'],
            'mcq_options.option_a_ur' => ['nullable', 'string', 'max:1000'],
            'mcq_options.option_b_ur' => ['nullable', 'string', 'max:1000'],
            'mcq_options.option_c_ur' => ['nullable', 'string', 'max:1000'],
            'mcq_options.option_d_ur' => ['nullable', 'string', 'max:1000'],
            'mcq_options.correct_option' => ['nullable', 'in:a,b,c,d'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('text_en') && ! $this->filled('text_ur')) {
                $validator->errors()->add('text_en', 'Enter question text in English or Urdu.');
            }

            if ($this->input('type') === 'mcq') {
                $opts = $this->input('mcq_options', []);
                $hasEn = ($opts['option_a_en'] ?? '') || ($opts['option_b_en'] ?? '');
                $hasUr = ($opts['option_a_ur'] ?? '') || ($opts['option_b_ur'] ?? '');
                if (! $hasEn && ! $hasUr) {
                    $validator->errors()->add('mcq_options', 'Add MCQ options.');
                }
            }
        });
    }
}
