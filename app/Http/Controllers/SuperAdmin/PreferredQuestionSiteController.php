<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PreferredQuestionSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PreferredQuestionSiteController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $sites = PreferredQuestionSite::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        return Inertia::render('SuperAdmin/PreferredSites/Index', [
            'sites' => $sites,
            'sourceTypeOptions' => PreferredQuestionSite::SOURCE_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        if ($request->filled('domain')) {
            $request->merge([
                'domain' => PreferredQuestionSite::normalizeDomain((string) $request->input('domain')),
            ]);
        }

        $validated = $this->validated($request);

        PreferredQuestionSite::query()->create([
            ...$validated,
            'domain' => PreferredQuestionSite::normalizeDomain($validated['domain']),
            'priority' => $validated['priority'] ?? ((PreferredQuestionSite::query()->max('priority') ?? 0) + 10),
            'is_active' => $request->boolean('is_active', true),
            'source_types' => $validated['source_types'] ?? PreferredQuestionSite::SOURCE_TYPES,
        ]);

        return back()->with('success', 'Preferred site added.');
    }

    public function update(Request $request, PreferredQuestionSite $preferredQuestionSite): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        if ($request->filled('domain')) {
            $request->merge([
                'domain' => PreferredQuestionSite::normalizeDomain((string) $request->input('domain')),
            ]);
        }

        $validated = $this->validated($request, $preferredQuestionSite);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        if (isset($validated['domain'])) {
            $validated['domain'] = PreferredQuestionSite::normalizeDomain($validated['domain']);
        }

        $preferredQuestionSite->update($validated);

        return back()->with('success', 'Preferred site updated.');
    }

    public function destroy(PreferredQuestionSite $preferredQuestionSite): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $preferredQuestionSite->delete();

        return back()->with('success', 'Preferred site deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?PreferredQuestionSite $site = null): array
    {
        return $request->validate([
            'name' => [$site ? 'sometimes' : 'required', 'string', 'max:120'],
            'domain' => [
                $site ? 'sometimes' : 'required',
                'string',
                'max:180',
                Rule::unique('preferred_question_sites', 'domain')->ignore($site?->id),
            ],
            'source_types' => ['nullable', 'array'],
            'source_types.*' => ['string', Rule::in(PreferredQuestionSite::SOURCE_TYPES)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
