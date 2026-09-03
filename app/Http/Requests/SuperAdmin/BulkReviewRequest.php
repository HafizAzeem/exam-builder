<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class BulkReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:ai_import_questions,id'],
            'action' => ['required', 'in:approve,reject,edit'],
            'chapter_id' => ['nullable', 'integer', 'exists:chapters,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'type' => ['nullable', 'in:mcq,short,long,fill,truefalse'],
            'source' => ['nullable', 'in:exercise,additional,past_paper'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ];
    }
}
