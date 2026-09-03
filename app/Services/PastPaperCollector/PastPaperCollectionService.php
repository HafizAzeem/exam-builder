<?php

namespace App\Services\PastPaperCollector;

use App\Contracts\PastPaperCollector\QuestionProcessingProvider;
use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Jobs\CollectPastPapersJob;
use App\Jobs\ProcessPaperSourceJob;
use App\Models\AIImport;
use App\Models\AIPaperCollection;
use App\Models\AIPaperSource;
use App\Models\AISetting;
use App\Services\AIImport\DuplicateDetectionService;
use App\Services\AIImport\QuestionParserService;
use Illuminate\Support\Facades\DB;

class PastPaperCollectionService
{
    public function __construct(
        protected SearchQueryGenerator $queryGenerator,
        protected WebSearchProvider $searchProvider,
        protected SourceRelevanceFilter $relevanceFilter,
        protected UrlSafetyService $urlSafety,
        protected SourceFetchService $fetchService,
        protected PaperAwareChunkerService $chunker,
        protected QuestionProcessingProvider $questionProcessor,
        protected QuestionParserService $parser,
        protected DuplicateDetectionService $duplicates,
    ) {}

    public function create(array $data, int $userId): AIPaperCollection
    {
        return DB::transaction(function () use ($data, $userId) {
            $settings = AISetting::current();

            $import = AIImport::create([
                'user_id' => $userId,
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'book_type' => 'past_paper',
                'board' => $data['board'] ?? 'Lahore Board',
                'year' => $data['year'],
                'session' => $data['session'] ?? null,
                'language' => $data['language'] ?? 'english',
                'original_filename' => 'web-collection',
                'stored_path' => 'ai-paper-collections/pending',
                'mime_type' => 'text/plain',
                'file_size' => 0,
                'status' => 'uploaded',
                'progress_percent' => 0,
            ]);

            $collection = AIPaperCollection::create([
                'user_id' => $userId,
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'ai_import_id' => $import->id,
                'board' => $data['board'] ?? 'Lahore Board',
                'year' => $data['year'],
                'session' => $data['session'] ?? null,
                'paper_type' => $data['paper_type'] ?? 'complete',
                'language' => $data['language'] ?? 'english',
                'country' => 'Pakistan',
                'max_results' => min(
                    (int) ($data['max_results'] ?? $settings->max_urls_per_search),
                    (int) $settings->max_urls_per_search
                ),
                'keywords_override' => $data['keywords_override'] ?? null,
                'status' => 'queued',
                'progress_stage' => 'queued',
                'progress_percent' => 0,
                'started_at' => now(),
            ]);

            if ($settings->enable_queue) {
                CollectPastPapersJob::dispatch($collection);
            } else {
                CollectPastPapersJob::dispatchSync($collection);
            }

            return $collection->fresh(['grade', 'subject', 'import']);
        });
    }

    public function runCollection(AIPaperCollection $collection): void
    {
        $started = microtime(true);
        $settings = AISetting::current();

        try {
            if (! $this->searchProvider->isConfigured()) {
                throw new \RuntimeException('Web search provider is not configured.');
            }
            if (! $this->questionProcessor->isConfigured()) {
                throw new \RuntimeException('Question processing provider is not configured.');
            }

            $collection->markStage('searching');
            $queries = $this->queryGenerator->generate($collection);
            $collection->update(['generated_queries' => $queries]);

            $collection->markStage('collecting_sources');
            $candidates = $this->discoverSources($collection, $queries, $settings);

            if ($candidates === []) {
                $collection->update([
                    'status' => 'failed',
                    'progress_stage' => 'failed',
                    'progress_percent' => 100,
                    'error_message' => 'No relevant search results found for this paper.',
                    'completed_at' => now(),
                    'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
                ]);
                $collection->import?->update([
                    'status' => 'failed',
                    'error_message' => 'No relevant search results found.',
                    'progress_percent' => 100,
                ]);

                return;
            }

            $collection->markStage('downloading');
            $this->processSources($collection, $settings);

            $collection->refresh()->refreshCounters();
            $collection->load('import');

            if ($collection->import) {
                $collection->import->refreshCounters();
                $tokenStats = $collection->import->logs()
                    ->selectRaw('COALESCE(SUM(input_tokens),0) as input_tokens, COALESCE(SUM(output_tokens),0) as output_tokens')
                    ->first();

                $hasQuestions = $collection->import->questions()->exists();

                $collection->update([
                    'input_tokens' => (int) ($tokenStats->input_tokens ?? 0),
                    'output_tokens' => (int) ($tokenStats->output_tokens ?? 0),
                    'questions_found' => $collection->import->questions()->count(),
                    'duplicate_count' => $collection->import->questions()->where('is_duplicate', true)->count(),
                    'status' => $hasQuestions ? 'review' : 'failed',
                    'progress_stage' => $hasQuestions ? 'review' : 'failed',
                    'progress_percent' => 100,
                    'error_message' => $hasQuestions ? null : 'Sources were visited but no questions could be extracted.',
                    'completed_at' => now(),
                    'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
                ]);

                $collection->import->update([
                    'status' => $hasQuestions ? 'review' : 'failed',
                    'progress_percent' => 100,
                    'error_message' => $hasQuestions ? null : 'No questions extracted from collected sources.',
                ]);
            }
        } catch (\Throwable $e) {
            $collection->update([
                'status' => 'failed',
                'progress_stage' => 'failed',
                'progress_percent' => 100,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
            $collection->import?->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'progress_percent' => 100,
            ]);

            throw $e;
        }
    }

    /**
     * @param  list<string>  $queries
     * @return list<AIPaperSource>
     */
    protected function discoverSources(AIPaperCollection $collection, array $queries, AISetting $settings): array
    {
        $collection->loadMissing(['subject']);
        $max = max(1, min((int) $collection->max_results, (int) $settings->max_urls_per_search));
        $seen = [];
        $created = [];

        foreach ($queries as $query) {
            if (count($created) >= $max) {
                break;
            }

            try {
                $results = $this->searchProvider->search($query, min(10, $max), [
                    'timeout' => $settings->search_timeout,
                    'country_code' => 'pk',
                    'language' => str_starts_with(strtolower((string) $collection->language), 'ur') ? 'ur' : 'en',
                ]);
            } catch (\Throwable $e) {
                // Continue with other queries on provider errors for best-effort collection.
                report($e);

                continue;
            }

            foreach ($results as $result) {
                if (count($created) >= $max) {
                    break 2;
                }

                if (! $this->relevanceFilter->isRelevant($result, $collection)) {
                    continue;
                }

                try {
                    $normalized = $this->urlSafety->assertSafe($result['url']);
                } catch (\Throwable) {
                    continue;
                }

                $key = strtolower(rtrim($normalized, '/'));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $created[] = AIPaperSource::create([
                    'ai_paper_collection_id' => $collection->id,
                    'url' => $result['url'],
                    'normalized_url' => $normalized,
                    'title' => $result['title'] ?? null,
                    'snippet' => $result['snippet'] ?? null,
                    'status' => 'discovered',
                ]);
            }
        }

        $collection->refreshCounters();

        return $created;
    }

    protected function processSources(AIPaperCollection $collection, AISetting $settings): void
    {
        $sources = $collection->sources()->where('status', 'discovered')->orderBy('id')->get();
        if ($sources->isEmpty()) {
            return;
        }

        $batchSize = max(1, (int) $settings->queue_size);
        $jobs = $sources->map(fn (AIPaperSource $source) => new ProcessPaperSourceJob($source))->all();

        if ($settings->enable_queue && count($jobs) > 1) {
            // Process synchronously within the parent job for reliable progress tracking in v1,
            // while still respecting queue_size for chunked sequential batches.
            foreach (array_chunk($jobs, $batchSize) as $chunk) {
                foreach ($chunk as $job) {
                    $job->handle($this);
                }
            }

            return;
        }

        foreach ($jobs as $job) {
            $job->handle($this);
        }
    }

    public function processSource(AIPaperSource $source): void
    {
        $collection = $source->collection()->with(['import.grade', 'import.subject', 'subject'])->firstOrFail();
        $import = $collection->import;
        if (! $import) {
            throw new \RuntimeException('Collection is missing linked AI import.');
        }

        $started = microtime(true);
        $source->update(['status' => 'downloading', 'error_message' => null]);

        try {
            $collection->markStage('downloading');
            $payload = $this->fetchService->fetchAndExtract($source->normalized_url ?: $source->url, $collection->id);

            // Deduplicate identical content within the same collection.
            if (! empty($payload['content_hash'])) {
                $duplicate = AIPaperSource::query()
                    ->where('ai_paper_collection_id', $collection->id)
                    ->where('content_hash', $payload['content_hash'])
                    ->where('id', '!=', $source->id)
                    ->whereIn('status', ['extracted', 'processed'])
                    ->exists();

                if ($duplicate) {
                    $source->update([
                        'status' => 'ignored',
                        'content_type' => $payload['content_type'],
                        'content_hash' => $payload['content_hash'],
                        'stored_path' => $payload['stored_path'],
                        'file_size' => $payload['file_size'],
                        'http_status' => $payload['http_status'],
                        'extracted_text' => $payload['extracted_text'],
                        'meta' => $payload['meta'],
                        'error_message' => 'Duplicate content hash within collection.',
                        'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
                    ]);
                    $collection->refreshCounters();

                    return;
                }
            }

            $source->update([
                'content_type' => $payload['content_type'],
                'content_hash' => $payload['content_hash'],
                'stored_path' => $payload['stored_path'],
                'file_size' => $payload['file_size'],
                'http_status' => $payload['http_status'],
                'extracted_text' => $payload['extracted_text'],
                'meta' => $payload['meta'],
                'status' => $payload['status'],
                'error_message' => $payload['error_message'],
            ]);

            if ($payload['status'] !== 'extracted' || ! filled($payload['extracted_text'])) {
                $collection->refreshCounters();

                return;
            }

            $collection->markStage('extracting');
            $source->update(['status' => 'extracting']);

            $chunks = $this->chunker->chunk((string) $payload['extracted_text']);
            $collection->markStage('processing');
            $source->update(['status' => 'processing']);

            $createdCount = 0;
            $chunkOffset = ($source->id * 1000);

            foreach ($chunks as $chunk) {
                $result = $this->questionProcessor->extractQuestions(
                    $import,
                    $chunk['text'],
                    $chunkOffset + (int) $chunk['index'],
                    $chunk['title'],
                    [
                        'source_url' => $source->url,
                        'source_title' => $source->title,
                        'paper_type' => $collection->paper_type,
                        'collection_id' => $collection->id,
                    ],
                );

                $created = $this->parser->storeStagingQuestions(
                    $import,
                    $result['questions'],
                    [
                        'ai_paper_source_id' => $source->id,
                        'source_url' => $source->url,
                        'source_excerpt' => mb_substr((string) $payload['extracted_text'], 0, 2000),
                    ],
                );

                $this->duplicates->scanMany($created);
                $createdCount += count($created);
            }

            $collection->markStage('classifying');

            $source->update([
                'status' => 'processed',
                'questions_extracted' => $createdCount,
                'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
                'error_message' => null,
            ]);

            $import->refreshCounters();
            $collection->refreshCounters();
        } catch (\Throwable $e) {
            $source->update([
                'status' => 'failed',
                'retry_count' => $source->retry_count + 1,
                'error_message' => $e->getMessage(),
                'processing_time_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
            $collection->refreshCounters();
        }
    }

    public function retrySource(AIPaperSource $source): void
    {
        if (! $source->isRetryable()) {
            throw new \RuntimeException('Source is not retryable.');
        }

        $source->update([
            'status' => 'discovered',
            'error_message' => null,
            'extracted_text' => null,
            'questions_extracted' => 0,
        ]);

        $settings = AISetting::current();
        if ($settings->enable_queue) {
            ProcessPaperSourceJob::dispatch($source);
        } else {
            ProcessPaperSourceJob::dispatchSync($source);
        }
    }

    public function retryFailed(AIPaperCollection $collection): void
    {
        $failed = $collection->sources()->whereIn('status', ['failed', 'ocr_required'])->get();
        foreach ($failed as $source) {
            $this->retrySource($source);
        }

        if ($collection->status === 'failed' && $failed->isNotEmpty()) {
            $collection->update([
                'status' => 'downloading',
                'progress_stage' => 'downloading',
                'error_message' => null,
                'completed_at' => null,
            ]);
        }
    }

    public function statusPayload(AIPaperCollection $collection): array
    {
        $collection->loadMissing(['grade', 'subject', 'import', 'sources']);

        return [
            'id' => $collection->id,
            'status' => $collection->status,
            'progress_stage' => $collection->progress_stage,
            'progress_percent' => $collection->progress_percent,
            'urls_visited' => $collection->urls_visited,
            'successful_sources' => $collection->successful_sources,
            'failed_sources' => $collection->failed_sources,
            'ocr_required_sources' => $collection->ocr_required_sources,
            'questions_found' => $collection->questions_found,
            'duplicate_count' => $collection->duplicate_count,
            'imported_count' => $collection->imported_count,
            'input_tokens' => $collection->input_tokens,
            'output_tokens' => $collection->output_tokens,
            'processing_time_ms' => $collection->processing_time_ms,
            'error_message' => $collection->error_message,
            'generated_queries' => $collection->generated_queries,
            'ai_import_id' => $collection->ai_import_id,
            'sources' => $collection->sources->map(fn (AIPaperSource $s) => [
                'id' => $s->id,
                'url' => $s->url,
                'title' => $s->title,
                'status' => $s->status,
                'questions_extracted' => $s->questions_extracted,
                'error_message' => $s->error_message,
            ])->values(),
        ];
    }
}
