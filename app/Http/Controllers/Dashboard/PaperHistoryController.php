<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SavedPaper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaperHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $institutionId = $user->institution_id;

        abort_unless($institutionId, 403);

        $query = ActivityLog::query()
            ->with('user:id,name')
            ->where('institution_id', $institutionId)
            ->where('action', 'paper.created')
            ->latest('created_at');

        if ($user->hasRole('teacher')) {
            $query->where('user_id', $user->id);
        }

        if ($search = $request->string('search')->toString()) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $logs = $query->paginate(20)->withQueryString();

        $paperIds = collect($logs->items())
            ->pluck('meta.paper_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $papers = SavedPaper::query()
            ->whereIn('id', $paperIds)
            ->get(['id', 'title', 'config_snapshot'])
            ->keyBy('id');

        $logs->through(function (ActivityLog $log) use ($papers) {
            $paperId = $log->meta['paper_id'] ?? null;
            $paper = $paperId ? $papers->get($paperId) : null;
            $config = $paper?->config_snapshot ?? [];

            return [
                'id' => $log->id,
                'created_at' => $log->created_at,
                'user' => $log->user,
                'paper_id' => $paperId,
                'title' => $paper?->title ?? 'Deleted paper',
                'class' => $config['exam_meta']['class'] ?? $config['class'] ?? '—',
                'subject' => $config['exam_meta']['subject'] ?? $config['subject'] ?? '—',
                'exists' => (bool) $paper,
            ];
        });

        return Inertia::render('Dashboard/PaperHistory', [
            'logs' => $logs,
            'filters' => $request->only(['search']),
        ]);
    }
}
