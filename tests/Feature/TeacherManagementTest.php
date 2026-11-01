<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\Question;
use App\Models\SavedPaper;
use App\Models\Subject;
use App\Models\TeacherPermission;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_permissions_can_be_saved_and_block_direct_requests_independently(): void
    {
        $owner = $this->owner();
        $fields = ['can_generate_paper', 'can_edit_paper', 'can_delete_paper', 'can_add_question', 'can_edit_question', 'can_delete_question'];
        $payload = ['name' => 'Restricted teacher', 'email' => 'restricted@example.test', 'password' => 'password123', 'allowed_grades' => null, 'allowed_subjects' => null, 'allowed_categories' => null] + array_fill_keys($fields, false);
        $this->actingAs($owner)->post('/admin/teachers', $payload)->assertSessionHasNoErrors();
        $teacher = User::where('email', 'restricted@example.test')->firstOrFail();
        foreach ($fields as $field) {
            $this->assertFalse($teacher->teacherPermission->$field);
        }
        $paper = SavedPaper::create(['institution_id' => $owner->institution_id, 'user_id' => $teacher->id, 'title' => 'My paper', 'config_snapshot' => []]);
        $this->actingAs($teacher)->post('/builder', [])->assertForbidden();
        $this->patch('/editor/'.$paper->id, [])->assertForbidden();
        $this->delete('/papers/'.$paper->id)->assertForbidden();
        $this->post('/papers/'.$paper->id.'/duplicate')->assertForbidden();
        $this->get('/questions/create')->assertForbidden();
        $this->post('/questions', [])->assertForbidden();
        $grade = Grade::create(['number' => 9, 'label_en' => 'Class 9', 'is_active' => true]);
        $subject = Subject::create(['grade_id' => $grade->id, 'name_en' => 'English', 'is_active' => true]);
        $chapter = Chapter::create(['subject_id' => $subject->id, 'number' => 1, 'title_en' => 'Grammar', 'is_active' => true]);
        $question = Question::create(['institution_id' => $owner->institution_id, 'submitted_by' => $teacher->id, 'chapter_id' => $chapter->id, 'type' => 'short', 'source' => 'additional', 'text_en' => 'Original', 'is_active' => true]);
        $this->patchJson('/questions/'.$question->id, ['chapter_id' => $chapter->id, 'type' => 'short', 'text_en' => 'Changed'])->assertForbidden();
        $this->deleteJson('/questions/'.$question->id, ['confirmed' => true])->assertForbidden();
        $this->get('/saved-papers')->assertOk();
        $this->get('/questions')->assertOk();
        $this->actingAs($owner)->patch('/admin/teachers/'.$teacher->id, array_replace($payload, array_fill_keys($fields, true)))->assertSessionHasNoErrors();
        foreach ($fields as $field) {
            $this->assertTrue($teacher->fresh()->teacherPermission->$field);
        }
        $this->actingAs($owner)->patch('/admin/teachers/'.$teacher->id, array_replace($payload, array_fill_keys($fields, true), ['can_generate_paper' => false, 'can_delete_question' => false]))->assertSessionHasNoErrors();
        $this->actingAs($teacher->fresh())->patchJson('/questions/'.$question->id, ['chapter_id' => $chapter->id, 'type' => 'short', 'source' => 'additional', 'text_en' => 'Allowed edit'])->assertOk();
        $this->deleteJson('/questions/'.$question->id, ['confirmed' => true])->assertForbidden();
        $this->post('/builder', [])->assertForbidden();
    }

    private function owner(): User
    {
        $school = Institution::create(['name' => 'School', 'expiry_date' => now()->addYear(), 'access_expires_at' => now()->addYear()]);
        $user = User::factory()->create(['institution_id' => $school->id]);
        $user->assignRole(Role::findOrCreate('institution_admin', 'web'));
        Role::findOrCreate('teacher', 'web');

        return $user;
    }

    public function test_owner_can_see_options_create_edit_revoke_and_delete_a_teacher(): void
    {
        $owner = $this->owner();
        Grade::create(['number' => 9, 'label_en' => 'Class 9', 'is_active' => true]);
        $this->actingAs($owner)->get('/admin/teachers')->assertOk()->assertInertia(fn (Assert $page) => $page->has('grades', 1)->has('subjects')->has('categories', 3));
        $data = ['name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'password123', 'allowed_grades' => [9], 'allowed_subjects' => null, 'allowed_categories' => ['exercise'], 'is_active' => true];
        $this->post('/admin/teachers', $data)->assertRedirect();
        $teacher = User::where('email', 'teacher@example.test')->firstOrFail();
        $this->patch("/admin/teachers/{$teacher->id}", array_merge($data, ['name' => 'Updated', 'allowed_grades' => [], 'password' => '']))->assertRedirect();
        $this->assertSame([], $teacher->fresh()->teacherPermission->allowed_grades);
        $this->actingAs($teacher)->get('/builder')->assertForbidden();
        $this->actingAs($owner)->patch("/admin/teachers/{$teacher->id}", array_merge($data, ['is_active' => false, 'password' => '']))->assertRedirect();
        $this->actingAs($teacher->fresh())->get('/builder')->assertForbidden();
        $this->actingAs($owner)->delete("/admin/teachers/{$teacher->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
    }

    public function test_owner_cannot_change_or_delete_another_schools_teacher(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $teacher = User::factory()->create(['institution_id' => $other->institution_id]);
        $teacher->assignRole('teacher');
        $this->actingAs($owner)->patch("/admin/teachers/{$teacher->id}", ['name' => 'Changed'])->assertNotFound();
        $this->delete("/admin/teachers/{$teacher->id}")->assertNotFound();
    }

    public function test_permission_scopes_cannot_be_bypassed_by_api_or_nested_paper_payloads(): void
    {
        $owner = $this->owner();
        $teacher = User::factory()->create(['institution_id' => $owner->institution_id]);
        $teacher->assignRole('teacher');
        TeacherPermission::create(['user_id' => $teacher->id, 'allowed_grades' => [9], 'allowed_subjects' => null, 'allowed_categories' => ['exercise']]);
        $chapters = [];
        foreach ([9, 10] as $number) {
            $grade = Grade::create(['number' => $number, 'label_en' => 'Class '.$number, 'is_active' => true]);
            $subject = Subject::create(['grade_id' => $grade->id, 'name_en' => 'English', 'is_active' => true]);
            $chapters[$number] = Chapter::create(['subject_id' => $subject->id, 'number' => 1, 'title_en' => 'Grammar', 'is_active' => true]);
        }
        $make = fn ($chapter, $source) => Question::create(['institution_id' => $owner->institution_id, 'submitted_by' => $teacher->id, 'chapter_id' => $chapter->id, 'source' => $source, 'type' => 'short', 'text_en' => 'Question', 'is_active' => true]);
        $allowed = $make($chapters[9], 'exercise');
        $wrongSource = $make($chapters[9], 'additional');
        $forbidden = $make($chapters[10], 'exercise');
        $topic = Topic::create(['chapter_id' => $chapters[10]->id, 'code' => '1.1', 'title_en' => 'Grammar', 'is_active' => true]);
        $this->actingAs($teacher)->getJson('/api/builder/questions/all?'.http_build_query(['chapter_ids' => [$chapters[9]->id]]))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $allowed->id);
        $this->getJson('/api/builder/questions/all?'.http_build_query(['chapter_ids' => [$chapters[9]->id], 'topic_ids' => [$topic->id]]))->assertForbidden();
        $this->post('/builder', ['title' => 'Paper', 'config' => ['question_ids' => [$forbidden->id]]])->assertForbidden();
        $this->post('/builder', ['title' => 'Paper', 'config' => ['question_ids' => [$wrongSource->id]]])->assertForbidden();
        $this->get('/questions')->assertInertia(fn (Assert $page) => $page->has('questions.data', 1));
        $this->postJson('/questions', ['chapter_id' => $chapters[9]->id, 'type' => 'short', 'source' => 'additional', 'text_en' => 'Forbidden source'])->assertForbidden();
        $this->patchJson('/questions/'.$allowed->id, ['chapter_id' => $chapters[9]->id, 'type' => 'short', 'source' => 'additional', 'text_en' => 'Changed source'])->assertForbidden();
    }

    public function test_deleting_a_teacher_with_saved_papers_keeps_their_work(): void
    {
        $owner = $this->owner();
        $teacher = User::factory()->create(['institution_id' => $owner->institution_id]);
        $teacher->assignRole('teacher');
        $paper = SavedPaper::create(['institution_id' => $owner->institution_id, 'user_id' => $teacher->id, 'title' => 'Saved work', 'config_snapshot' => ['question_ids' => []]]);
        $this->actingAs($owner)->delete("/admin/teachers/{$teacher->id}")->assertSessionHasErrors('teacher');
        $this->assertDatabaseHas('users', ['id' => $teacher->id]);
        $this->assertDatabaseHas('saved_papers', ['id' => $paper->id]);
    }
}
