<?php

namespace Database\Seeders\Concerns;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;

trait SeedsSubjectChapters
{
    /**
     * @param  list<array{en: string, ur: string, aliases?: list<string>}>  $subjects
     * @return list<int>
     */
    protected function syncSubjects(Grade $grade, array $subjects): array
    {
        $keepIds = [];
        $sort = 1;

        foreach ($subjects as $item) {
            $names = array_merge([$item['en']], $item['aliases'] ?? []);

            $subject = Subject::query()
                ->where('grade_id', $grade->id)
                ->whereIn('name_en', $names)
                ->first();

            if (! $subject) {
                $subject = Subject::query()->create([
                    'grade_id' => $grade->id,
                    'name_en' => $item['en'],
                    'name_ur' => $item['ur'],
                    'sort_order' => $sort,
                    'is_active' => true,
                ]);
            } else {
                $subject->update([
                    'name_en' => $item['en'],
                    'name_ur' => $item['ur'],
                    'sort_order' => $sort,
                    'is_active' => true,
                ]);
            }

            $keepIds[] = $subject->id;
            $sort++;
        }

        Subject::query()
            ->where('grade_id', $grade->id)
            ->whereNotIn('id', $keepIds)
            ->update(['is_active' => false]);

        return $keepIds;
    }

    /**
     * @param  array<int, array{en: string, ur?: string|null}>|array<int, string>  $chapters
     */
    protected function syncChapters(Subject $subject, array $chapters): void
    {
        $max = 0;

        foreach ($chapters as $number => $chapter) {
            $number = (int) $number;
            $max = max($max, $number);

            if (is_string($chapter)) {
                $titleEn = $chapter;
                $titleUr = null;
            } else {
                $titleEn = $chapter['en'];
                $titleUr = $chapter['ur'] ?? null;
            }

            Chapter::query()->updateOrCreate(
                [
                    'subject_id' => $subject->id,
                    'number' => $number,
                ],
                [
                    'title_en' => $titleEn,
                    'title_ur' => $titleUr ?: $titleEn,
                    'is_active' => true,
                ]
            );
        }

        if ($max > 0) {
            Chapter::query()
                ->where('subject_id', $subject->id)
                ->where('number', '>', $max)
                ->update(['is_active' => false]);
        }
    }

    protected function subjectByName(Grade $grade, string $nameEn): ?Subject
    {
        return Subject::query()
            ->where('grade_id', $grade->id)
            ->where('name_en', $nameEn)
            ->first();
    }
}
