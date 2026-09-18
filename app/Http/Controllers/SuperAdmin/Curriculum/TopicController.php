<?php

namespace App\Http\Controllers\SuperAdmin\Curriculum;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function index(Chapter $chapter): Response
    {
        $chapter->load('subject.grade');

        $topics = Topic::query()
            ->where('chapter_id', $chapter->id)
            ->withCount('questions')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return Inertia::render('SuperAdmin/Curriculum/Topics', [
            'chapter' => $chapter,
            'topics' => $topics,
        ]);
    }

    public function store(Request $request, Chapter $chapter): RedirectResponse
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('topics', 'code')->where(fn ($q) => $q->where('chapter_id', $chapter->id)),
            ],
            'title_en' => ['required', 'string', 'max:500'],
            'title_ur' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $chapter->topics()->create([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? (($chapter->topics()->max('sort_order') ?? 0) + 1),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Topic created.');
    }

    public function update(Request $request, Topic $topic): RedirectResponse
    {
        $validated = $request->validate([
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('topics', 'code')
                    ->where(fn ($q) => $q->where('chapter_id', $topic->chapter_id))
                    ->ignore($topic->id),
            ],
            'title_en' => ['sometimes', 'string', 'max:500'],
            'title_ur' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $topic->update($validated);

        return back()->with('success', 'Topic updated.');
    }

    public function destroy(Topic $topic): RedirectResponse
    {
        $topic->delete();

        return back()->with('success', 'Topic deleted.');
    }
}
