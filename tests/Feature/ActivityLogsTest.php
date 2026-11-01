<?php

namespace Tests\Feature;

use App\Http\Middleware\LogActivity;
use App\Models\ActivityLog;
use App\Models\Institution;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityLogsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        Role::findOrCreate('institution_admin', 'web');
        Role::findOrCreate('teacher', 'web');
        $school = Institution::create(['name' => 'Grammar School', 'status' => 'active', 'expiry_date' => now()->addMonth()]);
        $user = User::factory()->create(['name' => 'Ahmed Khan', 'institution_id' => $school->id]);
        $user->assignRole('institution_admin');

        return $user;
    }

    public function test_successful_teacher_changes_have_one_readable_entry_and_keep_actor_after_deletion(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post('/admin/teachers', ['name' => 'Sara Ali', 'phone' => '03001234567', 'password' => 'password123', 'is_active' => true, 'allowed_grades' => null, 'allowed_subjects' => null, 'allowed_categories' => null])->assertSessionHasNoErrors();
        $log = ActivityLog::where('institution_id', $owner->institution_id)->sole();
        $this->assertSame('Ahmed Khan', $log->meta['actor_name'] ?? null);
        $this->get('/admin/logs')->assertInertia(fn (Assert $page) => $page->component('Dashboard/ActivityLog')->where('logs.data.0.actor_name', 'Ahmed Khan')->where('logs.data.0.description', 'Added teacher Sara Ali')->etc());
        $owner->delete();
        $this->assertSame('Ahmed Khan', $log->fresh()->meta['actor_name']);
    }

    public function test_only_successful_mutations_are_recorded_once_without_request_secrets(): void
    {
        $owner = $this->owner();
        foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'GET'] as $method) {
            $request = Request::create('/admin/profile', $method, ['password' => 'secret']);
            $request->setUserResolver(fn () => $owner);
            (new LogActivity)->handle($request, function ($request) {
                ActivityLogger::log($request, 'paper.saved', ['paper_id' => 12]);

                return response('ok');
            });
        }
        $this->assertSame(4, ActivityLog::count());
        $this->assertStringNotContainsString('secret', ActivityLog::all()->toJson());
        $request = Request::create('/admin/profile', 'POST');
        $request->setUserResolver(fn () => $owner);
        (new LogActivity)->handle($request, fn () => response('denied', 403));
        $this->assertSame(4, ActivityLog::count());
    }

    public function test_logs_and_person_filter_are_scoped_to_the_school_and_invalid_submissions_are_not_logged(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        ActivityLog::create(['institution_id' => $other->institution_id, 'user_id' => $other->id, 'action' => 'paper.saved', 'meta' => ['paper_id' => 99], 'ip_address' => '127.0.0.1', 'created_at' => now()]);
        $this->actingAs($owner)->post('/admin/teachers', [])->assertSessionHasErrors();
        $this->get('/admin/logs')->assertInertia(fn (Assert $page) => $page->has('logs.data', 0)->etc());
        $this->assertSame(1, ActivityLog::count());
    }

    public function test_person_filter_and_legacy_descriptions_remain_readable(): void
    {
        $owner = $this->owner();
        $teacher = User::factory()->create(['institution_id' => $owner->institution_id, 'name' => 'Sara Ali']);
        foreach ([$owner, $teacher] as $user) {
            ActivityLog::create(['institution_id' => $owner->institution_id, 'user_id' => $user->id, 'action' => 'request.mutated', 'meta' => ['method' => 'DELETE', 'path' => '/admin/teachers/123'], 'ip_address' => '127.0.0.1', 'created_at' => now()]);
        }
        $this->actingAs($owner)->get('/admin/logs?user_id='.$teacher->id)->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.actor_name', 'Sara Ali')->where('logs.data.0.description', 'Deleted teacher')->etc());
    }
}
