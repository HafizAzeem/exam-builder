<?php

namespace App\Services\QuestionAssistant;

use App\Models\AIImport;
use App\Models\AssistantRequest;
use App\Services\AIImport\AIImportService;
use App\Services\AIImport\QuestionParserService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class QuestionAssistantService
{
    public function __construct(private AssistantBudgetService $budget, private AssistantAccess $access, private AssistantSourceReader $reader, private AssistantAiExtractor $extractor, private QuestionOnlyPayload $payload, private QuestionParserService $parser) {}

    public function process(AssistantRequest $request): void
    {
        if (! $this->budget->start($request)) {
            $current = $request->fresh();
            if (in_array($current->status, ['failed', 'canceled', 'completed'], true) && ($import = AIImport::find($current->ai_import_id)) && $import->stored_path) {
                Storage::disk(app(AIImportService::class)->disk())->delete($import->stored_path);
            }

            return;
        }
        $request->refresh();
        $import = AIImport::findOrFail($request->ai_import_id);
        $deadline = microtime(true) + 120;
        $check = function () use ($request, $import, $deadline) {
            $current = $request->fresh();
            if ($current->status !== 'processing' || $current->expires_at->isPast() || microtime(true) > $deadline) {
                throw new \RuntimeException('This request was canceled or timed out.');
            }
            $this->access->assert($import->user()->firstOrFail(), ['grade_id' => $import->grade_id, 'subject_id' => $import->subject_id, 'chapter_ids' => $import->chapterIds(), 'book_type' => $import->book_type]);
        };
        try {
            $check();
            $image = null;
            if ($request->mode === 'search') {
                $this->stage($import, 'searching');
                $sources = $this->reader->sources($import, $deadline, $check);
            } else {
                $this->stage($import, 'reading');
                $disk = Storage::disk(app(AIImportService::class)->disk());
                if ($request->mode === 'upload' && empty($import->meta['extracted_text'])) {
                    $bytes = $disk->get($import->stored_path);
                    $image = ['mime' => $import->mime_type, 'data' => base64_encode($bytes)];
                    $sources = [['text' => '', 'image' => true, 'url' => null, 'published_at' => null]];
                } else {
                    $text = $import->meta['extracted_text'] ?? $disk->get($import->stored_path);
                    if (mb_strlen($text) > 20000) {
                        throw new \RuntimeException('The extracted text exceeds the request limit.');
                    }
                    $sources = [['text' => $text, 'url' => null, 'published_at' => null]];
                }
            }
            $check();
            $this->stage($import, 'extracting');
            // Maximum two provider calls: split source text rather than retry charged calls.
            $groups = [];
            $group = [];
            $length = 0;
            foreach ($sources as $source) {
                if ($length + mb_strlen($source['text']) > 11000 && $group) {
                    $groups[] = $group;
                    $group = [];
                    $length = 0;
                }
                $group[] = $source;
                $length += mb_strlen($source['text']);
            }
            if ($group) {
                $groups[] = $group;
            }
            if (count($groups) > 2) {
                $groups = [$groups[0], array_merge(...array_slice($groups, 1))];
            }
            $questions = [];
            foreach (array_slice($groups, 0, 2) as $group) {
                $check();
                $text = implode("\n\nSOURCE BREAK\n\n", array_column($group, 'text'));
                $remaining = (int) floor($deadline - microtime(true));
                if ($remaining < 3) {
                    throw new \RuntimeException('The request timed out before extraction could finish.');
                }
                $raw = $this->extractor->extract($import, $text, $image, min(45, $remaining));
                $questions = array_merge($questions, $this->payload->filter($raw, $group, (int) $import->meta['question_count']));
            }
            $questions = $this->payload->filter($questions, $sources, (int) $import->meta['question_count']);
            if (! $questions) {
                throw new \RuntimeException('No questions could be verified in the supplied material. Try a clear question list or a more specific search.');
            }
            $check();
            $this->stage($import, 'preparing');
            $this->budget->complete($request, function () use ($import, $questions) {
                $this->access->assert($import->user()->firstOrFail(), ['grade_id' => $import->grade_id, 'subject_id' => $import->subject_id, 'chapter_ids' => $import->chapterIds(), 'book_type' => $import->book_type]);
                $created = $this->parser->storeStagingQuestions($import, $questions);
                if (! $created) {
                    throw new \RuntimeException('No usable questions were extracted.');
                }
                app(AIImportService::class)->constrainChapters($import);
                $import->update(['status' => 'review', 'progress_percent' => 100, 'questions_found' => count($created)]);
                $import->broadcastProgress();
            });
        } catch (\Throwable $e) {
            $message = $e instanceof ValidationException ? 'Assistant access or curriculum permissions changed. Please check your selected context.' : $e->getMessage();
            $this->budget->fail($request, $message);
            if ($request->fresh()->status !== 'completed') {
                $import->update(['status' => 'failed', 'error_message' => $message]);
            }
        } finally {
            // Source files are temporary; completed previews keep their structured questions.
            if ($import->stored_path) {
                Storage::disk(app(AIImportService::class)->disk())->delete($import->stored_path);
            }
            $meta = $import->fresh()->meta ?? [];
            unset($meta['extracted_text']);
            $import->update(['meta' => $meta]);
        }
    }

    private function stage(AIImport $import, string $stage): void
    {
        $meta = $import->fresh()->meta ?? [];
        $meta['assistant_stage'] = $stage;
        $import->update(['status' => 'processing', 'meta' => $meta]);
        $import->broadcastProgress();
    }
}
