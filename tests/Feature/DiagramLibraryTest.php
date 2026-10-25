<?php

namespace Tests\Feature;

use App\Models\Diagram;
use App\Models\Institution;
use App\Models\SavedPaper;
use App\Models\User;
use Database\Seeders\DiagramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiagramLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function drawing(): array
    {
        return ['title' => 'Triangle', 'category' => 'Geometry', 'elements' => [['kind' => 'polygon', 'points' => '40,180 160,20 280,180', 'stroke' => '#111111', 'fill' => 'none']]];
    }

    public function test_library_requires_login(): void
    {
        $this->get('/diagrams')->assertRedirect('/login');
    }

    public function test_drawings_are_private_until_reviewed_and_approved(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->postJson('/diagrams', $this->drawing())->assertCreated();
        $diagram = Diagram::firstOrFail();
        $this->actingAs($other)->getJson('/diagrams')->assertJsonCount(0, 'data');
        $this->actingAs($other)->patchJson("/diagrams/{$diagram->id}", $this->drawing())->assertForbidden();
        $this->actingAs($owner)->postJson("/diagrams/{$diagram->id}/submit")->assertOk();
        $this->actingAs($other)->postJson("/diagrams/{$diagram->id}/review", ['status' => 'approved'])->assertForbidden();
        Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin)->postJson("/diagrams/{$diagram->id}/review", ['status' => 'approved'])->assertOk();
        $this->actingAs($other)->getJson('/diagrams')->assertJsonCount(1, 'data');
        $this->actingAs($owner)->patchJson("/diagrams/{$diagram->id}", $this->drawing())->assertForbidden();
        $this->actingAs($other)->postJson("/diagrams/{$diagram->id}/copy")->assertCreated();
        $this->assertDatabaseHas('diagrams', ['user_id' => $other->id, 'status' => 'private']);
    }

    public function test_uploads_are_protected_and_unsafe_elements_are_rejected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/diagrams', ['title' => 'Picture', 'category' => 'Science', 'image' => UploadedFile::fake()->image('plant.png')])->assertCreated();
        $diagram = Diagram::firstOrFail();
        $this->get("/diagrams/{$diagram->id}/image")->assertOk();
        $this->actingAs(User::factory()->create())->get("/diagrams/{$diagram->id}/image")->assertNotFound();
        $this->actingAs($owner)->postJson('/diagrams', array_replace($this->drawing(), ['elements' => [['kind' => 'script', 'text' => 'alert(1)']]]))->assertUnprocessable();
        $this->postJson('/diagrams', array_replace($this->drawing(), ['status' => 'approved', 'user_id' => 999]))->assertCreated();
        $this->assertDatabaseMissing('diagrams', ['status' => 'approved']);
    }

    public function test_catalogue_is_idempotent_and_contains_multiple_subjects(): void
    {
        $this->seed(DiagramSeeder::class);
        $count = Diagram::count();
        $this->assertGreaterThanOrEqual(40, $count);
        $this->seed(DiagramSeeder::class);
        $this->assertSame($count, Diagram::count());
        foreach (['Basic shapes', 'Solid shapes', 'Geometry', 'Fractions', 'Graphs', 'Measurement', 'Science'] as $category) {
            $this->assertDatabaseHas('diagrams', ['category' => $category, 'status' => 'approved', 'is_builtin' => true]);
        }
    }

    public function test_replacing_an_uploaded_image_with_a_drawing_clears_the_image(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/diagrams', ['title' => 'Picture', 'category' => 'Science', 'image' => UploadedFile::fake()->image('plant.png')])->assertCreated();
        $diagram = Diagram::firstOrFail();
        $this->patchJson("/diagrams/{$diagram->id}", $this->drawing())->assertOk()->assertJsonPath('image_url', null);
        $this->assertNull($diagram->fresh()->image_path);
    }

    public function test_paper_retains_question_and_option_snapshots_after_library_deletion(): void
    {
        $institution = Institution::create(['name' => 'Diagram school', 'expiry_date' => now()->addYear()]);
        $owner = User::factory()->create(['institution_id' => $institution->id]);
        $this->actingAs($owner)->postJson('/diagrams', $this->drawing())->assertCreated();
        $diagram = Diagram::firstOrFail();
        $snapshot = ['title' => $diagram->title, 'elements' => $diagram->elements, 'width' => 180];
        $paper = SavedPaper::create([
            'institution_id' => $institution->id, 'user_id' => $owner->id, 'title' => 'Shapes',
            'config_snapshot' => ['question_ids' => []], 'layout_snapshot' => [], 'status' => 'saved',
        ]);
        $layout = ['header_template' => 1, 'paper_content' => ['header' => [], 'sections' => [
            ['type' => 'mcq', 'questions' => [['id' => 1, 'type' => 'mcq', 'text_en' => 'Identify', 'diagram' => $snapshot, 'options' => ['A' => ['en' => 'Triangle', 'diagram' => $snapshot]]]]],
        ]]];
        $this->patch(route('editor.update', $paper), ['layout_snapshot' => $layout])->assertRedirect();
        $this->deleteJson("/diagrams/{$diagram->id}")->assertNoContent();
        $this->get(route('editor.print', $paper))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('LayoutEditor/Print')
            ->where('preview.layout.paper_content.sections.0.questions.0.diagram.title', 'Triangle')
            ->where('preview.layout.paper_content.sections.0.questions.0.options.A.diagram.width', 180));
    }
}
