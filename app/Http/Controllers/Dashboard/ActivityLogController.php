<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityDescription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = ActivityLog::query()
            ->with('user:id,name', 'user.roles')
            ->where('institution_id', $request->user()->institution_id)
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();
        $logs->through(fn (ActivityLog $log) => ActivityDescription::present($log));

        return Inertia::render('Dashboard/ActivityLog', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'user_id']),
            'people' => User::where('institution_id', $request->user()->institution_id)->orderBy('name')->get(['id', 'name']),
            'actions' => ActivityLog::where('institution_id', $request->user()->institution_id)->distinct()->pluck('action')->map(fn ($action) => ['value' => $action, 'label' => ActivityDescription::LABELS[$action] ?? ($action === 'paper.printed' ? 'Opened paper for printing (older entries)' : 'Other changes (older entries)')]),
        ]);
    }
}
