<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\McqOption;
use App\Models\PastPaperTag;
use App\Models\Question;
use App\Models\SavedPaper;
use App\Models\Subject;
use Illuminate\Support\Collection;

final class SavedQuestionSnapshot
{
    public static function capture(array $ids): array
    {
        return Question::with(['mcqOptions', 'pastPaperTag', 'parts.mcqOptions', 'parts.pastPaperTag', 'chapter.subject.grade'])
            ->whereIn('id', $ids)->get()->sortBy(fn ($question) => array_search($question->id, $ids))
            ->values()->toArray();
    }

    public static function questions(array $rows): Collection
    {
        return collect($rows)->map(function ($row) {
            $relations = ['mcq_options', 'past_paper_tag', 'parts', 'chapter'];
            $question = (new Question)->forceFill(array_diff_key($row, array_flip($relations)));
            $question->setRelation('mcqOptions', empty($row['mcq_options']) ? null : (new McqOption)->forceFill($row['mcq_options']));
            $question->setRelation('pastPaperTag', empty($row['past_paper_tag']) ? null : (new PastPaperTag)->forceFill($row['past_paper_tag']));
            $question->setRelation('parts', self::questions($row['parts'] ?? []));
            $chapter = null;
            if (! empty($row['chapter'])) {
                $chapter = (new Chapter)->forceFill(array_diff_key($row['chapter'], ['subject' => true]));
                $subjectData = $row['chapter']['subject'] ?? null;
                $subject = $subjectData ? (new Subject)->forceFill(array_diff_key($subjectData, ['grade' => true])) : null;
                $subject?->setRelation('grade', empty($subjectData['grade']) ? null : (new Grade)->forceFill($subjectData['grade']));
                $chapter->setRelation('subject', $subject);
            }
            $question->setRelation('chapter', $chapter);

            return $question;
        });
    }

    public static function preserveLegacyPapers(array $ids): void
    {
        SavedPaper::whereNull('config_snapshot->question_records')->where(function ($query) use ($ids) {
            foreach ($ids as $id) {
                $query->orWhereJsonContains('config_snapshot->question_ids', (int) $id)
                    ->orWhereJsonContains('config_snapshot->question_ids', (string) $id);
            }
        })->lockForUpdate()->chunkById(100, function ($papers) {
            foreach ($papers as $paper) {
                $config = $paper->config_snapshot ?? [];
                $config['question_records'] = self::capture($config['question_ids'] ?? []);
                $paper->update(['config_snapshot' => $config]);
            }
        });
    }
}
