<?php

namespace App\Http\Requests\PaperBuilder;

use App\Support\CurriculumLookup;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchoolQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->institution_id
            && $user->hasAnyRole(['teacher', 'institution_admin']);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('questions')) {
            $this->merge([
                'questions' => [[
                    'chapter_id' => $this->input('chapter_id'),
                    'type' => $this->input('type'),
                    'text_en' => $this->input('text_en'),
                    'text_ur' => $this->input('text_ur'),
                    'correct_answer' => $this->input('correct_answer'),
                    'mcq' => $this->input('mcq'),
                ]],
            ]);
        }

        $rows = collect($this->input('questions', []))->map(function ($row) {
            if (($row['correct_answer'] ?? '') === '') {
                $row['correct_answer'] = null;
            }

            return $row;
        })->all();

        $this->merge(['questions' => $rows]);
    }

    public function rules(): array
    {
        return [
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.chapter_id' => CurriculumLookup::activeChapterId(),
            'questions.*.type' => ['required', 'in:mcq,short,long,fill,truefalse'],
            'questions.*.text_en' => ['nullable', 'string', 'max:5000'],
            'questions.*.text_ur' => ['nullable', 'string', 'max:5000'],
            'questions.*.correct_answer' => ['nullable', 'in:true,false'],
            'questions.*.mcq' => ['nullable', 'array'],
            'questions.*.mcq.option_a_en' => ['nullable', 'string', 'max:1000'],
            'questions.*.mcq.option_b_en' => ['nullable', 'string', 'max:1000'],
            'questions.*.mcq.option_c_en' => ['nullable', 'string', 'max:1000'],
            'questions.*.mcq.option_d_en' => ['nullable', 'string', 'max:1000'],
            'questions.*.mcq.correct_option' => ['nullable', 'in:a,b,c,d'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('questions', []) as $i => $row) {
                if (! filled($row['text_en'] ?? null) && ! filled($row['text_ur'] ?? null)) {
                    $validator->errors()->add("questions.$i.text_en", 'Enter question text in English or Urdu.');
                }

                if (($row['type'] ?? null) === 'mcq') {
                    $opts = $row['mcq'] ?? [];
                    $hasEn = ($opts['option_a_en'] ?? '') || ($opts['option_b_en'] ?? '');
                    if (! $hasEn) {
                        $validator->errors()->add("questions.$i.mcq", 'Add MCQ options.');
                    }
                }
            }
        });
    }
}
