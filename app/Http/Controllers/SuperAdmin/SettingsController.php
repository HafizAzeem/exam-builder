<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateAISettingsRequest;
use App\Models\AISetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $settings = AISetting::current();
        $settings->applyProviderConfig();
        $public = $settings->toPublicArray();

        return Inertia::render('SuperAdmin/AIImport/Settings', [
            'settings' => $public,
            'gemini_configured' => (bool) ($public['has_gemini_api_key'] ?? false),
            'groq_configured' => (bool) ($public['has_groq_api_key'] ?? false),
            'text_provider_configured' => $settings->isTextProviderConfigured(),
            'active_text_provider' => $public['active_text_provider'] ?? null,
            'providers' => [
                'search' => $public['free_search_provider'] ?? $public['active_text_provider'],
                'questions' => $public['active_text_provider'],
                'preferred_text' => $public['preferred_text_provider'],
                'fallback_text' => $public['fallback_text_provider'],
                'active' => $public['active_text_provider'],
            ],
            'model_presets' => [
                ['id' => 'gemini-3.5-flash-lite', 'label' => 'Gemini 3.5 Flash-Lite (recommended)'],
                ['id' => 'gemini-3.6-flash', 'label' => 'Gemini 3.6 Flash'],
                ['id' => 'gemini-3.8-flash', 'label' => 'Gemini 3.8 Flash'],
                ['id' => 'gemini-3.7-flash', 'label' => 'Gemini 3.7 Flash'],
                ['id' => 'gemini-3.5-flash', 'label' => 'Gemini 3.5 Flash'],
                ['id' => 'gemini-3.1-flash-lite', 'label' => 'Gemini 3.1 Flash-Lite'],
            ],
            'groq_model_presets' => [
                ['id' => 'openai/gpt-oss-20b', 'label' => 'GPT OSS 20B (default, fast)'],
                ['id' => 'openai/gpt-oss-120b', 'label' => 'GPT OSS 120B (stronger)'],
            ],
            'groq_vision_presets' => [
                ['id' => 'qwen/qwen3.8-27b', 'label' => 'Qwen 3.8 27B (OCR / images)'],
            ],
        ]);
    }

    public function update(UpdateAISettingsRequest $request): RedirectResponse
    {
        $settings = AISetting::current();
        $data = $request->validated();

        $data = $this->applySecretField($data, 'gemini_api_key', 'clear_gemini_api_key');
        $data = $this->applySecretField($data, 'groq_api_key', 'clear_groq_api_key');
        $data = $this->applySecretField($data, 'langsearch_api_key', 'clear_langsearch_api_key');
        if (! filled($data['searxng_url'] ?? null)) {
            unset($data['searxng_url']);
        }
        unset($data['clear_gemini_api_key'], $data['clear_groq_api_key'], $data['clear_langsearch_api_key']);

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
