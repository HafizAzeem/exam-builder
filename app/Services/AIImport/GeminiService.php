<?php

namespace App\Services\AIImport;

use App\Ai\Agents\QuestionExtractionAgent;
use App\Models\AIImport;
use App\Models\AIImportLog;
use App\Models\AISetting;
use Throwable;

class GeminiService
{
    /**
     * @return array{questions: list<array<string, mixed>>, log: AIImportLog}
     */
    public function extractQuestions(
        AIImport $import,
        string $chunkText,
        int $chunkIndex,
        ?string $chunkTitle = null,
    ): array {
        $settings = AISetting::current();
        $settings->applyProviderConfig();

        if (! $settings->isTextProviderConfigured()) {
            throw new \RuntimeException('Gemini is not configured. Set the Gemini API key in AI Settings.');
        }

        $source = $import->sourceForBookType();

        $prompt = $this->buildUserPrompt($import, $chunkText, $chunkTitle, $source);

        $log = AIImportLog::create([
            'ai_import_id' => $import->id,
            'chunk_index' => $chunkIndex,
            'prompt' => $prompt,
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        $attempts = 0;
        $maxAttempts = max(1, (int) $settings->retry_count);
        $lastError = null;

        while ($attempts < $maxAttempts) {
            $attempts++;
            $started = microtime(true);

            try {
                $agent = QuestionExtractionAgent::make(
                    instructionsOverride: $settings->prompt_template
                );

                $response = $agent->prompt(
                    $prompt,
                    provider: $settings->resolvedTextProvider(),
                    model: $settings->resolvedTextModel(),
                    timeout: 180,
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

        throw new \RuntimeException($lastError ?: 'Gemini extraction failed.');
    }

    protected function buildUserPrompt(AIImport $import, string $chunkText, ?string $chunkTitle, string $source): string
    {
        $import->loadMissing(['grade', 'subject']);

        $meta = [
            'grade' => $import->grade?->label_en ?? $import->grade_id,
            'subject' => $import->subject?->name_en ?? $import->subject_id,
            'book_type' => $import->book_type,
            'source' => $source,
            'language' => $import->language,
            'board' => $import->board,
            'year' => $import->year,
            'session' => $import->session,
            'chunk_title' => $chunkTitle,
        ];

        return "Context metadata (JSON):\n".json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nExtract all exam questions from this text chunk:\n\n".$chunkText;
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
