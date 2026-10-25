<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DiagramController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => ['nullable', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:80'], 'scope' => ['nullable', Rule::in(['shared', 'mine', 'pending'])]]);
        $query = Diagram::query()->visibleTo($request->user());
        if ($request->scope === 'mine') {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->scope === 'pending') {
            abort_unless($request->user()->hasRole('super_admin'), 403);
            $query->where('status', 'pending');
        } else {
            $query->where('status', 'approved');
        }
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$request->search.'%')->orWhere('description', 'like', '%'.$request->search.'%'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        $diagrams = $query->orderByDesc('is_builtin')->orderBy('title')->paginate(24)->withQueryString();
        $diagrams->through(fn ($diagram) => $diagram->payload($request->user()));
        if ($request->wantsJson()) {
            return response()->json($diagrams);
        }

        return Inertia::render('Diagrams/Index', [
            'diagrams' => $diagrams,
            'categories' => Diagram::query()->visibleTo($request->user())->distinct()->orderBy('category')->pluck('category'),
            'filters' => $request->only('search', 'category', 'scope'),
            'canReview' => $request->user()->hasRole('super_admin'),
        ]);
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'category' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'], 'source' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'elements' => ['nullable', 'array', 'max:300'],
            'elements.*' => ['array:kind,x,y,width,height,x2,y2,rx,ry,points,text,stroke,fill,strokeWidth,fontSize,rotation,dashed'],
            'elements.*.kind' => ['required', Rule::in(['rect', 'ellipse', 'line', 'arrow', 'polygon', 'polyline', 'text'])],
            'elements.*.text' => ['nullable', 'string', 'max:500'],
            'elements.*.points' => ['nullable', 'string', 'max:8000', 'regex:/^[0-9\s,.\-]+$/'],
            'elements.*.stroke' => ['nullable', 'regex:/^(#[a-fA-F0-9]{6}|none)$/'],
            'elements.*.fill' => ['nullable', 'regex:/^(#[a-fA-F0-9]{6}|none)$/'],
            'elements.*.dashed' => ['sometimes', 'boolean'],
            'elements.*.strokeWidth' => ['sometimes', 'numeric', 'min:0', 'max:20'],
            'elements.*.fontSize' => ['sometimes', 'numeric', 'min:8', 'max:100'],
            'elements.*.rotation' => ['sometimes', 'numeric', 'min:-360', 'max:360'],
        ] + collect(['x', 'y', 'width', 'height', 'x2', 'y2', 'rx', 'ry'])->mapWithKeys(fn ($key) => ["elements.*.$key" => ['sometimes', 'numeric', 'min:-1000', 'max:2000']])->all());
        unset($data['image']);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('diagrams', 'local');
            $data['elements'] = null;
        } else {
            abort_if(empty($data['elements']), 422, 'Add a drawing or upload an image.');
            $data['image_path'] = null;
        }

        return $data;
    }

    public function store(Request $request)
    {
        $diagram = Diagram::create($this->data($request) + ['user_id' => $request->user()->id, 'status' => 'private']);

        return response()->json($diagram->payload($request->user()), 201);
    }

    public function update(Request $request, Diagram $diagram)
    {
        abort_unless($diagram->canEdit($request->user()), 403);
        $diagram->update($this->data($request) + ['status' => 'private', 'review_note' => null]);

        return response()->json($diagram->payload($request->user()));
    }

    public function image(Request $request, Diagram $diagram)
    {
        abort_unless(Diagram::query()->visibleTo($request->user())->whereKey($diagram->id)->exists() && $diagram->image_path, 404);

        return Storage::disk('local')->response($diagram->image_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function copy(Request $request, Diagram $diagram)
    {
        abort_unless(Diagram::query()->visibleTo($request->user())->whereKey($diagram->id)->exists(), 404);
        $copy = $diagram->replicate(['builtin_key']);
        $copy->fill(['user_id' => $request->user()->id, 'title' => $diagram->title.' (copy)', 'is_builtin' => false, 'status' => 'private', 'review_note' => null]);
        // Separate files keep a private copy independent of moderation of its source.
        if ($diagram->image_path) {
            $copy->image_path = 'diagrams/'.Str::uuid().'.'.pathinfo($diagram->image_path, PATHINFO_EXTENSION);
            Storage::disk('local')->copy($diagram->image_path, $copy->image_path);
        }
        $copy->save();

        return response()->json($copy->payload($request->user()), 201);
    }

    public function submit(Request $request, Diagram $diagram)
    {
        abort_unless($diagram->canEdit($request->user()), 403);
        $diagram->update(['status' => 'pending', 'review_note' => null]);

        return response()->json(['status' => 'pending']);
    }

    public function review(Request $request, Diagram $diagram)
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        abort_unless($diagram->status === 'pending', 422);
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'review_note' => ['nullable', 'string', 'max:2000']]);
        $diagram->update($data);

        return response()->json(['status' => $diagram->status]);
    }

    public function destroy(Request $request, Diagram $diagram)
    {
        abort_unless($diagram->canEdit($request->user()), 403);
        // Keep image files: saved paper snapshots may still use them.
        $diagram->delete();

        return response()->noContent();
    }
}
