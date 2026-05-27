<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\Question;
use App\Models\SavedPaper;
use Illuminate\Support\Collection;

class PaperExportService
{
    public function __construct(
        protected BubbleSheetService $bubbleSheetService,
    ) {}

    public function buildPreviewData(SavedPaper $paper, ?Institution $institution = null): array
    {
        $config = $paper->config_snapshot ?? [];
        $layout = array_merge($this->defaultLayout(), $paper->layout_snapshot ?? []);
        $questionIds = $config['question_ids'] ?? [];

        $questions = Question::query()
            ->with(['mcqOptions', 'pastPaperTag', 'parts'])
            ->whereIn('id', $questionIds)
            ->get()
            ->sortBy(fn ($q) => array_search($q->id, $questionIds))
            ->values();

        $mcqs = $questions->where('type', 'mcq')->values();

        return [
            'paper' => $paper,
            'institution' => $institution,
            'config' => $config,
            'layout' => $layout,
            'questions' => $questions,
            'sections' => $this->groupBySection($questions),
            'omr_rows' => ($layout['enable_omr'] ?? false)
                ? $this->bubbleSheetService->generate($mcqs)
                : [],
            'answer_key' => ($layout['enable_answer_key'] ?? false)
                ? $this->buildAnswerKey($mcqs)
                : [],
        ];
    }

    protected function defaultLayout(): array
    {
        return [
            'header_template' => 1,
            'font_family' => 'Arial',
            'font_size' => 12,
            'font_color' => '#000000',
            'line_height' => 1.5,
            'dual_column' => false,
            'dual_medium' => false,
            'show_past_paper_tags' => false,
            'enable_omr' => false,
            'enable_answer_key' => false,
            'enable_watermark' => false,
            'watermark_text' => '',
            'watermark_opacity' => 0.1,
            'watermark_angle' => 45,
            'margins' => ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15],
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'scale' => 100,
        ];
    }

    protected function groupBySection(Collection $questions): array
    {
        $labels = [
            'mcq' => 'Section A: Multiple Choice Questions',
            'short' => 'Section B: Short Questions',
            'long' => 'Section C: Long Questions',
            'fill' => 'Section D: Fill in the Blanks',
            'truefalse' => 'Section E: True / False',
        ];

        $grouped = [];

        foreach ($questions->groupBy('type') as $type => $items) {
            $grouped[] = [
                'type' => $type,
                'title' => $labels[$type] ?? ucfirst($type),
                'questions' => $items->values(),
            ];
        }

        return $grouped;
    }

    /**
     * @return array<int, array{number: int, answer: string}>
     */
    protected function buildAnswerKey(Collection $mcqs): array
    {
        $key = [];
        $number = 1;

        foreach ($mcqs as $question) {
            $key[] = [
                'number' => $number++,
                'answer' => strtoupper($question->mcqOptions?->correct_option ?? ''),
            ];
        }

        return $key;
    }
}
