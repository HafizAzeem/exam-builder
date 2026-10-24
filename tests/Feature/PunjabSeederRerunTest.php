<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\SyllabusChapter;
use App\Models\SyllabusEdition;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PunjabSeederRerunTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_seed_reruns_preserve_subject_ids_without_recreating_alias_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $subjects = Subject::query()->orderBy('id')->get(['id', 'grade_id', 'name_en'])->toArray();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($subjects, Subject::query()->orderBy('id')->get(['id', 'grade_id', 'name_en'])->toArray());
    }

    public function test_full_seed_reruns_preserve_verified_historical_english_exclusions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $edition = SyllabusEdition::query()->where('academic_session', '2025-2026')
            ->where('textbook_edition', 'English 1')->where('group_name', 'Compulsory')->firstOrFail();
        $chapter = SyllabusChapter::query()->where('syllabus_edition_id', $edition->id)->firstOrFail();
        $edition->update(['status' => 'current', 'verified_at' => '2026-10-24']);
        $chapter->update(['status' => 'deleted', 'change_type' => 'deleted', 'verified_at' => '2026-10-24', 'change_notes' => 'Verified exclusion']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('current', $edition->fresh()->status);
        $this->assertSame('2026-10-24', $edition->fresh()->verified_at->toDateString());
        $this->assertSame('deleted', $chapter->fresh()->status);
        $this->assertSame('Verified exclusion', $chapter->fresh()->change_notes);
    }

    public function test_full_seed_preserves_verified_chapters_in_an_unverified_historical_edition(): void
    {
        $this->seed(DatabaseSeeder::class);
        $edition = SyllabusEdition::query()->where('academic_session', '2025-2026')
            ->where('textbook_edition', 'English 1')->where('group_name', 'Compulsory')->firstOrFail();
        $chapter = SyllabusChapter::query()->where('syllabus_edition_id', $edition->id)->firstOrFail();
        $chapter->update(['status' => 'deleted', 'change_type' => 'deleted', 'verified_at' => '2026-10-24', 'change_notes' => 'Reviewed chapter exclusion']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('deleted', $chapter->fresh()->status);
        $this->assertSame('Reviewed chapter exclusion', $chapter->fresh()->change_notes);
    }

    public function test_verified_grade_nine_snapshots_keep_later_review_decisions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $editions = SyllabusEdition::query()->whereIn('textbook_edition', [
            'PECTAA English 9 2025-26', 'PECTAA Computer Science 9 2025-26',
        ])->get();
        $this->assertCount(2, $editions);
        $english = $editions->firstWhere('textbook_edition', 'PECTAA English 9 2025-26');
        $this->assertSame([5, 8, 10], $english->chapters()->where('status', 'deleted')->orderBy('chapter_number')->pluck('chapter_number')->all());
        foreach ($editions as $edition) {
            $edition->update(['status' => 'revised', 'verified_at' => '2026-10-24']);
            $edition->chapters()->where('chapter_number', 1)->update([
                'status' => 'deleted', 'change_type' => 'deleted', 'verified_at' => '2026-10-24',
                'change_notes' => 'Later verified exclusion',
            ]);
        }

        $this->seed(DatabaseSeeder::class);

        foreach ($editions as $edition) {
            $this->assertSame('revised', $edition->fresh()->status);
            $chapter = $edition->chapters()->where('chapter_number', 1)->firstOrFail();
            $this->assertSame('deleted', $chapter->status);
            $this->assertSame('Later verified exclusion', $chapter->change_notes);
        }
    }
}
