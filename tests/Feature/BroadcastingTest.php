<?php

namespace Tests\Feature;

use App\Events\AIImportProgressUpdated;
use App\Events\PaperCollectionProgressUpdated;
use App\Events\PaperPdfStatusUpdated;
use App\Jobs\GeneratePdfJob;
use App\Models\AIImport;
use App\Models\AIPaperCollection;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\SavedPaper;
use App\Models\Subject;
use App\Models\User;
use App\Services\AIImport\AIImportService;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['_token' => 'testing']);
        $this->withHeader('X-CSRF-TOKEN', 'testing');

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => '1234',
            'broadcasting.connections.pusher.options.cluster' => 'ap2',
        ]);

        $this->app->make(BroadcastManager::class)->purge();
        require base_path('routes/channels.php');
    }

    protected function makeSuperAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    protected function makeTeacher(?int $institutionId = null): User
    {
        Role::findOrCreate('teacher', 'web');
        $user = User::factory()->create([
            'institution_id' => $institutionId,
        ]);
        $user->assignRole('teacher');

        return $user;
    }

    /**
     * @return array{0: Grade, 1: Subject}
     */
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

    protected function makeImport(User $user): AIImport
    {
        [$grade, $subject] = $this->seedGradeSubject();

        return AIImport::query()->create([
            'user_id' => $user->id,
            'grade_id' => $grade->id,
            'subject_id' => $subject->id,
            'book_type' => 'text_book',
            'original_filename' => 'paper.pdf',
            'stored_path' => 'ai-imports/test.pdf',
            'status' => 'uploaded',
        ]);
    }

    public function test_import_status_changes_broadcast_progress_event(): void
    {
        Event::fake([AIImportProgressUpdated::class]);

        $user = $this->makeSuperAdmin();
        $import = $this->makeImport($user);

        app(AIImportService::class)->markStatus($import, 'extracting');

        Event::assertDispatched(AIImportProgressUpdated::class, function (AIImportProgressUpdated $event) use ($import) {
            return $event->import->is($import)
                && $event->broadcastAs() === 'progress.updated'
                && $event->broadcastOn()[0]->name === 'private-ai-import.'.$import->id;
        });
    }

    public function test_collection_stage_changes_broadcast_progress_event(): void
    {
        Event::fake([PaperCollectionProgressUpdated::class]);

        $user = $this->makeSuperAdmin();
        $import = $this->makeImport($user);

        $collection = AIPaperCollection::query()->create([
            'user_id' => $user->id,
            'grade_id' => $import->grade_id,
            'subject_id' => $import->subject_id,
            'ai_import_id' => $import->id,
            'board' => 'Lahore Board',
            'year' => 2020,
            'status' => 'queued',
            'progress_stage' => 'queued',
        ]);

        $collection->markStage('searching');

        Event::assertDispatched(PaperCollectionProgressUpdated::class, function (PaperCollectionProgressUpdated $event) use ($collection) {
            return $event->collection->is($collection)
                && $event->broadcastAs() === 'progress.updated'
                && $event->broadcastOn()[0]->name === 'private-paper-collection.'.$collection->id;
        });
    }

    public function test_super_admin_can_authorize_import_channel(): void
    {
        $user = $this->makeSuperAdmin();
        $import = $this->makeImport($user);

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-ai-import.'.$import->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_teacher_cannot_authorize_import_channel(): void
    {
        $admin = $this->makeSuperAdmin();
        $import = $this->makeImport($admin);
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-ai-import.'.$import->id,
            ])
            ->assertForbidden();
    }

    public function test_teacher_can_authorize_own_import_channel(): void
    {
        $teacher = $this->makeTeacher();
        $import = $this->makeImport($teacher);

        $this->actingAs($teacher)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-ai-import.'.$import->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_paper_owner_institution_can_authorize_pdf_channel(): void
    {
        $institution = Institution::query()->create([
            'name' => 'Test School',
            'expiry_date' => now()->addYear()->toDateString(),
        ]);
        $user = $this->makeTeacher($institution->id);
        $paper = SavedPaper::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'title' => 'Midterm',
            'config_snapshot' => [],
            'layout_snapshot' => [],
            'status' => 'saved',
        ]);

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-paper.'.$paper->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_other_institution_cannot_authorize_pdf_channel(): void
    {
        $institution = Institution::query()->create([
            'name' => 'School A',
            'expiry_date' => now()->addYear()->toDateString(),
        ]);
        $other = Institution::query()->create([
            'name' => 'School B',
            'expiry_date' => now()->addYear()->toDateString(),
        ]);
        $owner = $this->makeTeacher($institution->id);
        $outsider = $this->makeTeacher($other->id);
        $paper = SavedPaper::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $owner->id,
            'title' => 'Midterm',
            'config_snapshot' => [],
            'layout_snapshot' => [],
            'status' => 'saved',
        ]);

        $this->actingAs($outsider)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-paper.'.$paper->id,
            ])
            ->assertForbidden();
    }

    public function test_pdf_request_dispatches_job(): void
    {
        Queue::fake();

        $institution = Institution::query()->create([
            'name' => 'Test School',
            'expiry_date' => now()->addYear()->toDateString(),
        ]);
        $user = $this->makeTeacher($institution->id);
        $paper = SavedPaper::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'title' => 'Midterm',
            'config_snapshot' => [],
            'layout_snapshot' => [],
            'status' => 'saved',
        ]);

        $this->actingAs($user)
            ->post(route('editor.pdf', $paper))
            ->assertRedirect();

        Queue::assertPushed(GeneratePdfJob::class);
    }

    public function test_pdf_status_event_uses_paper_channel(): void
    {
        $event = new PaperPdfStatusUpdated(15, 'ready', '/storage/papers/1/15.pdf');

        $this->assertSame('pdf.status', $event->broadcastAs());
        $this->assertSame('private-paper.15', $event->broadcastOn()[0]->name);
        $this->assertSame('ready', $event->broadcastWith()['status']);
    }
}
