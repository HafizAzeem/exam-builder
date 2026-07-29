<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\LoginSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $institutionId = $request->user()->institution_id;

        abort_unless($institutionId, 403);

        $sessions = LoginSession::query()
            ->with('user:id,name,email')
            ->whereHas('user', fn ($q) => $q->where('institution_id', $institutionId))
            ->latest('logged_in_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Dashboard/LoginHistory', [
            'sessions' => $sessions,
        ]);
    }
}
