<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\SyllabusChapter;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PectaaVerifiedSyllabusSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_nine_english_2025_26_matches_pectaa_toc_and_marks_alp_exclusions(): void
    {
        $grade = Grade::query()->create([
            'number' => 9,
            'label_en' => 'Class 9',
            'label_ur' => 'جماعت 9',
            'is_active' => true,
        ]);
        $english = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'English',
            'name_ur' => 'انگریزی',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'Computer Science',
            'name_ur' => 'کمپیوٹر سائنس',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->seed(\Database\Seeders\Pectaa2025_26VerifiedSyllabusSeeder::class);

        $chapters = SyllabusChapter::query()
            ->with('edition')
            ->whereHas('edition', fn ($query) => $query->where('subject_id', $english->id))
            ->orderBy('chapter_number')
            ->get();
        $this->assertCount(11, $chapters);
        $this->assertSame('Daffodils (Poem)', $chapters[2]->chapter_title);
        $this->assertSame('current', $chapters[2]->status);
        $this->assertSame('PECTAA English 9 2025-26', $chapters[2]->edition->textbook_edition);
        $this->assertSame('deleted', $chapters[4]->status);
        $this->assertStringContainsString('unit remains in the textbook', strtolower($chapters[4]->change_notes));
        $this->assertSame('deleted', $chapters[7]->status);
        $this->assertSame('deleted', $chapters[9]->status);
        $this->assertSame('unverified', $chapters[0]->change_type);

        $computerChapters = SyllabusChapter::query()
            ->whereHas('edition', fn ($query) => $query->where('textbook_edition', 'PECTAA Computer Science 9 2025-26'))
            ->orderBy('chapter_number')
            ->get();
        $this->assertCount(12, $computerChapters);
        $this->assertSame('Introduction to Systems', $computerChapters[0]->chapter_title);
        $this->assertSame('Entrepreneurship in Digital Age', $computerChapters[11]->chapter_title);
    }
}
