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
            'text_provider_configured' => $settings->isTextProviderConfigured(),
            'providers' => [
                'search' => $searchProvider->name(),
                'questions' => $questionProvider->name(),
                'preferred_text' => 'gemini',
            ],
            'model_presets' => [
                ['id' => 'gemini-3.5-flash-lite', 'label' => 'Gemini 3.5 Flash-Lite (recommended)'],
                ['id' => 'gemini-3.6-flash', 'label' => 'Gemini 3.6 Flash'],
                ['id' => 'gemini-3.8-flash', 'label' => 'Gemini 3.8 Flash'],
                ['id' => 'gemini-3.7-flash', 'label' => 'Gemini 3.7 Flash'],
                ['id' => 'gemini-3.5-flash', 'label' => 'Gemini 3.5 Flash'],
                ['id' => 'gemini-3.1-flash-lite', 'label' => 'Gemini 3.1 Flash-Lite'],
            ],
        ]);
    }

    public function update(UpdateAISettingsRequest $request): RedirectResponse
    {
        $settings = AISetting::current();
        $data = $request->validated();

        $data = $this->applySecretField($data, 'gemini_api_key', 'clear_gemini_api_key');
        unset($data['clear_gemini_api_key']);

        $data['preferred_text_provider'] = 'gemini';

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
