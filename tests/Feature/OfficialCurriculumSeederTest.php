<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialCurriculumSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_grades_use_pectaa_book_subjects_without_fabricated_active_chapters(): void
    {
        $this->seed(\Database\Seeders\GradeSeeder::class);

        $grade = Grade::query()->where('number', 1)->firstOrFail();
        $english = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'English',
            'name_ur' => 'English',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $fakeChapter = Chapter::query()->create([
            'subject_id' => $english->id,
            'number' => 1,
            'title_en' => 'English Chapter 1',
            'title_ur' => 'English Chapter 1',
            'is_active' => true,
        ]);

        $this->seed(\Database\Seeders\CurriculumSeeder::class);

        $this->assertTrue($grade->fresh()->is_active);
        $this->assertDatabaseHas('subjects', [
            'grade_id' => $grade->id,
            'name_en' => 'Mathematics',
            'is_active' => true,
        ]);
        $this->assertFalse($fakeChapter->fresh()->is_active);
        $this->assertDatabaseHas('subjects', [
            'grade_id' => Grade::query()->where('number', 4)->value('id'),
            'name_en' => 'General Science',
            'is_active' => true,
        ]);
    }
}
