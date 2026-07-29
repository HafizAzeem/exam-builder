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
        array $topicIds = [],
        ?string $boardName = null,
        ?int $year = null,
    ) {
        $query = Question::query()
            ->with(['mcqOptions', 'pastPaperTag', 'parts', 'chapter.subject.grade', 'topic'])
            ->where('is_active', true)
            ->whereNull('parent_question_id');

        if ($topicIds) {
            $query->where(function ($q) use ($topicIds, $chapterIds) {
                $q->whereIn('topic_id', $topicIds);

                // Include legacy questions without topic, scoped to selected chapters.
                if ($chapterIds) {
                    $q->orWhere(function ($legacy) use ($chapterIds) {
                        $legacy->whereNull('topic_id')->whereIn('chapter_id', $chapterIds);
                    });
                }
            });
        } else {
            $query->whereIn('chapter_id', $chapterIds);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($sources) {
            $query->whereIn('source', $sources);
        }

        if ($boardName || $year) {
            $query->whereHas('pastPaperTag', function ($q) use ($boardName, $year) {
                if ($boardName) {
                    $q->where('board_name', $boardName);
                }
                if ($year) {
                    $q->where('year', $year);
                }
            });
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
        int $perPage = 20,
        array $topicIds = [],
        ?string $boardName = null,
        ?int $year = null,
    ): LengthAwarePaginator
    {
        return $this->baseQuery($chapterIds, $type, $sources, $search, $topicIds, $boardName, $year)
            ->latest('id')
            ->paginate($perPage);
    }

    public function fetchAll(
        array $chapterIds,
        ?string $type,
        array $sources,
        ?string $search = null,
        int $limit = 500,
        array $topicIds = [],
        ?string $boardName = null,
        ?int $year = null,
    ): Collection {
        return $this->baseQuery($chapterIds, $type, $sources, $search, $topicIds, $boardName, $year)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function randomFetch(
        array $chapterIds,
        array $config,
        string $cacheKey,
        array $sources = [],
        array $topicIds = [],
    ): Collection {
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($chapterIds, $config, $sources, $topicIds) {
            $results = collect();

            foreach ($config as $type => $count) {
                $count = (int) $count;
                if ($count <= 0) {
                    continue;
                }

                $query = Question::query()
                    ->with(['mcqOptions', 'pastPaperTag', 'parts'])
                    ->where('type', $type)
                    ->where('is_active', true)
                    ->whereNull('parent_question_id');

                if ($topicIds) {
                    $query->where(function ($q) use ($topicIds, $chapterIds) {
                        $q->whereIn('topic_id', $topicIds);
                        if ($chapterIds) {
                            $q->orWhere(function ($legacy) use ($chapterIds) {
                                $legacy->whereNull('topic_id')->whereIn('chapter_id', $chapterIds);
                            });
                        }
                    });
                } else {
                    $query->whereIn('chapter_id', $chapterIds);
                }

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
