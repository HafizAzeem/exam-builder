<?php

namespace App\Http\Requests\PaperBuilder;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherAIQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'chapter_id' => ['required', 'integer'],
        ];
    }
}
