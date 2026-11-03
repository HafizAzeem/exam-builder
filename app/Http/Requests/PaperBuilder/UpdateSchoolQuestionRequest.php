<?php

namespace App\Http\Requests\PaperBuilder;

class UpdateSchoolQuestionRequest extends StoreSchoolQuestionRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'questions' => ['required', 'array', 'size:1'],
            'questions.*.parts' => ['sometimes', 'array', 'max:50'],
            'questions.*.parts.*.id' => ['required', 'integer', 'distinct'],
            'questions.*.parts.*.text_en' => ['nullable', 'string', 'max:5000'],
            'questions.*.parts.*.text_ur' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('questions') && $this->has('parts')) {
            $this->merge(['questions' => [array_merge($this->only([
                'chapter_id', 'type', 'source', 'board_name', 'text_en', 'text_ur', 'correct_answer', 'mcq', 'diagram',
            ]), ['parts' => $this->input('parts')])]]);
        }
        parent::prepareForValidation();
    }
}
