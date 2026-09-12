<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAISettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'model_name' => ['required', 'string', 'max:200'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:256', 'max:65536'],
            'prompt_template' => ['required', 'string'],
            'chunk_size' => ['required', 'integer', 'min:1000', 'max:50000'],
            'retry_count' => ['required', 'integer', 'min:1', 'max:10'],
            'enable_queue' => ['required', 'boolean'],
            'preferred_text_provider' => ['required', Rule::in(['gemini', 'openrouter', 'openai'])],
            'openrouter_model' => [
                Rule::requiredIf(fn () => $this->input('preferred_text_provider') === 'openrouter'),
                'nullable',
                'string',
                'max:200',
            ],
            'gemini_api_key' => ['nullable', 'string', 'max:500'],
            'openrouter_api_key' => ['nullable', 'string', 'max:500'],
            'openai_api_key' => ['nullable', 'string', 'max:500'],
            'google_search_api_key' => ['nullable', 'string', 'max:500'],
            'google_cse_id' => ['nullable', 'string', 'max:100'],
            'max_urls_per_search' => ['required', 'integer', 'min:1', 'max:20'],
            'max_pages_per_source' => ['required', 'integer', 'min:1', 'max:100'],
            'search_timeout' => ['required', 'integer', 'min:5', 'max:120'],
            'collector_retry_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'duplicate_similarity_threshold' => ['required', 'numeric', 'min:0.5', 'max:1'],
            'queue_size' => ['required', 'integer', 'min:1', 'max:20'],
            'max_source_bytes' => ['required', 'integer', 'min:100000', 'max:50000000'],
            'clear_gemini_api_key' => ['sometimes', 'boolean'],
            'clear_openrouter_api_key' => ['sometimes', 'boolean'],
            'clear_openai_api_key' => ['sometimes', 'boolean'],
            'clear_google_search_api_key' => ['sometimes', 'boolean'],
        ];
    }
}
