<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->where('institution_id', $request->user()->institution_id)
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Dashboard/ActivityLog', [
            'logs' => $logs,
            'filters' => $request->only(['action']),
        ]);
    }
}
