<?php

namespace App\Services\AIImport\Providers;

use App\Contracts\AIImport\QuestionAiService;
use App\Models\AIImport;
use App\Models\AIImportLog;
use App\Models\AISetting;
use App\Models\Chapter;
use App\Services\AIImport\GroqClient;
use App\Services\AIImport\QuestionJsonSchema;
use Throwable;

class GroqQuestionAiService implements QuestionAiService
{
    public function __construct(
        protected GroqClient $groq,
    ) {}

    public function name(): string
    {
        return AISetting::PROVIDER_GROQ;
    }

    public function extractQuestions(
        AIImport $import,
        string $chunkText,
        int $chunkIndex,
        ?string $chunkTitle = null,
    ): array {
        $settings = AISetting::current();
        if (! $settings->isGroqUsable()) {
            throw new \RuntimeException('Groq is not configured. Set the Groq API key in AI Settings.');
        }

        $prompt = $this->buildExtractPrompt($import, $chunkText, $chunkTitle);

        return $this->run($import, $chunkIndex, $prompt);
    }

    public function generateQuestions(AIImport $import): array
    {
        $settings = AISetting::current();
        if (! $settings->isGroqUsable()) {
            throw new \RuntimeException('Groq is not configured. Set the Groq API key in AI Settings.');
        }

        return $this->run($import, 0, $this->buildGeneratePrompt($import));
    }

    /**
     * @return array{questions: list<array<string, mixed>>, log: AIImportLog}
     */
    protected function run(AIImport $import, int $chunkIndex, string $prompt): array
    {
        $settings = AISetting::current();
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
                $result = $this->groq->chat([
                    ['role' => 'system', 'content' => QuestionJsonSchema::systemInstructions()],
                    ['role' => 'user', 'content' => $prompt],
                ], $settings->resolvedGroqModel(), [
                    'response_format' => QuestionJsonSchema::groqResponseFormat(),
                ]);
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

        throw new \RuntimeException($lastError ?: 'Groq question processing failed.');
    }

    protected function buildExtractPrompt(AIImport $import, string $chunkText, ?string $chunkTitle): string
    {
        $import->loadMissing(['grade', 'subject']);
        $instructions = AISetting::current()->prompt_template ?: AISetting::defaultPromptTemplate();
        $source = $import->sourceForBookType();

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

        return $instructions."\n\n"
            ."Return JSON {\"questions\":[...]} matching the exam_questions schema.\n"
            ."If the text has no (A)(B)(C)(D) choices, type must be short/long/fill/truefalse — never an MCQ without four options.\n\n"
            ."Context metadata (JSON):\n".json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nExtract all exam questions from this text chunk:\n\n".$chunkText;
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
        ];

        $countLines = [];
        foreach (['mcq', 'short', 'long', 'fill', 'truefalse'] as $type) {
            $countLines[] = $type.': '.(int) ($counts[$type] ?? 0);
        }
        $total = array_sum(array_map('intval', is_array($counts) ? $counts : []));

        return "Generate Pakistani board exam questions.\n"
            ."Produce EXACTLY these counts and no extras:\n".implode("\n", $countLines)."\n"
            ."Total questions in the array MUST be {$total}.\n"
            ."MCQ items MUST include mcq_options with four distinct choices and correct_option.\n"
            ."Use only listed chapters.\n\n"
            ."Context metadata (JSON):\n".json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            .(strtolower((string) $import->language) === 'urdu' || strcasecmp((string) $import->subject?->name_en, 'Urdu') === 0
                ? "\nIMPORTANT: Write questions in Urdu (text_ur)."
                : '');
    }
}
