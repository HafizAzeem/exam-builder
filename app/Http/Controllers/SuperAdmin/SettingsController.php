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
        $settings->applyProviderConfig();

        return Inertia::render('SuperAdmin/AIImport/Settings', [
            'settings' => $settings->toPublicArray(),
            'gemini_configured' => filled($settings->resolvedGeminiApiKey()),
            'openrouter_configured' => filled($settings->resolvedOpenRouterApiKey()),
            'openai_configured' => filled($settings->resolvedOpenAiApiKey()),
            'google_search_configured' => $searchProvider->isConfigured(),
            'text_provider_configured' => $settings->isTextProviderConfigured(),
            'providers' => [
                'search' => $searchProvider->name(),
                'questions' => $questionProvider->name(),
                'preferred_text' => $settings->preferred_text_provider ?: 'gemini',
            ],
        ]);
    }

    public function update(UpdateAISettingsRequest $request): RedirectResponse
    {
        $settings = AISetting::current();
        $data = $request->validated();

        $data = $this->applySecretField($data, 'gemini_api_key', 'clear_gemini_api_key');
        $data = $this->applySecretField($data, 'openrouter_api_key', 'clear_openrouter_api_key');
        $data = $this->applySecretField($data, 'openai_api_key', 'clear_openai_api_key');
        $data = $this->applySecretField($data, 'google_search_api_key', 'clear_google_search_api_key');

        unset(
            $data['clear_gemini_api_key'],
            $data['clear_openrouter_api_key'],
            $data['clear_openai_api_key'],
            $data['clear_google_search_api_key'],
        );

        $settings->update($data);

        return back()->with('success', 'AI settings saved.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function applySecretField(array $data, string $field, string $clearFlag): array
    {
        if (! empty($data[$clearFlag])) {
            $data[$field] = null;
        } elseif (! filled($data[$field] ?? null)) {
            unset($data[$field]);
        }

        return $data;
    }
}
