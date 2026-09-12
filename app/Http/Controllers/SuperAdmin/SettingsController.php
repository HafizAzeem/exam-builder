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
            'model_presets' => [
                'gemini' => [
                    ['id' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash (recommended)'],
                    ['id' => 'gemini-2.5-pro', 'label' => 'Gemini 2.5 Pro'],
                    ['id' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash'],
                    ['id' => 'gemini-1.5-flash', 'label' => 'Gemini 1.5 Flash'],
                ],
                'openai' => [
                    ['id' => 'gpt-4o-mini', 'label' => 'GPT-4o mini'],
                    ['id' => 'gpt-4o', 'label' => 'GPT-4o'],
                    ['id' => 'gpt-4.1-mini', 'label' => 'GPT-4.1 mini'],
                ],
                'openrouter' => [
                    ['id' => 'google/gemma-4-31b-it:free', 'label' => 'Gemma 4 31B (free)', 'free' => true],
                    ['id' => 'meta-llama/llama-3.3-70b-instruct:free', 'label' => 'Llama 3.3 70B (free)', 'free' => true],
                    ['id' => 'qwen/qwen3-4b:free', 'label' => 'Qwen3 4B (free)', 'free' => true],
                    ['id' => 'google/gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash via OpenRouter', 'free' => false],
                    ['id' => 'openai/gpt-4o-mini', 'label' => 'GPT-4o mini via OpenRouter', 'free' => false],
                ],
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
