<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Contracts\PastPaperCollector\QuestionProcessingProvider;
use App\Contracts\PastPaperCollector\WebSearchProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateAISettingsRequest;
use App\Models\AISetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(
        WebSearchProvider $searchProvider,
        QuestionProcessingProvider $questionProvider,
    ): Response {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $settings = AISetting::current();

        return Inertia::render('SuperAdmin/AIImport/Settings', [
            'settings' => $settings->toPublicArray(),
            'gemini_configured' => $questionProvider->isConfigured(),
            'google_search_configured' => $searchProvider->isConfigured(),
            'providers' => [
                'search' => $searchProvider->name(),
                'questions' => $questionProvider->name(),
            ],
        ]);
    }

    public function update(UpdateAISettingsRequest $request): RedirectResponse
    {
        $settings = AISetting::current();
        $data = $request->validated();

        if (! empty($data['clear_gemini_api_key'])) {
            $data['gemini_api_key'] = null;
        } elseif (! filled($data['gemini_api_key'] ?? null)) {
            unset($data['gemini_api_key']);
        }

        if (! empty($data['clear_google_search_api_key'])) {
            $data['google_search_api_key'] = null;
        } elseif (! filled($data['google_search_api_key'] ?? null)) {
            unset($data['google_search_api_key']);
        }

        unset($data['clear_gemini_api_key'], $data['clear_google_search_api_key']);

        $settings->update($data);

        return back()->with('success', 'AI settings saved.');
    }
}
