<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\Question;
use App\Models\SavedPaper;
use App\Models\Subject;
use App\Models\User;
use App\Services\PaperExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageSchoolQuestionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'teacher', ?int $schoolId = null): User
    {
        $schoolId ??= Institution::create(['name' => 'School', 'license_type' => 'full', 'expiry_date' => now()->addYear(), 'access_expires_at' => now()->addYear()])->id;
        $user = User::factory()->create(['institution_id' => $schoolId]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function question(User $author, array $attributes = []): Question
    {
        $grade = Grade::firstOrCreate(['number' => 9], ['label_en' => 'Class 9', 'is_active' => true]);
        $subject = Subject::firstOrCreate(['grade_id' => $grade->id, 'name_en' => 'English'], ['is_active' => true]);
        $chapter = Chapter::firstOrCreate(['subject_id' => $subject->id, 'number' => 1], ['title_en' => 'Grammar', 'is_active' => true]);

        return Question::create(array_merge(['institution_id' => $author->institution_id, 'submitted_by' => $author->id,
            'chapter_id' => $chapter->id, 'type' => 'short', 'source' => 'additional', 'text_en' => 'Original question', 'is_active' => true], $attributes));
    }

    public function test_teachers_browse_school_questions_and_filter_their_own_without_changing_edit_permissions(): void
    {
        $teacher = $this->user();
        $colleague = $this->user('teacher', $teacher->institution_id);
        $mine = $this->question($teacher);
        $this->question($colleague);
        $this->question($this->user());
        $this->actingAs($teacher)->get('/questions')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Questions/Index')->has('questions.data', 2));
        $this->get('/questions?scope=mine')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('questions.data', 1)->where('questions.data.0.id', $mine->id)->where('questions.data.0.can_manage', true));
        $this->actingAs($this->user('institution_admin', $teacher->institution_id))->get('/questions')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('questions.data', 2));
        $this->get('/questions?search=nonexistent')->assertOk()->assertInertia(fn (Assert $page) => $page->has('questions.data', 0));
    }

    public function test_standalone_add_page_has_curriculum_before_any_questions_exist(): void
    {
        $teacher = $this->user();
        $question = $this->question($teacher);
        $chapterId = $question->chapter_id;
        $question->delete();
        $this->actingAs($teacher)->get('/questions/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Questions/Index')->where('initialMode', 'add')->has('entryChapters', 1)->where('entryChapters.0.id', $chapterId));
        $this->postJson('/questions', ['questions' => [
            ['chapter_id' => $chapterId, 'type' => 'short', 'text_en' => 'First row'],
            ['chapter_id' => $chapterId, 'type' => 'short', 'text_en' => 'Second row'],
        ]])->assertCreated()->assertJsonCount(2, 'questions');
        $this->get('/questions?scope=mine')->assertOk()->assertInertia(fn (Assert $page) => $page->has('questions.data', 2));
    }

    public function test_teacher_cannot_mutate_colleagues_other_schools_or_global_questions(): void
    {
        $teacher = $this->user();
        $colleague = $this->user('teacher', $teacher->institution_id);
        $questions = [$this->question($colleague), $this->question($this->user()), $this->question($teacher, ['submitted_by' => null])];
        foreach ($questions as $question) {
            $this->actingAs($teacher)->patchJson('/questions/'.$question->id, ['chapter_id' => $question->chapter_id, 'type' => 'short', 'text_en' => 'Changed'])->assertForbidden();
            $this->deleteJson('/questions/'.$question->id, ['confirmed' => true])->assertForbidden();
            $this->assertSame('Original question', $question->fresh()->text_en);
        }
    }

    public function test_author_and_school_admin_can_edit_without_changing_attribution(): void
    {
        $teacher = $this->user();
        $question = $this->question($teacher);
        foreach ([$teacher, $this->user('institution_admin', $teacher->institution_id)] as $user) {
            $this->actingAs($user)->patchJson('/questions/'.$question->id, [
                'chapter_id' => $question->chapter_id, 'type' => 'mcq', 'source' => 'exercise', 'text_en' => 'Updated question',
                'submitted_by' => $user->id + 999, 'institution_id' => 999,
                'mcq' => ['option_a_en' => 'A', 'option_b_en' => 'B', 'option_c_en' => 'C', 'option_d_en' => 'D', 'correct_option' => 'b'],
            ])->assertOk();
            $this->assertSame($teacher->id, $question->fresh()->submitted_by);
            $this->assertSame($teacher->institution_id, $question->fresh()->institution_id);
            $this->assertSame('b', $question->fresh()->mcqOptions->correct_option);
        }
        $this->patchJson('/questions/'.$question->id, ['chapter_id' => $question->chapter_id, 'type' => 'short', 'text_en' => 'Short'])->assertOk();
        $this->assertNull($question->fresh()->mcqOptions);
    }

    public function test_permanent_delete_requires_confirmation_and_school_admin_can_delete_teacher_question(): void
    {
        $teacher = $this->user();
        $question = $this->question($teacher);
        $part = $this->question($teacher, ['parent_question_id' => $question->id]);
        $this->actingAs($this->user('institution_admin', $teacher->institution_id))->deleteJson('/questions/'.$question->id)->assertUnprocessable();
        $response = $this->deleteJson('/questions/'.$question->id, ['confirmed' => true]);
        $this->assertSame(200, $response->status(), $response->getContent());
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        $this->assertDatabaseMissing('questions', ['id' => $part->id]);
    }

    public function test_new_and_legacy_papers_preserve_question_content_options_and_order_after_mutations(): void
    {
        $teacher = $this->user();
        $question = $this->question($teacher, ['type' => 'mcq']);
        $question->mcqOptions()->create(['option_a_en' => 'Original A', 'option_b_en' => 'Original B', 'option_c_en' => 'C', 'option_d_en' => 'D', 'correct_option' => 'b']);
        $second = $this->question($teacher, ['text_en' => 'Second question']);
        $newPaper = SavedPaper::create(['institution_id' => $teacher->institution_id, 'user_id' => $teacher->id, 'title' => 'New', 'config_snapshot' => ['question_ids' => [$second->id, $question->id]]]);
        $legacy = $newPaper->replicate();
        $legacy->title = 'Legacy';
        $legacy->save();
        DB::table('saved_papers')->where('id', $legacy->id)->update(['config_snapshot' => json_encode(['question_ids' => [$second->id, $question->id]])]);
        $this->actingAs($teacher)->patchJson('/questions/'.$question->id, ['chapter_id' => $question->chapter_id, 'type' => 'short', 'text_en' => 'Changed'])->assertOk();
        $this->deleteJson('/questions/'.$question->id, ['confirmed' => true])->assertOk();
        foreach ([$newPaper, $legacy] as $paper) {
            $preview = app(PaperExportService::class)->buildPreviewData($paper->fresh());
            $this->assertSame([$second->id, $question->id], $preview['questions']->pluck('id')->all());
            $saved = $preview['questions']->last();
            $this->assertSame('Original question', $saved->text_en);
            $this->assertSame('mcq', $saved->type);
            $this->assertSame('Original B', $saved->mcqOptions->option_b_en);
            $this->assertSame('b', $saved->mcqOptions->correct_option);
        }
    }

    public function test_school_admin_cannot_manage_another_school_and_invalid_edits_leave_content_unchanged(): void
    {
        $admin = $this->user('institution_admin');
        $foreign = $this->question($this->user());
        $this->actingAs($admin)->deleteJson('/questions/'.$foreign->id, ['confirmed' => true])->assertForbidden();
        $this->patchJson('/questions/'.$foreign->id, ['chapter_id' => $foreign->chapter_id, 'type' => 'short', 'text_en' => 'Changed'])->assertForbidden();
        $mine = $this->question($admin);
        $this->patchJson('/questions/'.$mine->id, ['chapter_id' => $mine->chapter_id, 'type' => 'short', 'text_en' => ''])->assertUnprocessable();
        $this->assertSame('Original question', $mine->fresh()->text_en);
    }

    public function test_legacy_paper_from_another_school_keeps_parts_diagrams_and_layout_overrides(): void
    {
        $teacher = $this->user();
        $question = $this->question($teacher, ['type' => 'long', 'has_parts' => true]);
        $part = $this->question($teacher, ['parent_question_id' => $question->id, 'diagram' => ['type' => 'image', 'image_url' => '/storage/example.png']]);
        $other = $this->user();
        $paper = SavedPaper::create(['institution_id' => $other->institution_id, 'user_id' => $other->id, 'title' => 'Shared question paper',
            'config_snapshot' => ['question_ids' => [$question->id]],
            'layout_snapshot' => ['paper_content' => ['sections' => [['questions' => [['id' => $question->id, 'text_en' => 'Paper-specific wording']]]]]]]);
        DB::table('saved_papers')->where('id', $paper->id)->update(['config_snapshot' => json_encode(['question_ids' => [(string) $question->id]])]);
        $this->actingAs($teacher)->deleteJson('/questions/'.$question->id, ['confirmed' => true])->assertOk();
        $saved = app(PaperExportService::class)->buildPreviewData($paper->fresh())['questions']->first();
        $this->assertSame('Paper-specific wording', $saved->text_en);
        $this->assertSame($part->id, $saved->parts->first()->id);
        $this->assertSame('/storage/example.png', $saved->parts->first()->diagram['image_url']);
        $copy = $paper->fresh()->replicate();
        $copy->save();
        $this->assertSame('Paper-specific wording', app(PaperExportService::class)->buildPreviewData($copy)['questions']->first()->text_en);
    }

    public function test_editing_parts_cannot_target_unrelated_questions(): void
    {
        $teacher = $this->user();
        $question = $this->question($teacher, ['has_parts' => true]);
        $foreign = $this->question($this->user());
        $this->actingAs($teacher)->patchJson('/questions/'.$question->id, ['questions' => [[
            'chapter_id' => $question->chapter_id, 'type' => 'short', 'text_en' => 'Changed parent',
            'parts' => [['id' => $foreign->id, 'text_en' => 'Malicious change']],
        ]]])->assertUnprocessable();
        $this->assertSame('Original question', $question->fresh()->text_en);
        $this->assertSame('Original question', $foreign->fresh()->text_en);
    }

    public function test_school_admin_retains_management_when_question_author_deletes_account(): void
    {
        $teacher = $this->user();
        $admin = $this->user('institution_admin', $teacher->institution_id);
        $question = $this->question($teacher);
        $teacher->delete();
        $this->assertNull($question->fresh()->submitted_by);
        $this->actingAs($admin)->get('/questions')->assertOk()->assertInertia(fn (Assert $page) => $page->has('questions.data', 1));
        $this->patchJson('/questions/'.$question->id, ['chapter_id' => $question->chapter_id, 'type' => 'short', 'text_en' => 'School corrected question'])->assertOk();
        $this->deleteJson('/questions/'.$question->id, ['confirmed' => true])->assertOk();
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    }

    public function test_bulk_entry_keeps_matching_text_in_different_chapters_and_question_types(): void
    {
        $teacher = $this->user();
        $question = $this->question($teacher);
        $secondChapter = Chapter::create(['subject_id' => $question->chapter->subject_id, 'number' => 2, 'title_en' => 'Second chapter', 'is_active' => true]);
        $this->actingAs($teacher)->postJson('/questions', ['questions' => [
            ['chapter_id' => $question->chapter_id, 'type' => 'short', 'text_en' => 'Define a noun.'],
            ['chapter_id' => $secondChapter->id, 'type' => 'short', 'text_en' => 'Define a noun.'],
            ['chapter_id' => $question->chapter_id, 'type' => 'long', 'text_en' => 'Define a noun.'],
        ]])->assertCreated()->assertJsonCount(3, 'questions');
    }

    public function test_class_and_subject_filters_stay_scoped_to_school_and_entry_permissions(): void
    {
        $teacher = $this->user();
        $mine = $this->question($teacher);
        $grade = Grade::create(['number' => 10, 'label_en' => 'Class 10', 'is_active' => true]);
        $subject = Subject::create(['grade_id' => $grade->id, 'name_en' => 'Science', 'is_active' => true]);
        $chapter = Chapter::create(['subject_id' => $subject->id, 'number' => 1, 'title_en' => 'Science unit', 'is_active' => true]);
        $second = $this->question($teacher, ['chapter_id' => $chapter->id]);
        $this->question($this->user(), ['chapter_id' => $chapter->id]);
        $teacher->teacherPermission()->create(['allowed_grades' => [9], 'allowed_subjects' => [$mine->chapter->subject_id]]);
        $this->actingAs($teacher)->get('/questions?grade_id='.$grade->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('questions.data', 0)->has('entryChapters', 1)->where('entryChapters.0.id', $mine->chapter_id));
        $this->get('/questions?subject_id='.$subject->id)->assertOk()->assertInertia(fn (Assert $page) => $page->has('questions.data', 0));
        $this->get('/questions')->assertOk()->assertInertia(fn (Assert $page) => $page->has('questions.data', 1)->where('questions.data.0.id', $mine->id));
        $this->get('/questions?grade_id='.$grade->id.'&chapter_id='.$mine->chapter_id)->assertOk()->assertInertia(fn (Assert $page) => $page->has('questions.data', 0));
    }
}
