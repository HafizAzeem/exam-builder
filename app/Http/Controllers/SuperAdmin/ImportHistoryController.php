<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AIImport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $imports = AIImport::query()
            ->with(['user:id,name', 'grade:id,number,label_en', 'subject:id,name_en'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('SuperAdmin/AIImport/History', [
            'imports' => $imports,
            'filters' => $request->only(['status']),
        ]);
    }
}
