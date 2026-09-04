<?php

namespace App\Services\PastPaperCollector\Providers;

use App\Ai\Agents\PastPaperExtractionAgent;
use App\Contracts\PastPaperCollector\QuestionProcessingProvider;
use App\Models\AIImport;
use App\Models\AIImportLog;
use App\Models\AISetting;
use Throwable;

class GeminiQuestionProcessingProvider implements QuestionProcessingProvider
{
    public function name(): string
    {
        return AISetting::current()->preferred_text_provider ?: 'gemini';
    }

    public function isConfigured(): bool
    {
        return AISetting::current()->isTextProviderConfigured();
    }

    public function extractQuestions(
        AIImport $import,
        string $chunkText,
        int $chunkIndex,
        ?string $chunkTitle = null,
        array $extraMeta = [],
    ): array {
        $settings = AISetting::current();
        $settings->applyProviderConfig();

        if (! $settings->isTextProviderConfigured()) {
            throw new \RuntimeException('AI text provider is not configured. Set keys in Super Admin AI Settings.');
        }

        $source = $import->sourceForBookType();
        $prompt = $this->buildUserPrompt($import, $chunkText, $chunkTitle, $source, $extraMeta);

        $log = AIImportLog::create([
            'ai_import_id' => $import->id,
            'chunk_index' => $chunkIndex,
            'prompt' => $prompt,
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        $attempts = 0;
        $maxAttempts = max(1, (int) ($settings->collector_retry_attempts ?: $settings->retry_count));
        $lastError = null;

        while ($attempts < $maxAttempts) {
            $attempts++;
            $started = microtime(true);

            try {
                $agent = PastPaperExtractionAgent::make(
                    instructionsOverride: $settings->prompt_template
                        ?: AISetting::pastPaperPromptTemplate()
                );

                $response = $agent->prompt(
                    $prompt,
                    provider: $settings->resolvedTextProvider(),
                    model: $settings->resolvedTextModel(),
                    timeout: max(60, (int) $settings->search_timeout * 6),
                );

                $decoded = $this->decodeResponse((string) $response);
                $questions = $decoded['questions'] ?? [];

                $log->update([
                    'response' => (string) $response,
                    'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
                    'input_tokens' => $response->usage->promptTokens ?? null,
                    'output_tokens' => $response->usage->completionTokens ?? null,
                    'retry_count' => $attempts - 1,
                    'status' => 'success',
                    'error' => null,
                ]);

                return [
                    'questions' => is_array($questions) ? $questions : [],
                    'log' => $log->fresh(),
                ];
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                $log->update([
                    'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
                    'retry_count' => $attempts - 1,
                    'status' => 'failed',
                    'error' => $lastError,
                ]);
            }
        }

        throw new \RuntimeException($lastError ?: 'Gemini past-paper extraction failed.');
    }

    protected function buildUserPrompt(
        AIImport $import,
        string $chunkText,
        ?string $chunkTitle,
        string $source,
        array $extraMeta,
    ): string {
        $import->loadMissing(['grade', 'subject']);

        $meta = array_merge([
            'grade' => $import->grade?->label_en ?? $import->grade_id,
            'subject' => $import->subject?->name_en ?? $import->subject_id,
            'board' => $import->board,
            'year' => $import->year,
            'session' => $import->session,
            'language' => $import->language,
            'source' => $source,
            'book_type' => $import->book_type,
            'chunk_title' => $chunkTitle,
            'country' => 'Pakistan',
        ], $extraMeta);

        return "Past paper context metadata (JSON):\n"
            .json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nExtract all exam questions from this past paper content. Ignore ads, headers, footers, page numbers, and answer keys:\n\n"
            .$chunkText;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(string $text): array
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON response from Gemini: '.json_last_error_msg());
        }

        if (! isset($decoded['questions']) && array_is_list($decoded)) {
            return ['questions' => $decoded];
        }

        return $decoded;
    }
}
