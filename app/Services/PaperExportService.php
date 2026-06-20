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
        $savedLayout = $paper->layout_snapshot ?? [];
        $layout = $this->mergeLayoutWithPaperConfig(
            array_merge($this->defaultLayout(), $savedLayout),
            $config,
            $savedLayout,
        );

        $questionIds = $config['question_ids'] ?? [];

        $questions = Question::query()
            ->with(['mcqOptions', 'pastPaperTag', 'parts'])
            ->whereIn('id', $questionIds)
            ->get()
            ->sortBy(fn ($q) => array_search($q->id, $questionIds))
            ->values();

        if (! empty($layout['paper_content'])) {
            $questions = $this->applyPaperContentToQuestions($questions, $layout['paper_content']);
        }

        $mcqs = $questions->where('type', 'mcq')->values();

        if (($layout['enable_watermark'] ?? false) && empty($layout['watermark_text'])) {
            $layout['watermark_text'] = $this->buildWatermarkText($institution, $paper->institute_snapshot);
        }

        if (! empty($layout['watermark_image_path'])) {
            $layout['watermark_image_url'] = '/storage/'.$layout['watermark_image_path'];
        }

        $examMeta = $config['exam_meta'] ?? [];
        if (! empty($layout['paper_content']['header'])) {
            $examMeta = array_merge($examMeta, $this->examMetaFromPaperContent($layout['paper_content']['header']));
        }

        return [
            'paper' => $paper,
            'institution' => $this->institutionWithPaperContent($institution, $paper->institute_snapshot, $layout['paper_content']['header'] ?? null),
            'config' => $config,
            'exam_meta' => $examMeta,
            'settings' => $config['settings'] ?? [],
            'layout' => $layout,
            'questions' => $questions,
            'sections' => $this->groupBySection($questions),
            'omr_rows' => $this->bubbleSheetService->generate($mcqs),
            'answer_key' => $this->buildAnswerKey($mcqs),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    protected function buildWatermarkText(?Institution $institution, ?array $snapshot = null): string
    {
        $name = strtoupper((string) ($institution?->name ?? $snapshot['name'] ?? ''));
        $address = trim((string) ($institution?->address ?? $snapshot['address'] ?? ''));
        $city = trim((string) ($institution?->city ?? $snapshot['city'] ?? ''));
        $phone = trim((string) ($institution?->phone ?? $snapshot['phone'] ?? ''));

        $location = collect([$address, $city])->filter()->implode(', ');
        $lines = array_filter([
            $name,
            $location !== '' ? strtoupper($location) : null,
            $phone !== '' ? 'PH: '.$phone : null,
        ]);

        return implode("\n", $lines);
    }

    /**
     * Most paper options come from the builder wizard (config_snapshot).
     * Dual medium can also be toggled in the layout editor (saved in layout_snapshot).
     *
     * @param  array<string, mixed>  $layout
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $savedLayout
     * @return array<string, mixed>
     */
    protected function mergeLayoutWithPaperConfig(array $layout, array $config, array $savedLayout): array
    {
        $settings = $config['settings'] ?? [];

        if (array_key_exists('dual_medium', $savedLayout)) {
            $layout['dual_medium'] = (bool) $savedLayout['dual_medium'];
        } elseif (array_key_exists('dual_medium', $config)) {
            $layout['dual_medium'] = (bool) $config['dual_medium'];
        }

        foreach (['show_past_paper_tags', 'enable_omr', 'enable_answer_key', 'enable_watermark'] as $key) {
            if (array_key_exists($key, $settings)) {
                $layout[$key] = (bool) $settings[$key];
            }
        }

        if (in_array($layout['watermark_type'] ?? 'none', ['text', 'image'], true)) {
            $layout['enable_watermark'] = true;
        }

        return $layout;
    }

    protected function defaultLayout(): array
    {
        return [
            'header_template' => 1,
            'font_family' => 'Arial',
            'font_size' => 11,
            'heading_font_size' => 12,
            'font_weight' => 'normal',
            'font_color' => '#000000',
            'line_height' => 1.5,
            'show_note' => true,
            'show_paper_note' => true,
            'watermark_type' => 'none',
            'dual_column' => false,
            'dual_medium' => false,
            'show_past_paper_tags' => false,
            'enable_omr' => false,
            'omr_columns' => 2,
            'enable_answer_key' => false,
            'enable_watermark' => false,
            'watermark_text' => '',
            'watermark_opacity' => 0.18,
            'watermark_angle' => 45,
            'watermark_size' => 22,
            'watermark_image_path' => '',
            'watermark_image_size' => 50,
            'margins' => ['top' => 15, 'right' => 15, 'bottom' => 15, 'left' => 15],
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'page_view' => 'single',
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

        $sectionNumber = 1;

        foreach ($questions->groupBy('type') as $type => $items) {
            $count = $items->count();
            $grouped[] = [
                'type' => $type,
                'number' => $sectionNumber++,
                'title' => $labels[$type] ?? ucfirst($type),
                'questions' => $items->values(),
                'question_count' => $count,
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

    /**
     * @param  array<string, mixed>  $paperContent
     */
    protected function applyPaperContentToQuestions(Collection $questions, array $paperContent): Collection
    {
        $overrides = [];

        foreach ($paperContent['sections'] ?? [] as $section) {
            foreach ($section['questions'] ?? [] as $question) {
                if (! empty($question['id'])) {
                    $overrides[(int) $question['id']] = $question;
                }
            }
        }

        return $questions->map(function ($question) use ($overrides) {
            $override = $overrides[$question->id] ?? null;

            if (! $override) {
                return $question;
            }

            if (isset($override['text_en'])) {
                $question->text_en = $override['text_en'];
            }

            if (isset($override['text_ur'])) {
                $question->text_ur = $override['text_ur'];
            }

            if ($question->mcqOptions && ! empty($override['options'])) {
                $opts = $override['options'];
                $map = [
                    'A' => ['option_a_en', 'option_a_ur'],
                    'B' => ['option_b_en', 'option_b_ur'],
                    'C' => ['option_c_en', 'option_c_ur'],
                    'D' => ['option_d_en', 'option_d_ur'],
                ];

                foreach ($map as $key => [$enField, $urField]) {
                    if (isset($opts[$key]['en'])) {
                        $question->mcqOptions->{$enField} = $opts[$key]['en'];
                    }
                    if (isset($opts[$key]['ur'])) {
                        $question->mcqOptions->{$urField} = $opts[$key]['ur'];
                    }
                }
            }

            if (! empty($override['parts']) && $question->relationLoaded('parts')) {
                foreach ($question->parts as $idx => $part) {
                    $partOverride = $override['parts'][$idx] ?? null;
                    if (! $partOverride) {
                        continue;
                    }
                    if (isset($partOverride['text_en'])) {
                        $part->text_en = $partOverride['text_en'];
                    }
                    if (isset($partOverride['text_ur'])) {
                        $part->text_ur = $partOverride['text_ur'];
                    }
                }
            }

            return $question;
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @return array<string, mixed>
     */
    protected function examMetaFromPaperContent(array $header): array
    {
        return array_filter([
            'class' => $header['class'] ?? null,
            'subject' => $header['subject'] ?? null,
            'time' => $header['paper_time'] ?? null,
            'marks' => $header['marks'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>|null  $header
     */
    protected function institutionWithPaperContent(?Institution $institution, ?array $snapshot, ?array $header): ?Institution
    {
        if (! $header) {
            return $institution;
        }

        $data = $institution
            ? $institution->only(['name', 'logo_path', 'address', 'city', 'phone'])
            : ($snapshot ?? []);

        if (! empty($header['institute_name'])) {
            $data['name'] = $header['institute_name'];
        }

        if (! empty($header['institute_address'])) {
            $data['address'] = $header['institute_address'];
        }

        if ($institution) {
            $institution->forceFill($data);

            return $institution;
        }

        return Institution::make($data);
    }
}
