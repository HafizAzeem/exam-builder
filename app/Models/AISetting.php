<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Enums\Lab;
use Throwable;

class AISetting extends Model
{
    protected $table = 'ai_settings';

    public const PROVIDER_GEMINI = 'gemini';

    public const PROVIDER_GROQ = 'groq';

    public const FALLBACK_NONE = 'none';

    protected $fillable = [
        'model_name',
        'temperature',
        'max_tokens',
        'prompt_template',
        'chunk_size',
        'retry_count',
        'enable_queue',
        'gemini_api_key',
        'gemini_enabled',
        'groq_enabled',
        'groq_api_key',
        'groq_model',
        'groq_vision_model',
        'langsearch_api_key',
        'searxng_url',
        'preferred_text_provider',
        'fallback_text_provider',
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
        'groq_api_key',
        'langsearch_api_key',
    ];

    protected $casts = [
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'chunk_size' => 'integer',
        'retry_count' => 'integer',
        'enable_queue' => 'boolean',
        'gemini_api_key' => 'encrypted',
        'gemini_enabled' => 'boolean',
        'groq_enabled' => 'boolean',
        'groq_api_key' => 'encrypted',
        'langsearch_api_key' => 'encrypted',
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
        $defaults = [
            'model_name' => 'gemini-3.5-flash-lite',
            'temperature' => 0.2,
            'max_tokens' => 8192,
            'prompt_template' => static::defaultPromptTemplate(),
            'chunk_size' => 8000,
            'retry_count' => 3,
            'enable_queue' => true,
            'preferred_text_provider' => self::PROVIDER_GROQ,
            'max_urls_per_search' => 10,
            'max_pages_per_source' => 20,
            'search_timeout' => 30,
            'collector_retry_attempts' => 3,
            'duplicate_similarity_threshold' => 0.85,
            'queue_size' => 5,
            'max_source_bytes' => 15_000_000,
        ];

        if (Schema::hasColumn('ai_settings', 'gemini_enabled')) {
            $defaults['gemini_enabled'] = true;
        }
        if (Schema::hasColumn('ai_settings', 'groq_enabled')) {
            $defaults['groq_enabled'] = true;
            $defaults['groq_model'] = 'openai/gpt-oss-20b';
            $defaults['groq_vision_model'] = 'qwen/qwen3.8-27b';
        }
        if (Schema::hasColumn('ai_settings', 'fallback_text_provider')) {
            $defaults['fallback_text_provider'] = self::PROVIDER_GEMINI;
        }

        return static::query()->firstOrCreate([], $defaults);
    }

    /**
     * Push DB credentials into runtime config.
     * Super Admin AI Settings is the only source — clears env-based leftovers.
     */
    public function applyProviderConfig(): void
    {
        config([
            'ai.providers.gemini.key' => $this->gemini_api_key,
            'ai.providers.groq.key' => $this->groq_api_key,
            'ai.providers.groq.url' => rtrim((string) config('ai.providers.groq.url', 'https://api.groq.com/openai/v1'), '/'),
            'ai.web_search.langsearch_key' => $this->langsearch_api_key,
            'ai.web_search.searxng_url' => $this->resolvedSearxngUrl(),
            'ai.providers.openrouter.key' => null,
            'ai.providers.openai.key' => null,
            'services.google_cse.key' => null,
            'services.google_cse.cx' => null,
        ]);
    }

    public function resolvedGeminiApiKey(): ?string
    {
        return filled($this->gemini_api_key) ? $this->gemini_api_key : null;
    }

    public function resolvedGroqApiKey(): ?string
    {
        return filled($this->groq_api_key) ? $this->groq_api_key : null;
    }

    public function resolvedLangsearchApiKey(): ?string
    {
        if (! Schema::hasColumn('ai_settings', 'langsearch_api_key')) {
            return null;
        }

        return filled($this->langsearch_api_key) ? $this->langsearch_api_key : null;
    }

    public function resolvedSearxngUrl(): ?string
    {
        if (! Schema::hasColumn('ai_settings', 'searxng_url')) {
            return null;
        }

        $url = rtrim(trim((string) ($this->searxng_url ?: '')), '/');

        return $url !== '' ? $url : null;
    }

    public function isFreeWebSearchConfigured(): bool
    {
        return filled($this->resolvedLangsearchApiKey()) || filled($this->resolvedSearxngUrl());
    }

    public function resolvedFreeSearchProvider(): ?string
    {
        if (filled($this->resolvedLangsearchApiKey())) {
            return 'langsearch';
        }
        if (filled($this->resolvedSearxngUrl())) {
            return 'searxng';
        }

        return null;
    }

    public function resolvedGroqModel(): string
    {
        $model = trim((string) ($this->groq_model ?: 'openai/gpt-oss-20b'));

        return $model !== '' ? $model : 'openai/gpt-oss-20b';
    }

    public function resolvedGroqVisionModel(): string
    {
        $model = trim((string) ($this->groq_vision_model ?: 'qwen/qwen3.8-27b'));

        return $model !== '' ? $model : 'qwen/qwen3.8-27b';
    }

    public function primaryTextProvider(): string
    {
        $primary = strtolower((string) ($this->preferred_text_provider ?: self::PROVIDER_GROQ));

        return in_array($primary, [self::PROVIDER_GEMINI, self::PROVIDER_GROQ], true)
            ? $primary
            : self::PROVIDER_GROQ;
    }

    public function fallbackTextProvider(): string
    {
        $raw = Schema::hasColumn('ai_settings', 'fallback_text_provider')
            ? $this->fallback_text_provider
            : self::PROVIDER_GEMINI;

        $fallback = strtolower(trim((string) ($raw ?: self::PROVIDER_GEMINI)));
        if ($fallback === '') {
            $fallback = self::PROVIDER_GEMINI;
        }

        if (! in_array($fallback, [self::PROVIDER_GEMINI, self::PROVIDER_GROQ, self::FALLBACK_NONE], true)) {
            $fallback = self::PROVIDER_GEMINI;
        }

        if ($fallback !== self::FALLBACK_NONE && $fallback === $this->primaryTextProvider()) {
            return self::FALLBACK_NONE;
        }

        return $fallback;
    }

    public function isProviderEnabled(string $id): bool
    {
        return match ($id) {
            self::PROVIDER_GEMINI => (bool) ($this->gemini_enabled ?? true),
            self::PROVIDER_GROQ => (bool) ($this->groq_enabled ?? true),
            default => false,
        };
    }

    public function isProviderUsable(string $id): bool
    {
        if (! $this->isProviderEnabled($id)) {
            return false;
        }

        return match ($id) {
            self::PROVIDER_GEMINI => filled($this->resolvedGeminiApiKey()),
            self::PROVIDER_GROQ => filled($this->resolvedGroqApiKey()),
            default => false,
        };
    }

    public function isGeminiUsable(): bool
    {
        return $this->isProviderUsable(self::PROVIDER_GEMINI);
    }

    public function isGroqUsable(): bool
    {
        return $this->isProviderUsable(self::PROVIDER_GROQ);
    }

    /**
     * @return self::PROVIDER_GEMINI|self::PROVIDER_GROQ
     */
    public function resolveActiveTextProvider(): string
    {
        $primary = $this->primaryTextProvider();
        if ($this->isProviderUsable($primary)) {
            return $primary;
        }

        $fallback = $this->fallbackTextProvider();
        if ($fallback !== self::FALLBACK_NONE && $this->isProviderUsable($fallback)) {
            return $fallback;
        }

        throw new \RuntimeException(
            'No AI text provider available. Enable the primary or fallback provider and add its API key in AI Settings.'
        );
    }

    public function tryResolveActiveTextProvider(): ?string
    {
        try {
            return $this->resolveActiveTextProvider();
        } catch (Throwable) {
            return null;
        }
    }

    public function resolvedTextProvider(): Lab
    {
        return Lab::Gemini;
    }

    public function resolvedTextModel(): string
    {
        $model = $this->model_name ?: 'gemini-3.5-flash-lite';

        return static::migrateDeprecatedModel($model);
    }

    /**
     * Map shut-down / new-user-blocked model IDs to current Gemini 3.x models.
     */
    public static function migrateDeprecatedModel(string $model): string
    {
        return match ($model) {
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-2.0-flash',
            'gemini-2.0-flash-001',
            'gemini-2.0-flash-lite',
            'gemini-2.0-flash-lite-001',
            'gemini-1.5-flash',
            'gemini-1.5-flash-001',
            'gemini-1.5-flash-002' => 'gemini-3.5-flash-lite',
            'gemini-2.5-pro',
            'gemini-1.5-pro',
            'gemini-1.5-pro-001',
            'gemini-1.5-pro-002' => 'gemini-3.5-flash',
            default => $model,
        };
    }

    public function isTextProviderConfigured(): bool
    {
        return $this->tryResolveActiveTextProvider() !== null;
    }

    public function toPublicArray(): array
    {
        $active = $this->tryResolveActiveTextProvider();

        return [
            'id' => $this->id,
            'model_name' => $this->model_name,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'prompt_template' => $this->prompt_template,
            'chunk_size' => $this->chunk_size,
            'retry_count' => $this->retry_count,
            'enable_queue' => $this->enable_queue,
            'preferred_text_provider' => $this->primaryTextProvider(),
            'fallback_text_provider' => $this->fallbackTextProvider(),
            'gemini_enabled' => $this->isProviderEnabled(self::PROVIDER_GEMINI),
            'groq_enabled' => $this->isProviderEnabled(self::PROVIDER_GROQ),
            'groq_model' => $this->resolvedGroqModel(),
            'groq_vision_model' => $this->resolvedGroqVisionModel(),
            'max_urls_per_search' => $this->max_urls_per_search,
            'max_pages_per_source' => $this->max_pages_per_source,
            'search_timeout' => $this->search_timeout,
            'collector_retry_attempts' => $this->collector_retry_attempts,
            'duplicate_similarity_threshold' => $this->duplicate_similarity_threshold,
            'queue_size' => $this->queue_size,
            'max_source_bytes' => $this->max_source_bytes,
            'has_gemini_api_key' => filled($this->resolvedGeminiApiKey()),
            'gemini_key_masked' => $this->maskSecret($this->resolvedGeminiApiKey()),
            'has_groq_api_key' => filled($this->resolvedGroqApiKey()),
            'groq_key_masked' => $this->maskSecret($this->resolvedGroqApiKey()),
            'has_langsearch_api_key' => filled($this->resolvedLangsearchApiKey()),
            'langsearch_key_masked' => $this->maskSecret($this->resolvedLangsearchApiKey()),
            'searxng_url' => $this->resolvedSearxngUrl(),
            'free_search_provider' => $this->resolvedFreeSearchProvider(),
            'text_provider_configured' => $this->isTextProviderConfigured(),
            'active_text_provider' => $active,
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
