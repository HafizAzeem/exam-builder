<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class QuestionBankService
{
    protected function baseQuery(
        array $chapterIds,
        ?string $type,
        array $sources,
        ?string $search = null,
    ) {
        $query = Question::query()
            ->with(['mcqOptions', 'pastPaperTag', 'parts', 'chapter.subject.grade'])
            ->whereIn('chapter_id', $chapterIds)
            ->where('is_active', true)
            ->whereNull('parent_question_id');

        if ($type) {
            $query->where('type', $type);
        }

        if ($sources) {
            $query->whereIn('source', $sources);
        }

        if ($search) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('text_en', 'like', "%{$search}%")
                    ->orWhere('text_ur', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function manualFetch(
        array $chapterIds,
        ?string $type,
        array $sources,
        ?string $search = null,
        int $perPage = 20
    ): LengthAwarePaginator
    {
        return $this->baseQuery($chapterIds, $type, $sources, $search)
            ->latest('id')
            ->paginate($perPage);
    }

    public function fetchAll(
        array $chapterIds,
        ?string $type,
        array $sources,
        ?string $search = null,
        int $limit = 500,
    ): Collection {
        return $this->baseQuery($chapterIds, $type, $sources, $search)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function randomFetch(
        array $chapterIds,
        array $config,
        string $cacheKey,
        array $sources = [],
    ): Collection {
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($chapterIds, $config, $sources) {
            $results = collect();

            foreach ($config as $type => $count) {
                $count = (int) $count;
                if ($count <= 0) {
                    continue;
                }

                $query = Question::query()
                    ->with(['mcqOptions', 'pastPaperTag', 'parts'])
                    ->whereIn('chapter_id', $chapterIds)
                    ->where('type', $type)
                    ->where('is_active', true)
                    ->whereNull('parent_question_id');

                if ($sources) {
                    $query->whereIn('source', $sources);
                }

                $questions = $query->inRandomOrder()->limit($count)->get();

                $results = $results->merge($questions);
            }

            return $results->values();
        });
    }

    public function invalidateCache(string $cacheKey): void
    {
        Cache::forget($cacheKey);
    }

    public function fetchByIds(array $ids): Collection
    {
        if (! $ids) {
            return collect();
        }

        return Question::query()
            ->with(['mcqOptions', 'pastPaperTag', 'parts'])
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get();
    }
}
