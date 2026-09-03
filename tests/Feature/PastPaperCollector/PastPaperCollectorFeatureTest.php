<?php

namespace Tests\Feature\PastPaperCollector;

use App\Jobs\CollectPastPapersJob;
use App\Models\AIPaperCollection;
use App\Models\AISetting;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PastPaperCollectorFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSuperAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    protected function seedGradeSubject(): array
    {
        $grade = Grade::query()->create(['number' => 9, 'label_en' => 'Class 9', 'label_ur' => null]);
        $subject = Subject::query()->create([
            'grade_id' => $grade->id,
            'name_en' => 'Physics',
            'name_ur' => null,
            'sort_order' => 1,
        ]);

        return [$grade, $subject];
    }

    public function test_guest_cannot_access_collector(): void
    {
        $this->get(route('super-admin.past-paper-collector.create'))
            ->assertRedirect(route('login'));
    }

    public function test_super_admin_can_view_search_form(): void
    {
        $user = $this->makeSuperAdmin();
        $this->seedGradeSubject();

        $this->actingAs($user)
            ->get(route('super-admin.past-paper-collector.create'))
            ->assertOk();
    }

    public function test_store_creates_collection_and_dispatches_job(): void
    {
        Queue::fake();
        $user = $this->makeSuperAdmin();
        [$grade, $subject] = $this->seedGradeSubject();
        AISetting::current()->update(['enable_queue' => true]);

        $response = $this->actingAs($user)->post(route('super-admin.past-paper-collector.store'), [
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'board' => 'Lahore Board',
            'year' => 2020,
            'session' => 'morning',
            'paper_type' => 'complete',
            'language' => 'english',
            'max_results' => 5,
            'country' => 'Pakistan',
        ]);

        $collection = AIPaperCollection::query()->first();
        $this->assertNotNull($collection);
        $this->assertSame('Lahore Board', $collection->board);
        $this->assertSame(2020, $collection->year);
        $this->assertNotNull($collection->ai_import_id);
        $response->assertRedirect(route('super-admin.past-paper-collector.show', $collection));
        Queue::assertPushed(CollectPastPapersJob::class);
    }

    public function test_non_admin_cannot_store_collection(): void
    {
        Role::findOrCreate('teacher', 'web');
        $user = User::factory()->create();
        $user->assignRole('teacher');
        [$grade, $subject] = $this->seedGradeSubject();

        $this->actingAs($user)->post(route('super-admin.past-paper-collector.store'), [
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'board' => 'Lahore Board',
            'year' => 2020,
        ])->assertForbidden();
    }

    public function test_settings_page_hides_secret_values(): void
    {
        $user = $this->makeSuperAdmin();
        AISetting::current()->update([
            'gemini_api_key' => 'secret-gemini-key-value',
            'google_search_api_key' => 'secret-google-key-value',
            'google_cse_id' => 'cx-123',
        ]);

        $response = $this->actingAs($user)->get(route('super-admin.ai-import.settings'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/AIImport/Settings')
            ->where('settings.has_gemini_api_key', true)
            ->where('settings.has_google_search_api_key', true)
            ->missing('settings.gemini_api_key')
            ->missing('settings.google_search_api_key')
        );
    }
}
