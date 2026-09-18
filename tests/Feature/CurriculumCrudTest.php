<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CurriculumCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['_token' => 'testing']);
        $this->withHeader('X-CSRF-TOKEN', 'testing');
    }

    protected function makeSuperAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_super_admin_can_manage_classes_and_hide_inactive_from_lookups(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)
            ->post(route('super-admin.curriculum.grades.store'), [
                '_token' => 'testing',
                'number' => 8,
                'label_en' => 'Class 8',
                'label_ur' => 'جماعت 8',
                'is_active' => true,
            ])
            ->assertRedirect();

        $grade = Grade::query()->where('number', 8)->first();
        $this->assertNotNull($grade);
        $this->assertTrue($grade->is_active);

        $this->actingAs($user)
            ->post(route('super-admin.curriculum.subjects.store', $grade), [
                '_token' => 'testing',
                'name_en' => 'Mathematics',
                'name_ur' => 'ریاضی',
                'is_active' => true,
            ])
            ->assertRedirect();

        $subject = Subject::query()->where('grade_id', $grade->id)->first();
        $this->assertNotNull($subject);

        $this->actingAs($user)
            ->post(route('super-admin.curriculum.chapters.store', $subject), [
                '_token' => 'testing',
                'number' => 1,
                'title_en' => 'Real Numbers',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('super-admin.ai-import.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/AIImport/Upload')
                ->has('grades', 1)
                ->where('grades.0.id', $grade->id)
                ->where('grades.0.number', 8)
            );

        $this->actingAs($user)
            ->patch(route('super-admin.curriculum.grades.update', $grade), [
                '_token' => 'testing',
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('super-admin.ai-import.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('grades', 0)
            );
    }

    public function test_inactive_subject_and_board_are_omitted_from_lookups(): void
    {
        $user = $this->makeSuperAdmin();

        $grade = Grade::query()->create([
            'number' => 9,
            'label_en' => 'Class 9',
            'is_active' => true,
        ]);
        $activeSubject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'Physics',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'Hidden Chemistry',
            'sort_order' => 2,
            'is_active' => false,
        ]);
        Chapter::query()->create([
            'subject_id' => $activeSubject->id,
            'number' => 1,
            'title_en' => 'Kinematics',
            'is_active' => true,
        ]);

        $lahore = Board::query()->where('name', 'Lahore Board')->first();
        $this->assertNotNull($lahore);

        Board::query()->create([
            'name' => 'Hidden Board',
            'is_active' => false,
            'sort_order' => 9,
        ]);

        $this->actingAs($user)
            ->get(route('super-admin.past-paper-collector.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/PastPaperCollector/Search')
                ->has('subjects', 1)
                ->where('subjects.0.name_en', 'Physics')
                ->has('boards', 1)
                ->where('boards.0.name', 'Lahore Board')
            );

        $this->actingAs($user)
            ->patch(route('super-admin.curriculum.boards.update', $lahore), [
                '_token' => 'testing',
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('super-admin.past-paper-collector.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('boards', 0)
            );
    }

    public function test_cannot_delete_class_that_still_has_subjects(): void
    {
        $user = $this->makeSuperAdmin();
        $grade = Grade::query()->create([
            'number' => 7,
            'label_en' => 'Class 7',
            'is_active' => true,
        ]);
        Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'English',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->from(route('super-admin.curriculum.grades.index'))
            ->delete(route('super-admin.curriculum.grades.destroy', $grade), [
                '_token' => 'testing',
            ])
            ->assertRedirect(route('super-admin.curriculum.grades.index'));

        $this->assertDatabaseHas('grades', ['id' => $grade->id]);
    }
}
