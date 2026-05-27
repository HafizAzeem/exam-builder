<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class QuestionBankService
{
    public function manualFetch(
        array $chapterIds,
        ?string $type,
        array $sources,
        ?string $search = null,
        int $perPage = 20
    ): LengthAwarePaginator
    {
        $query = Question::query()
            ->with(['mcqOptions', 'pastPaperTag', 'chapter.subject.grade'])
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

        return $query->latest('id')->paginate($perPage);
    }

    public function randomFetch(array $chapterIds, array $config, string $cacheKey): Collection
    {
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($chapterIds, $config) {
            $results = collect();

            foreach ($config as $type => $count) {
                if (! $count) {
                    continue;
                }

                $questions = Question::query()
                    ->with(['mcqOptions', 'pastPaperTag'])
                    ->whereIn('chapter_id', $chapterIds)
                    ->where('type', $type)
                    ->where('is_active', true)
                    ->whereNull('parent_question_id')
                    ->inRandomOrder()
                    ->limit((int) $count)
                    ->get();

                $results = $results->merge($questions);
            }

            return $results->values();
        });
    }

    public function invalidateCache(string $cacheKey): void
    {
        Cache::forget($cacheKey);
    }
}
