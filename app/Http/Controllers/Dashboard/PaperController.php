<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SavedPaper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaperController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->institution_id) {
            return Inertia::render('Dashboard/Index', [
                'papers' => ['data' => []],
                'filters' => [],
                'message' => 'Super Admin: assign an institution context or use admin tools.',
            ]);
        }

        $institutionId = $user->institution_id;

        $query = SavedPaper::query()
            ->with('user:id,name')
            ->where('institution_id', $institutionId)
            ->latest();

        if ($user->hasRole('teacher')) {
            $query->where('user_id', $user->id);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where('title', 'like', "%{$search}%");
        }

        return Inertia::render('Dashboard/Index', [
            'papers' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function destroy(Request $request, SavedPaper $paper)
    {
        $this->authorizePaper($request, $paper);

        $paper->delete();

        return back()->with('success', 'Paper deleted.');
    }

    public function duplicate(Request $request, SavedPaper $paper)
    {
        $this->authorizePaper($request, $paper);

        $copy = $paper->replicate();
        $copy->title = $paper->title.' (Copy)';
        $copy->user_id = $request->user()->id;
        $copy->save();

        return redirect()->route('editor.show', $copy)->with('success', 'Paper duplicated.');
    }

    protected function authorizePaper(Request $request, SavedPaper $paper): void
    {
        abort_unless(
            $paper->institution_id === $request->user()->institution_id,
            403
        );

        if ($request->user()->hasRole('teacher') && $paper->user_id !== $request->user()->id) {
            abort(403);
        }
    }
}
