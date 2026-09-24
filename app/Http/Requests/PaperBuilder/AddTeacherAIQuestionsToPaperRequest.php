<?php

namespace App\Http\Requests\PaperBuilder;

use Illuminate\Foundation\Http\FormRequest;

class AddTeacherAIQuestionsToPaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'staging_ids' => ['required', 'array', 'min:1'],
            'staging_ids.*' => ['integer'],
        ];
    }
}
