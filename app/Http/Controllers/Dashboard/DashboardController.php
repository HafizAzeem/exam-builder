<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Institution;
use App\Models\LoginSession;
use App\Models\PastPaperTag;
use App\Models\SavedPaper;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->institution_id) {
            return Inertia::render('Dashboard/Overview', [
                'stats' => $this->emptyStats(),
                'institution' => null,
                'message' => 'Super Admin: assign an institution context or use admin tools.',
            ]);
        }

        $institutionId = $user->institution_id;
        $isAdmin = $user->hasRole('institution_admin');
        $isTeacher = $user->hasRole('teacher');

        $savedPapersQuery = SavedPaper::query()->where('institution_id', $institutionId);
        $papersGeneratedQuery = ActivityLog::query()
            ->where('institution_id', $institutionId)
            ->where('action', 'paper.created');

        if ($isTeacher) {
            $savedPapersQuery->where('user_id', $user->id);
            $papersGeneratedQuery->where('user_id', $user->id);
        }

        $stats = [
            'papersGenerated' => (clone $papersGeneratedQuery)->count(),
            'savedPapers' => (clone $savedPapersQuery)->count(),
            'pastPapers' => PastPaperTag::query()->count(),
            'teachers' => $isAdmin
                ? User::role('teacher')->where('institution_id', $institutionId)->count()
                : null,
            'paperHistory' => (clone $papersGeneratedQuery)->count(),
            'loginHistory' => $isAdmin
                ? LoginSession::query()
                    ->whereHas('user', fn ($q) => $q->where('institution_id', $institutionId))
                    ->where('success', true)
                    ->count()
                : null,
        ];

        $institution = Institution::find($institutionId);

        return Inertia::render('Dashboard/Overview', [
            'stats' => $stats,
            'institution' => $this->formatInstitution($institution),
        ]);
    }

    protected function emptyStats(): array
    {
        return [
            'papersGenerated' => 0,
            'savedPapers' => 0,
            'pastPapers' => 0,
            'teachers' => null,
            'paperHistory' => 0,
            'loginHistory' => null,
        ];
    }

    protected function formatInstitution(?Institution $institution): ?array
    {
        if (! $institution) {
            return null;
        }

        return [
            'name' => $institution->name,
            'expiry_date' => $institution->expiry_date?->format('d-m-Y'),
            'license_type' => $institution->license_type,
            'license_label' => $institution->license_type === 'full' ? 'Full Access' : 'Partial Access',
            'is_active' => $institution->expiry_date?->isFuture() ?? false,
            'logo_url' => $institution->logo_path
                ? Storage::disk('public')->url($institution->logo_path)
                : null,
        ];
    }
}
