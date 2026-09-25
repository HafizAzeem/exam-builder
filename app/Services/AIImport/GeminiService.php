<?php

namespace App\Services\AIImport;

use App\Ai\Agents\QuestionExtractionAgent;
use App\Ai\Agents\QuestionGenerationAgent;
use App\Models\AIImport;
use App\Models\AIImportLog;
use App\Models\AISetting;
use App\Models\Chapter;
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

    /**
     * @return array{questions: list<array<string, mixed>>, log: AIImportLog}
     */
    public function generateQuestions(AIImport $import): array
    {
        $settings = AISetting::current();
        $settings->applyProviderConfig();

        if (! $settings->isTextProviderConfigured()) {
            throw new \RuntimeException('Gemini is not configured. Set the Gemini API key in AI Settings.');
        }

        $prompt = $this->buildGeneratePrompt($import);

        $log = AIImportLog::create([
            'ai_import_id' => $import->id,
            'chunk_index' => 0,
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
                $agent = QuestionGenerationAgent::make();

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

        throw new \RuntimeException($lastError ?: 'Gemini generation failed.');
    }

    protected function buildGeneratePrompt(AIImport $import): string
    {
        $import->loadMissing(['grade', 'subject']);

        $chapterIds = $import->chapterIds();
        $chapters = Chapter::query()
            ->where('subject_id', $import->subject_id)
            ->whereIn('id', $chapterIds ?: [0])
            ->orderBy('number')
            ->get(['id', 'number', 'title_en', 'title_ur']);

        $counts = $import->meta['counts'] ?? [];
        $contentSource = $import->meta['content_source'] ?? null;
        $contentSources = $import->meta['content_sources'] ?? ($contentSource ? [$contentSource] : []);

        $meta = [
            'grade' => $import->grade?->label_en ?? $import->grade_id,
            'subject' => $import->subject?->name_en ?? $import->subject_id,
            'board' => $import->board,
            'language' => $import->language,
            'source' => $import->sourceForBookType(),
            'content_source' => $contentSource,
            'content_sources' => $contentSources,
            'book_type' => $import->book_type,
            'requested_counts' => $counts,
            'chapters' => $chapters->map(fn (Chapter $c) => [
                'id' => $c->id,
                'chapter_number' => $c->number,
                'chapter_title' => $c->title_en,
                'chapter_title_ur' => $c->title_ur,
            ])->values()->all(),
            'language_rules' => [
                'urdu_subject_or_language' => 'Prefer text_ur only; do not force English translation.',
                'english' => 'Prefer text_en; text_ur optional.',
                'either_language_ok' => true,
            ],
        ];

        return "Context metadata (JSON):\n".json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nGenerate exam questions that match the requested_counts exactly when possible."
            ." Use only the chapters listed above. Return structured questions."
            ." Match the selected content_sources styles (exercise / past_paper / online_practice) when more than one is listed."
            .(strtolower((string) $import->language) === 'urdu' || strcasecmp((string) $import->subject?->name_en, 'Urdu') === 0
                ? "\nIMPORTANT: This is an Urdu paper — write questions in Urdu (text_ur). Do not require English translations."
                : '');
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
