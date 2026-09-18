<?php

namespace App\Support;

use App\Models\Board;
use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class CurriculumLookup
{
    public static function grades(): Builder
    {
        return Grade::query()->active()->orderBy('number');
    }

    public static function subjects(?int $gradeId = null): Builder
    {
        $query = Subject::query()
            ->active()
            ->whereHas('grade', fn (Builder $q) => $q->active())
            ->orderBy('sort_order')
            ->orderBy('name_en');

        if ($gradeId) {
            $query->where('grade_id', $gradeId);
        }

        return $query;
    }

    public static function chapters(?int $subjectId = null): Builder
    {
        $query = Chapter::query()
            ->active()
            ->whereHas('subject', function (Builder $q) {
                $q->active()->whereHas('grade', fn (Builder $g) => $g->active());
            })
            ->orderBy('number');

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        return $query;
    }

    public static function topics(?int $chapterId = null): Builder
    {
        $query = Topic::query()
            ->active()
            ->whereHas('chapter', function (Builder $q) {
                $q->active()->whereHas('subject', function (Builder $s) {
                    $s->active()->whereHas('grade', fn (Builder $g) => $g->active());
                });
            })
            ->orderBy('sort_order')
            ->orderBy('code');

        if ($chapterId) {
            $query->where('chapter_id', $chapterId);
        }

        return $query;
    }

    public static function boards(): Builder
    {
        return Board::query()->active()->orderBy('sort_order')->orderBy('name');
    }

    public static function defaultBoardName(): string
    {
        return (string) (self::boards()->value('name') ?: '');
    }

    public static function activeGradeId(): array
    {
        return ['required', 'integer', self::existsActive('grades')];
    }

    public static function optionalActiveGradeId(): array
    {
        return ['nullable', 'integer', self::existsActive('grades')];
    }

    public static function activeSubjectId(?int $gradeId = null): array
    {
        return [
            'required',
            'integer',
            Rule::exists('subjects', 'id')->where(function ($query) use ($gradeId) {
                $query->where('is_active', true);
                if ($gradeId) {
                    $query->where('grade_id', $gradeId);
                }
            }),
        ];
    }

    public static function optionalActiveSubjectId(): array
    {
        return ['nullable', 'integer', self::existsActive('subjects')];
    }

    public static function activeChapterId(): array
    {
        return ['required', 'integer', self::existsActive('chapters')];
    }

    public static function optionalActiveChapterId(): array
    {
        return ['nullable', 'integer', self::existsActive('chapters')];
    }

    public static function optionalActiveTopicId(): array
    {
        return ['nullable', 'integer', self::existsActive('topics')];
    }

    public static function activeBoardName(bool $required = true): array
    {
        $rule = Rule::exists('boards', 'name')->where('is_active', true);

        return [$required ? 'required' : 'nullable', 'string', 'max:100', $rule];
    }

    public static function existsActive(string $table): Exists
    {
        return Rule::exists($table, 'id')->where('is_active', true);
    }
}
