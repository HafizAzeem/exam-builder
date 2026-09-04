<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Enums\Lab;

class AISetting extends Model
{
    protected $table = 'ai_settings';

    protected $fillable = [
        'model_name',
        'temperature',
        'max_tokens',
        'prompt_template',
        'chunk_size',
        'retry_count',
        'enable_queue',
        'gemini_api_key',
        'google_search_api_key',
        'google_cse_id',
        'openrouter_api_key',
        'openai_api_key',
        'preferred_text_provider',
        'openrouter_model',
        'max_urls_per_search',
        'max_pages_per_source',
        'search_timeout',
        'collector_retry_attempts',
        'duplicate_similarity_threshold',
        'queue_size',
        'max_source_bytes',
    ];

    protected $hidden = [
        'gemini_api_key',
        'google_search_api_key',
        'openrouter_api_key',
        'openai_api_key',
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'chunk_size' => 'integer',
        'retry_count' => 'integer',
        'enable_queue' => 'boolean',
        'gemini_api_key' => 'encrypted',
        'google_search_api_key' => 'encrypted',
        'openrouter_api_key' => 'encrypted',
        'openai_api_key' => 'encrypted',
        'max_urls_per_search' => 'integer',
        'max_pages_per_source' => 'integer',
        'search_timeout' => 'integer',
        'collector_retry_attempts' => 'integer',
        'duplicate_similarity_threshold' => 'float',
        'queue_size' => 'integer',
        'max_source_bytes' => 'integer',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'model_name' => 'gemini-2.5-flash',
            'temperature' => 0.2,
            'max_tokens' => 8192,
            'prompt_template' => static::defaultPromptTemplate(),
            'chunk_size' => 8000,
            'retry_count' => 3,
            'enable_queue' => true,
            'preferred_text_provider' => 'gemini',
            'max_urls_per_search' => 10,
            'max_pages_per_source' => 20,
            'search_timeout' => 30,
            'collector_retry_attempts' => 3,
            'duplicate_similarity_threshold' => 0.85,
            'queue_size' => 5,
            'max_source_bytes' => 15_000_000,
        ]);
    }

    /**
     * Push DB credentials into runtime config.
     * Super Admin AI Settings is the only source — clears env-based leftovers.
     */
    public function applyProviderConfig(): void
    {
        config([
            'ai.providers.gemini.key' => $this->gemini_api_key,
            'ai.providers.openrouter.key' => $this->openrouter_api_key,
            'ai.providers.openai.key' => $this->openai_api_key,
            'services.google_cse.key' => $this->google_search_api_key,
            'services.google_cse.cx' => $this->google_cse_id,
        ]);
    }

    public function resolvedTextProvider(): Lab
    {
        return match ($this->preferred_text_provider) {
            'openrouter' => Lab::OpenRouter,
            'openai' => Lab::OpenAI,
            default => Lab::Gemini,
        };
    }

    public function resolvedTextModel(): string
    {
        if ($this->preferred_text_provider === 'openrouter') {
            return $this->openrouter_model ?: $this->model_name;
        }

        return $this->model_name;
    }

    public function resolvedGeminiApiKey(): ?string
    {
        return filled($this->gemini_api_key) ? $this->gemini_api_key : null;
    }

    public function resolvedOpenRouterApiKey(): ?string
    {
        return filled($this->openrouter_api_key) ? $this->openrouter_api_key : null;
    }

    public function resolvedOpenAiApiKey(): ?string
    {
        return filled($this->openai_api_key) ? $this->openai_api_key : null;
    }

    public function resolvedGoogleSearchApiKey(): ?string
    {
        return filled($this->google_search_api_key) ? $this->google_search_api_key : null;
    }

    public function resolvedGoogleCseId(): ?string
    {
        return filled($this->google_cse_id) ? $this->google_cse_id : null;
    }

    public function isTextProviderConfigured(): bool
    {
        return match ($this->preferred_text_provider) {
            'openrouter' => filled($this->resolvedOpenRouterApiKey()),
            'openai' => filled($this->resolvedOpenAiApiKey()),
            default => filled($this->resolvedGeminiApiKey()),
        };
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'model_name' => $this->model_name,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'prompt_template' => $this->prompt_template,
            'chunk_size' => $this->chunk_size,
            'retry_count' => $this->retry_count,
            'enable_queue' => $this->enable_queue,
            'google_cse_id' => $this->google_cse_id,
            'preferred_text_provider' => $this->preferred_text_provider ?: 'gemini',
            'openrouter_model' => $this->openrouter_model,
            'max_urls_per_search' => $this->max_urls_per_search,
            'max_pages_per_source' => $this->max_pages_per_source,
            'search_timeout' => $this->search_timeout,
            'collector_retry_attempts' => $this->collector_retry_attempts,
            'duplicate_similarity_threshold' => $this->duplicate_similarity_threshold,
            'queue_size' => $this->queue_size,
            'max_source_bytes' => $this->max_source_bytes,
            'has_gemini_api_key' => filled($this->resolvedGeminiApiKey()),
            'has_openrouter_api_key' => filled($this->resolvedOpenRouterApiKey()),
            'has_openai_api_key' => filled($this->resolvedOpenAiApiKey()),
            'has_google_search_api_key' => filled($this->resolvedGoogleSearchApiKey()),
            'gemini_key_masked' => $this->maskSecret($this->resolvedGeminiApiKey()),
            'openrouter_key_masked' => $this->maskSecret($this->resolvedOpenRouterApiKey()),
            'openai_key_masked' => $this->maskSecret($this->resolvedOpenAiApiKey()),
            'google_search_key_masked' => $this->maskSecret($this->resolvedGoogleSearchApiKey()),
            'text_provider_configured' => $this->isTextProviderConfigured(),
        ];
    }

    protected function maskSecret(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4).str_repeat('*', max(4, $length - 8)).substr($value, -4);
    }

    public static function defaultPromptTemplate(): string
    {
        return <<<'PROMPT'
You are an expert exam-question extractor for Pakistani school textbooks and past papers.

Extract every question from the provided text chunk.

Rules:
- Return structured data only (no markdown, no explanations).
- Detect question type: mcq, short, long, fill, truefalse.
- Prefer bilingual text when both Urdu and English appear (text_en / text_ur).
- For MCQ include options a–d and correct_option when detectable.
- For long questions include parts when numbered sub-parts exist.
- Include chapter_number, chapter_title, and topic when available in the chunk.
PROMPT;
    }

    public static function pastPaperPromptTemplate(): string
    {
        return <<<'PROMPT'
You are an expert Pakistani board past-paper question extractor (BISE Lahore and related boards).

Extract ONLY exam questions from the provided past-paper content.

Strict rules:
- Return structured JSON only. No markdown. No explanations.
- Ignore advertisements, navigation, headers, footers, watermarks, page numbers, and answer keys unless answers are embedded as correct MCQ options.
- Detect question type: mcq, short, long, fill, truefalse.
- Prefer bilingual capture when both Urdu and English appear (text_en / text_ur / option_*_en / option_*_ur).
- For MCQ include options a–d and correct_option when clearly available.
- Preserve parent/part structure for multi-part questions (has_parts / parts).
- Classify chapter_title, topic, difficulty (easy|medium|hard), estimated_marks, and confidence_score (0-1) using curriculum context when provided.
- Always include board, year, session, grade, subject, and source when known from metadata.
PROMPT;
    }
}
