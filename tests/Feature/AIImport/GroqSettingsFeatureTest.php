<?php

namespace Tests\Feature\AIImport;

use App\Models\AISetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GroqSettingsFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSuperAdmin(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        $settings = AISetting::current();

        return array_merge([
            'model_name' => $settings->model_name,
            'temperature' => $settings->temperature,
            'max_tokens' => $settings->max_tokens,
            'prompt_template' => $settings->prompt_template,
            'chunk_size' => $settings->chunk_size,
            'retry_count' => $settings->retry_count,
            'enable_queue' => true,
            'gemini_api_key' => '',
            'gemini_enabled' => true,
            'groq_enabled' => true,
            'groq_api_key' => '',
            'groq_model' => 'openai/gpt-oss-20b',
            'groq_vision_model' => 'qwen/qwen3.8-27b',
            'preferred_text_provider' => 'groq',
            'fallback_text_provider' => 'gemini',
            'max_urls_per_search' => $settings->max_urls_per_search,
            'max_pages_per_source' => $settings->max_pages_per_source,
            'search_timeout' => $settings->search_timeout,
            'collector_retry_attempts' => $settings->collector_retry_attempts,
            'duplicate_similarity_threshold' => $settings->duplicate_similarity_threshold,
            'queue_size' => $settings->queue_size,
            'max_source_bytes' => $settings->max_source_bytes,
            'clear_gemini_api_key' => false,
            'clear_groq_api_key' => false,
            'clear_langsearch_api_key' => false,
            'langsearch_api_key' => '',
            'searxng_url' => '',
        ], $overrides);
    }

    public function test_settings_page_never_exposes_raw_keys(): void
    {
        $user = $this->makeSuperAdmin();
        AISetting::current()->update([
            'groq_api_key' => 'gsk_secret_groq_key_value',
            'gemini_api_key' => 'secret-gemini-key-value',
            'preferred_text_provider' => 'groq',
            'fallback_text_provider' => 'gemini',
        ]);

        $response = $this->actingAs($user)->get(route('super-admin.ai-import.settings'));
        $response->assertOk();
        $response->assertDontSee('gsk_secret_groq_key_value');
        $response->assertDontSee('secret-gemini-key-value');
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/AIImport/Settings')
            ->where('settings.has_groq_api_key', true)
            ->where('settings.has_gemini_api_key', true)
            ->where('groq_configured', true)
            ->where('active_text_provider', 'groq')
            ->missing('settings.gemini_api_key')
            ->missing('settings.groq_api_key')
        );
    }

    public function test_save_persists_primary_fallback_and_toggles(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)->put(route('super-admin.ai-import.settings.update'), $this->payload([
            'preferred_text_provider' => 'groq',
            'fallback_text_provider' => 'gemini',
            'groq_enabled' => true,
            'gemini_enabled' => false,
            'groq_model' => 'openai/gpt-oss-120b',
            'groq_api_key' => 'gsk_saved_key_value_xx',
        ]))->assertRedirect();

        $settings = AISetting::current()->fresh();
        $this->assertSame('groq', $settings->preferred_text_provider);
        $this->assertSame('gemini', $settings->fallback_text_provider);
        $this->assertTrue($settings->groq_enabled);
        $this->assertFalse($settings->gemini_enabled);
        $this->assertSame('openai/gpt-oss-120b', $settings->groq_model);
        $this->assertSame('gsk_saved_key_value_xx', $settings->groq_api_key);
    }

    public function test_empty_key_on_save_does_not_wipe_existing_secret(): void
    {
        $user = $this->makeSuperAdmin();
        AISetting::current()->update([
            'groq_api_key' => 'gsk_keep_this_secret_key',
            'gemini_api_key' => 'gemini_keep_this_secret',
        ]);

        $this->actingAs($user)->put(route('super-admin.ai-import.settings.update'), $this->payload([
            'groq_api_key' => '',
            'gemini_api_key' => '',
            'temperature' => 0.4,
        ]))->assertRedirect();

        $settings = AISetting::current()->fresh();
        $this->assertSame('gsk_keep_this_secret_key', $settings->groq_api_key);
        $this->assertSame('gemini_keep_this_secret', $settings->gemini_api_key);
        $this->assertEquals(0.4, $settings->temperature);
    }

    public function test_clear_checkbox_nulls_groq_key(): void
    {
        $user = $this->makeSuperAdmin();
        AISetting::current()->update([
            'groq_api_key' => 'gsk_should_be_cleared',
        ]);

        $this->actingAs($user)->put(route('super-admin.ai-import.settings.update'), $this->payload([
            'clear_groq_api_key' => true,
            'groq_api_key' => '',
        ]))->assertRedirect();

        $this->assertNull(AISetting::current()->fresh()->groq_api_key);
    }

    public function test_fallback_cannot_match_primary(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)->put(route('super-admin.ai-import.settings.update'), $this->payload([
            'preferred_text_provider' => 'groq',
            'fallback_text_provider' => 'groq',
        ]))->assertSessionHasErrors('fallback_text_provider');
    }
}
