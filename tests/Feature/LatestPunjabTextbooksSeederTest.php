<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\SyllabusChapter;
use App\Models\SyllabusEdition;
use App\Support\CurriculumLookup;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\LatestPunjabTextbooksSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LatestPunjabTextbooksSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reruns_preserve_verified_syllabus_records(): void
    {
        $this->seed(LatestPunjabTextbooksSeeder::class);
        $edition = SyllabusEdition::query()->firstOrFail();
        $chapter = SyllabusChapter::query()->where('syllabus_edition_id', $edition->id)->firstOrFail();
        $edition->update(['status' => 'current', 'verified_at' => '2026-10-24']);
        $chapter->update(['status' => 'deleted', 'change_type' => 'deleted', 'verified_at' => '2026-10-24', 'change_notes' => 'Verified examination exclusion']);

        $this->seed(LatestPunjabTextbooksSeeder::class);

        $this->assertSame('current', $edition->fresh()->status);
        $this->assertSame('deleted', $chapter->fresh()->status);
        $this->assertSame('Verified examination exclusion', $chapter->fresh()->change_notes);
    }

    public function test_latest_computer_science_name_preserves_legacy_subject_id(): void
    {
        $this->seed(GradeSeeder::class);
        $grade = Grade::query()->where('number', 9)->firstOrFail();
        $subject = Subject::query()->create([
            'grade_id' => $grade->id, 'name_en' => 'Computer Science', 'name_ur' => 'Computer Science',
            'sort_order' => 1, 'is_active' => true,
        ]);

        $this->seed(LatestPunjabTextbooksSeeder::class);

        $this->assertSame('Computer Science and Entrepreneurship', $subject->fresh()->name_en);
        $this->assertSame(9, CurriculumLookup::chapters($subject->id)->count());
        $this->assertSame(0, CurriculumLookup::subjects($grade->id)->where('name_en', 'Computer Science')->count());
    }

    public function test_every_published_book_has_contents_and_default_seed_order_keeps_latest_titles(): void
    {
        $books = json_decode(file_get_contents(database_path('seeders/data/punjab-latest-textbooks.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->seed(DatabaseSeeder::class);

        foreach ($books as $book) {
            $label = "Class {$book['grade']} {$book['subject']}";
            $this->assertNotEmpty($book['chapters'], $label);
            $grade = Grade::query()->where('number', $book['grade'])->firstOrFail();
            $subject = CurriculumLookup::subjects($grade->id)->where('name_en', $book['subject'])->firstOrFail();
            $actual = CurriculumLookup::chapters($subject->id)->pluck('title_en', 'number')->all();
            $expected = [];
            foreach ($book['chapters'] as $offset => $chapter) {
                $expected[is_array($chapter) ? ($chapter['number'] ?? $offset + 1) : $offset + 1] = is_array($chapter) ? $chapter['en'] : $chapter;
            }
            $this->assertSame($expected, $actual, $label);
        }
    }

    public function test_legacy_optional_subject_names_reuse_their_existing_records(): void
    {
        $this->seed(GradeSeeder::class);
        $subjects = [];
        foreach ([[6, 'Art and Drawing', 'Art and Model Drawing'], [11, 'Persian', 'Farsi'], [12, 'Philosophy', 'Logic']] as [$number, $old, $latest]) {
            $subjects[] = [Subject::query()->create([
                'grade_id' => Grade::query()->where('number', $number)->value('id'),
                'name_en' => $old, 'name_ur' => $old, 'sort_order' => 1, 'is_active' => true,
            ]), $latest];
        }

        $this->seed(LatestPunjabTextbooksSeeder::class);

        foreach ($subjects as [$subject, $latest]) {
            $this->assertSame($latest, $subject->fresh()->name_en);
        }
    }

    public function test_latest_contents_are_available_in_the_paper_builder_and_reruns_preserve_ids(): void
    {
        $this->seed(GradeSeeder::class);
        $grade = Grade::query()->where('number', 1)->firstOrFail();
        $subject = Subject::query()->create([
            'grade_id' => $grade->id, 'name_en' => 'English', 'name_ur' => 'English',
            'sort_order' => 1, 'is_active' => true,
        ]);
        $chapter = Chapter::query()->create([
            'subject_id' => $subject->id, 'number' => 2,
            'title_en' => 'English Chapter 2', 'title_ur' => 'English Chapter 2', 'is_active' => true,
        ]);
        $obsolete = Chapter::query()->create([
            'subject_id' => $subject->id, 'number' => 99,
            'title_en' => 'Old chapter', 'title_ur' => 'Old chapter', 'is_active' => true,
        ]);

        $this->seed(LatestPunjabTextbooksSeeder::class);

        $this->assertSame('My Family and I', $chapter->fresh()->title_en);
        $this->assertFalse($obsolete->fresh()->is_active);
        $this->assertContains('My Family and I', CurriculumLookup::chapters($subject->id)->pluck('title_en'));
        $chapterCount = Chapter::query()->count();
        $subjectCount = Subject::query()->count();

        $this->seed(LatestPunjabTextbooksSeeder::class);

        $this->assertSame($chapter->id, $subject->chapters()->where('number', 2)->value('id'));
        $this->assertSame($chapterCount, Chapter::query()->count());
        $this->assertSame($subjectCount, Subject::query()->count());
    }

    public function test_all_twelve_grades_have_current_books_and_actual_chapter_titles(): void
    {
        $this->seed(GradeSeeder::class);
        $this->seed(LatestPunjabTextbooksSeeder::class);

        $this->assertSame(12, CurriculumLookup::grades()->count());
        foreach (range(1, 12) as $number) {
            $grade = Grade::query()->where('number', $number)->firstOrFail();
            $english = CurriculumLookup::subjects($grade->id)->where('name_en', 'English')->firstOrFail();
            $this->assertGreaterThan(0, CurriculumLookup::chapters($english->id)->count(), "Class {$number} English contents missing");
        }
        $this->assertDatabaseHas('syllabus_editions', ['textbook_edition' => 'English 10 (2026-27)']);
        $this->assertDatabaseHas('syllabus_editions', ['textbook_edition' => 'English (27.08.2026)']);
        $this->assertDatabaseHas('syllabus_editions', ['textbook_edition' => 'Urdu (15.10.2026)']);
        $this->assertSame(0, CurriculumLookup::chapters()->where('title_en', 'like', '%Prose Lesson%')->count());
    }

    public function test_existing_class_labels_and_activation_are_updated_without_creating_new_grades(): void
    {
        $grade = Grade::query()->create([
            'number' => 1, 'label_en' => 'Old class name', 'label_ur' => 'Old name', 'is_active' => false,
        ]);

        $this->seed(GradeSeeder::class);

        $this->assertSame('Class 1', $grade->fresh()->label_en);
        $this->assertTrue($grade->fresh()->is_active);
        $this->assertSame(12, Grade::query()->count());
    }

    public function test_subject_aliases_are_updated_without_duplicate_active_subjects(): void
    {
        $this->seed(GradeSeeder::class);
        $grade = Grade::query()->where('number', 1)->firstOrFail();
        $subject = Subject::query()->create([
            'grade_id' => $grade->id, 'name_en' => 'Islamiat', 'name_ur' => 'اسلامیات',
            'sort_order' => 1, 'is_active' => true,
        ]);

        $this->seed(LatestPunjabTextbooksSeeder::class);

        $this->assertSame('Islamiyat', $subject->fresh()->name_en);
        $this->assertSame(1, CurriculumLookup::subjects($grade->id)->whereIn('name_en', ['Islamiyat', 'Islamiat'])->count());
    }

    public function test_printed_chapter_numbers_are_preserved_and_obsolete_numbers_are_retired(): void
    {
        $this->seed(GradeSeeder::class);
        $grade = Grade::query()->where('number', 10)->firstOrFail();
        $subject = Subject::query()->create([
            'grade_id' => $grade->id, 'name_en' => 'Chemistry', 'name_ur' => 'کیمسٹری',
            'sort_order' => 1, 'is_active' => true,
        ]);
        $old = Chapter::query()->create([
            'subject_id' => $subject->id, 'number' => 1,
            'title_en' => 'Old chemistry chapter', 'title_ur' => 'Old chemistry chapter', 'is_active' => true,
        ]);

        $this->seed(LatestPunjabTextbooksSeeder::class);

        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame(14, CurriculumLookup::chapters($subject->id)->firstOrFail()->number);
        $this->assertDatabaseHas('chapters', [
            'subject_id' => $subject->id, 'number' => 14,
            'title_en' => 'States of Matter and Phase Changes', 'is_active' => true,
        ]);
    }
}
