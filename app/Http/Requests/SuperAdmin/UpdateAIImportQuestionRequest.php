<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAIImportQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'type' => ['sometimes', 'in:mcq,short,long,fill,truefalse'],
            'source' => ['sometimes', 'in:exercise,additional,past_paper'],
            'text_en' => ['nullable', 'string'],
            'text_ur' => ['nullable', 'string'],
            'mcq_options' => ['nullable', 'array'],
            'mcq_options.option_a_en' => ['nullable', 'string'],
            'mcq_options.option_b_en' => ['nullable', 'string'],
            'mcq_options.option_c_en' => ['nullable', 'string'],
            'mcq_options.option_d_en' => ['nullable', 'string'],
            'mcq_options.correct_option' => ['nullable', 'in:a,b,c,d'],
            'parts' => ['nullable', 'array'],
            'review_notes' => ['nullable', 'string'],
            'match_status' => ['sometimes', 'in:matched,unmatched_chapter,unmatched_topic,manual'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'estimated_marks' => ['nullable', 'integer', 'min:1', 'max:100'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ];
    }
}
