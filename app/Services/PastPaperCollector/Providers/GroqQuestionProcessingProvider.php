<?php

namespace App\Services\PastPaperCollector\Providers;

use App\Contracts\PastPaperCollector\QuestionProcessingProvider;
use App\Models\AIImport;
use App\Models\AIImportLog;
use App\Models\AISetting;
use App\Services\AIImport\GroqClient;
use App\Services\AIImport\QuestionJsonSchema;
use Throwable;

class GroqQuestionProcessingProvider implements QuestionProcessingProvider
{
    public function __construct(
        protected GroqClient $groq,
    ) {}

    public function name(): string
    {
        return AISetting::PROVIDER_GROQ;
    }

    public function isConfigured(): bool
    {
        return AISetting::current()->isGroqUsable();
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

        if (! $settings->isGroqUsable()) {
            throw new \RuntimeException('Groq is not configured. Set the Groq API key in AI Settings.');
        }

        $prompt = $this->buildUserPrompt($import, $chunkText, $chunkTitle, $import->sourceForBookType(), $extraMeta);

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
                $result = $this->groq->chat([
                    ['role' => 'system', 'content' => QuestionJsonSchema::systemInstructions()],
                    ['role' => 'user', 'content' => $prompt],
                ], $settings->resolvedGroqModel(), [
                    'response_format' => QuestionJsonSchema::groqResponseFormat(),
                ], max(60, (int) $settings->search_timeout * 6));
                $decoded = $this->groq->decodeJson($result['content']);
                $questions = $decoded['questions'] ?? [];

                $log->update([
                    'response' => $result['content'],
                    'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
                    'input_tokens' => data_get($result, 'usage.prompt_tokens'),
                    'output_tokens' => data_get($result, 'usage.completion_tokens'),
                    'retry_count' => $attempts - 1,
                    'status' => 'success',
                    'error' => null,
                ]);

                return [
                    'questions' => is_array($questions) ? array_values($questions) : [],
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

        throw new \RuntimeException($lastError ?: 'Groq past-paper extraction failed.');
    }

    protected function buildUserPrompt(
        AIImport $import,
        string $chunkText,
        ?string $chunkTitle,
        string $source,
        array $extraMeta,
    ): string {
        $import->loadMissing(['grade', 'subject']);
        $instructions = AISetting::current()->prompt_template ?: AISetting::pastPaperPromptTemplate();

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

        return $instructions."\n\n"
            ."Return ONLY JSON {\"questions\":[...]}. Do not invent MCQ options.\n\n"
            ."Past paper context metadata (JSON):\n"
            .json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nExtract all exam questions from this past paper content. Ignore ads, headers, footers, page numbers, and answer keys:\n\n"
            .$chunkText;
    }
}
