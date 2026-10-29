<?php

namespace Tests\Unit;

use App\Models\SavedPaper;
use App\Services\PaperExportService;
use App\Support\PaperTemplates;
use Tests\TestCase;

class PaperTemplatesTest extends TestCase
{
    public function test_export_preserves_every_supported_layout_and_its_details(): void
    {
        foreach ([1, 'board', 'federal', 'academy', 'urdu', 'entry'] as $id) {
            $paper = new SavedPaper([
                'title' => 'Practice',
                'config_snapshot' => ['question_records' => [], 'exam_meta' => ['class' => '10', 'subject' => 'Physics']],
                'layout_snapshot' => ['header_template' => $id, 'template_meta' => ['batch' => 'Evening', 'version' => 'B']],
            ]);
            $preview = app(PaperExportService::class)->buildPreviewData($paper);
            $this->assertSame($id, $preview['layout']['header_template']);
            $this->assertSame('Evening', $preview['layout']['template_meta']['batch']);
        }
    }

    public function test_removed_or_invalid_template_ids_fall_back_to_original(): void
    {
        foreach ([2, 3, 4, 5, 6, 7, 'unknown', null, [], '1'] as $id) {
            $this->assertSame(1, PaperTemplates::normalize($id));
        }
    }
}
