<?php

namespace App\Services\AIImport;

use App\Models\Chapter;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CurriculumMatchService
{
    /**
     * @return array{chapter_id:?int, topic_id:?int, match_status:string}
     */
    public function match(int $subjectId, ?int $chapterNumber, ?string $chapterTitle, ?string $topicTitle): array
    {
        $chapters = Chapter::query()
            ->where('subject_id', $subjectId)
            ->orderBy('number')
            ->get(['id', 'number', 'title_en', 'title_ur']);

        $chapter = $this->findChapter($chapters, $chapterNumber, $chapterTitle);

        if (! $chapter) {
            return [
                'chapter_id' => null,
                'topic_id' => null,
                'match_status' => 'unmatched_chapter',
            ];
        }

        if (! $topicTitle) {
            return [
                'chapter_id' => $chapter->id,
                'topic_id' => null,
                'match_status' => 'matched',
            ];
        }

        $topic = $this->findTopic($chapter->id, $topicTitle);

        if (! $topic) {
            return [
                'chapter_id' => $chapter->id,
                'topic_id' => null,
                'match_status' => 'unmatched_topic',
            ];
        }

        return [
            'chapter_id' => $chapter->id,
            'topic_id' => $topic->id,
            'match_status' => 'matched',
        ];
    }

    protected function findChapter(Collection $chapters, ?int $chapterNumber, ?string $chapterTitle): ?Chapter
    {
        if ($chapterNumber) {
            $byNumber = $chapters->firstWhere('number', $chapterNumber);
            if ($byNumber) {
                return $byNumber;
            }
        }

        if (! $chapterTitle) {
            return null;
        }

        $needle = $this->normalize($chapterTitle);

        return $chapters->first(function (Chapter $chapter) use ($needle) {
            return $this->normalize($chapter->title_en) === $needle
                || ($chapter->title_ur && $this->normalize($chapter->title_ur) === $needle)
                || str_contains($this->normalize($chapter->title_en), $needle)
                || ($chapter->title_ur && str_contains($this->normalize($chapter->title_ur), $needle));
        });
    }

    protected function findTopic(int $chapterId, string $topicTitle): ?Topic
    {
        $needle = $this->normalize($topicTitle);

        return Topic::query()
            ->where('chapter_id', $chapterId)
            ->get(['id', 'title_en', 'title_ur', 'code'])
            ->first(function (Topic $topic) use ($needle) {
                return $this->normalize($topic->title_en) === $needle
                    || ($topic->title_ur && $this->normalize($topic->title_ur) === $needle)
                    || $this->normalize($topic->code) === $needle
                    || str_contains($this->normalize($topic->title_en), $needle);
            });
    }

    protected function normalize(?string $value): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
    }
}
