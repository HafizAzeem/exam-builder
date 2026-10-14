<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\AISetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAISettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['gemini_enabled', 'groq_enabled', 'enable_queue', 'clear_gemini_api_key', 'clear_groq_api_key', 'clear_langsearch_api_key'] as $field) {
            if ($this->exists($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
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
            'gemini_api_key' => ['nullable', 'string', 'max:500'],
            'gemini_enabled' => ['required', 'boolean'],
            'groq_enabled' => ['required', 'boolean'],
            'groq_api_key' => ['nullable', 'string', 'max:500'],
            'groq_model' => ['nullable', 'string', 'max:150'],
            'groq_vision_model' => ['nullable', 'string', 'max:150'],
            'preferred_text_provider' => ['required', 'in:gemini,groq'],
            'fallback_text_provider' => ['required', 'in:gemini,groq,none'],
            'max_urls_per_search' => ['required', 'integer', 'min:1', 'max:20'],
            'max_pages_per_source' => ['required', 'integer', 'min:1', 'max:100'],
            'search_timeout' => ['required', 'integer', 'min:5', 'max:120'],
            'collector_retry_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'duplicate_similarity_threshold' => ['required', 'numeric', 'min:0.5', 'max:1'],
            'queue_size' => ['required', 'integer', 'min:1', 'max:20'],
            'max_source_bytes' => ['required', 'integer', 'min:100000', 'max:50000000'],
            'langsearch_api_key' => ['nullable', 'string', 'max:500'],
            'searxng_url' => ['nullable', 'string', 'max:255'],
            'clear_gemini_api_key' => ['sometimes', 'boolean'],
            'clear_groq_api_key' => ['sometimes', 'boolean'],
            'clear_langsearch_api_key' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $primary = (string) $this->input('preferred_text_provider');
            $fallback = (string) $this->input('fallback_text_provider');

            if ($fallback !== AISetting::FALLBACK_NONE && $fallback === $primary) {
                $validator->errors()->add(
                    'fallback_text_provider',
                    'Fallback provider must be different from the primary provider (or None).'
                );
            }
        });
    }
}
