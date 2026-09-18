<?php

namespace App\Http\Controllers\SuperAdmin\Curriculum;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\PastPaperTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function index(): Response
    {
        $boards = Board::query()->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('SuperAdmin/Curriculum/Boards', [
            'boards' => $boards,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:boards,name'],
            'region' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        Board::query()->create([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? ((Board::query()->max('sort_order') ?? 0) + 1),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Board created.');
    }

    public function update(Request $request, Board $board): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('boards', 'name')->ignore($board->id)],
            'region' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $board->update($validated);

        return back()->with('success', 'Board updated.');
    }

    public function destroy(Board $board): RedirectResponse
    {
        $inUse = PastPaperTag::query()->where('board_name', $board->name)->exists();

        if ($inUse) {
            return back()->with('error', 'This board is used on past-paper questions. Deactivate it instead of deleting.');
        }

        $board->delete();

        return back()->with('success', 'Board deleted.');
    }
}
