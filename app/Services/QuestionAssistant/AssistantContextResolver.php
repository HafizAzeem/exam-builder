<?php

namespace App\Services\QuestionAssistant;

use App\Models\Grade;
use App\Models\Subject;
use App\Support\CurriculumLookup;
use Illuminate\Validation\ValidationException;

/** Deterministic detection only; ambiguity never incurs a provider call. */
class AssistantContextResolver
{
    public function proposal(array $data): ?array
    {
        $text = strtr(mb_substr($data['raw_text'] ?? '', 0, 1500), array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY), range(0, 9)));
        $grade = Grade::findOrFail($data['grade_id']);
        $subject = Subject::findOrFail($data['subject_id']);
        if (preg_match('/(?:class|grade|جماعت|کلاس)\s*[:#-]?\s*(1[012]|[1-9])(?!\d)/iu', $text, $match)) {
            $grade = CurriculumLookup::grades()->where('number', (int) $match[1])->first();
            if (! $grade) {
                $this->ambiguous('The mentioned class is unavailable. Choose an available class before submitting.');
            }
        }
        $subjects = CurriculumLookup::subjects($grade->id)->get();
        $mentioned = $subjects->filter(fn ($s) => preg_match('/(?<!\pL)'.preg_quote($s->name_en, '/').'(?!\pL)/iu', $text));
        if ($mentioned->count() > 1) {
            $this->ambiguous('Your instructions mention multiple subjects. Choose one subject for this request.');
        }
        $subject = $mentioned->first() ?? $subjects->first(fn ($s) => mb_strtolower($s->name_en) === mb_strtolower($subject->name_en));
        if (! $subject) {
            $this->ambiguous('The mentioned class has no matching subject. Change the class and subject in paper context.');
        }
        $chapters = CurriculumLookup::chapters($subject->id)->get(['id', 'number', 'title_en']);
        $ids = array_map('intval', $data['chapter_ids']);
        $changed = (int) $grade->id !== (int) $data['grade_id'] || (int) $subject->id !== (int) $data['subject_id'];
        if (preg_match('/(?:chapters?|units?|lessons?|باب|سبق)\s*[:#-]?\s*(\d+(?:\s*(?:,|and|&|to|[-–])\s*\d+)*)/iu', $text, $match)) {
            $numbers = [];
            $spec = preg_replace_callback('/(\d+)\s*(?:to|[-–])\s*(\d+)/i', function ($m) {
                if ((int) $m[2] < (int) $m[1] || (int) $m[2] - (int) $m[1] > 30) {
                    $this->ambiguous('Choose a valid chapter range.');
                }

                return implode(',', range((int) $m[1], (int) $m[2]));
            }, $match[1]);
            preg_match_all('/\d+/', $spec, $found);
            $numbers = array_unique(array_map('intval', $found[0]));
            $matched = $chapters->whereIn('number', $numbers);
            if ($matched->count() !== count($numbers)) {
                $this->ambiguous('Some mentioned chapters are not available in this subject. Check the syllabus and chapter numbers.');
            }
            $ids = $matched->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $matched = $chapters->filter(fn ($c) => mb_strlen($c->title_en) >= 5 && mb_stripos($text, $c->title_en) !== false);
            if ($matched->isNotEmpty()) {
                $ids = $matched->pluck('id')->map(fn ($id) => (int) $id)->all();
            } elseif ($changed) {
                $this->ambiguous('You mentioned a different class or subject. Also specify its chapter/unit numbers, or change paper context first.');
            }
        }
        $old = array_map('intval', $data['chapter_ids']);
        sort($old);
        $sorted = $ids;
        sort($sorted);
        if (! $changed && $old === $sorted) {
            return null;
        }

        return ['grade' => ['id' => $grade->id, 'label_en' => $grade->label_en], 'subject' => ['id' => $subject->id, 'name_en' => $subject->name_en], 'chapter_ids' => $ids, 'chapters' => $chapters->whereIn('id', $ids)->values()->all(), 'message' => 'Your instructions specify a different curriculum context. Confirm it before using a school request.', 'description' => $grade->label_en.' · '.$subject->name_en.' · '.$chapters->whereIn('id', $ids)->map(fn ($c) => $c->number.'. '.$c->title_en)->implode(', '), 'text_hash' => hash('sha256', $data['raw_text'] ?? '')];
    }

    private function ambiguous(string $message): never
    {
        throw ValidationException::withMessages(['raw_text' => $message]);
    }
}
