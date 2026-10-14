<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ProviderCard from '@/Components/ProviderCard.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    gemini_configured: { type: Boolean, default: false },
    groq_configured: { type: Boolean, default: false },
    text_provider_configured: { type: Boolean, default: false },
    active_text_provider: { type: String, default: null },
    providers: { type: Object, default: () => ({}) },
    model_presets: { type: Array, default: () => [] },
    groq_model_presets: { type: Array, default: () => [] },
    groq_vision_presets: { type: Array, default: () => [] },
});

const form = useForm({
    model_name: props.settings.model_name || 'gemini-3.5-flash-lite',
    temperature: props.settings.temperature,
    max_tokens: props.settings.max_tokens,
    prompt_template: props.settings.prompt_template || '',
    chunk_size: props.settings.chunk_size,
    retry_count: props.settings.retry_count,
    enable_queue: !!props.settings.enable_queue,
    gemini_api_key: '',
    gemini_enabled: props.settings.gemini_enabled !== false,
    groq_enabled: props.settings.groq_enabled !== false,
    groq_api_key: '',
    groq_model: props.settings.groq_model || 'openai/gpt-oss-20b',
    groq_vision_model: props.settings.groq_vision_model || 'qwen/qwen3.8-27b',
    preferred_text_provider: props.settings.preferred_text_provider || 'groq',
    fallback_text_provider: props.settings.fallback_text_provider || 'gemini',
    max_urls_per_search: props.settings.max_urls_per_search ?? 10,
    max_pages_per_source: props.settings.max_pages_per_source ?? 20,
    search_timeout: props.settings.search_timeout ?? 30,
    collector_retry_attempts: props.settings.collector_retry_attempts ?? 3,
    duplicate_similarity_threshold: props.settings.duplicate_similarity_threshold ?? 0.85,
    queue_size: props.settings.queue_size ?? 5,
    max_source_bytes: props.settings.max_source_bytes ?? 15000000,
    langsearch_api_key: '',
    searxng_url: props.settings.searxng_url || '',
    clear_gemini_api_key: false,
    clear_groq_api_key: false,
    clear_langsearch_api_key: false,
});

watch(() => form.preferred_text_provider, (primary) => {
    if (form.fallback_text_provider === primary) {
        form.fallback_text_provider = primary === 'groq' ? 'gemini' : 'none';
    }
});

const activeLabel = computed(() => {
    if (props.active_text_provider === 'groq') return 'Groq';
    if (props.active_text_provider === 'gemini') return 'Gemini';
    return 'None configured';
});

const fallbackOptions = computed(() => {
    const options = [{ id: 'none', label: 'None' }];
    if (form.preferred_text_provider !== 'gemini') {
        options.push({ id: 'gemini', label: 'Gemini' });
    }
    if (form.preferred_text_provider !== 'groq') {
        options.push({ id: 'groq', label: 'Groq' });
    }
    return options;
});

const submit = () => {
    form.put(route('super-admin.ai-import.settings.update'));
};
</script>

<template>
    <Head title="AI Settings" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">AI Settings</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div
                    class="rounded-lg border px-4 py-3 text-sm"
                    :class="text_provider_configured
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                        : 'border-amber-200 bg-amber-50 text-amber-800'"
                >
                    Active now: <strong>{{ activeLabel }}</strong>
                    <div class="mt-1 text-xs opacity-80">
                        Primary runs first. If it is off or missing a key, the fallback provider is used.
                        Questions: {{ providers.questions || '—' }} ·
                        Web search: {{ providers.search || 'none (Groq invent only)' }}
                    </div>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Routing</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Primary provider" />
                                <select v-model="form.preferred_text_provider" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="groq">Groq (default)</option>
                                    <option value="gemini">Gemini</option>
                                </select>
                                <InputError :message="form.errors.preferred_text_provider" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Fallback provider" />
                                <select v-model="form.fallback_text_provider" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                    <option v-for="opt in fallbackOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                                </select>
                                <InputError :message="form.errors.fallback_text_provider" class="mt-1" />
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Free web search</h3>
                                <p class="mt-1 text-xs text-gray-500">
                                    Harvest and the past-paper collector use LangSearch or SearXNG. Groq is never used for crawling (it hits the free rate limit). Leave the key blank to keep a saved key.
                                </p>
                            </div>
                            <div
                                class="rounded-md border px-3 py-1 text-xs font-medium"
                                :class="settings.free_search_provider
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                    : 'border-slate-200 bg-slate-50 text-slate-600'"
                            >
                                {{ settings.free_search_provider ? ('Configured: ' + settings.free_search_provider) : 'Optional — generate still works' }}
                            </div>
                        </div>
                        <div v-if="settings.langsearch_key_masked" class="font-mono text-xs text-gray-500">
                            Saved LangSearch key: {{ settings.langsearch_key_masked }}
                        </div>
                        <div>
                            <InputLabel value="LangSearch API key" />
                            <TextInput
                                v-model="form.langsearch_api_key"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="new-password"
                                placeholder="••••••••"
                            />
                            <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                                <Checkbox v-model:checked="form.clear_langsearch_api_key" />
                                Clear LangSearch key
                            </label>
                            <InputError :message="form.errors.langsearch_api_key" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="SearXNG URL (fallback if no LangSearch key)" />
                            <TextInput
                                v-model="form.searxng_url"
                                class="mt-1 block w-full font-mono text-sm"
                                placeholder="http://localhost:8080"
                            />
                            <InputError :message="form.errors.searxng_url" class="mt-1" />
                        </div>
                    </section>

                    <ProviderCard
                        title="Groq"
                        :enabled="form.groq_enabled"
                        :has-key="groq_configured"
                        :masked-key="settings.groq_key_masked"
                        :api-key="form.groq_api_key"
                        :clear-key="form.clear_groq_api_key"
                        :model="form.groq_model"
                        model-label="Text model"
                        :model-presets="groq_model_presets"
                        :extra-model="form.groq_vision_model"
                        extra-model-label="Vision / OCR model"
                        :extra-model-presets="groq_vision_presets"
                        :errors="{
                            enabled: form.errors.groq_enabled,
                            apiKey: form.errors.groq_api_key,
                            model: form.errors.groq_model,
                            extraModel: form.errors.groq_vision_model,
                        }"
                        @update:enabled="form.groq_enabled = $event"
                        @update:api-key="form.groq_api_key = $event"
                        @update:clear-key="form.clear_groq_api_key = $event"
                        @update:model="form.groq_model = $event"
                        @update:extra-model="form.groq_vision_model = $event"
                    />

                    <ProviderCard
                        title="Gemini"
                        :enabled="form.gemini_enabled"
                        :has-key="gemini_configured"
                        :masked-key="settings.gemini_key_masked"
                        :api-key="form.gemini_api_key"
                        :clear-key="form.clear_gemini_api_key"
                        :model="form.model_name"
                        model-label="Text / search / OCR model"
                        :model-presets="model_presets"
                        :errors="{
                            enabled: form.errors.gemini_enabled,
                            apiKey: form.errors.gemini_api_key,
                            model: form.errors.model_name,
                        }"
                        @update:enabled="form.gemini_enabled = $event"
                        @update:api-key="form.gemini_api_key = $event"
                        @update:clear-key="form.clear_gemini_api_key = $event"
                        @update:model="form.model_name = $event"
                    />

                    <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Shared generation settings</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Temperature" />
                                <TextInput v-model="form.temperature" type="number" step="0.01" min="0" max="2" class="mt-1 block w-full" />
                                <InputError :message="form.errors.temperature" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Max Tokens" />
                                <TextInput v-model="form.max_tokens" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.max_tokens" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Chunk Size (characters)" />
                                <TextInput v-model="form.chunk_size" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.chunk_size" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Retry Count" />
                                <TextInput v-model="form.retry_count" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.retry_count" class="mt-1" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Prompt Template" />
                            <textarea
                                v-model="form.prompt_template"
                                rows="8"
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
                            />
                            <InputError :message="form.errors.prompt_template" class="mt-1" />
                        </div>
                        <label class="flex items-center gap-2">
                            <Checkbox v-model:checked="form.enable_queue" />
                            <span class="text-sm text-gray-700">Enable queue (background processing)</span>
                        </label>
                    </section>

                    <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Past Paper Collector</h3>
                        <p class="text-xs text-gray-500">
                            Search uses LangSearch or SearXNG. OCR and question extraction follow the active text provider (Groq or Gemini).
                        </p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Maximum URLs per search" />
                                <TextInput v-model="form.max_urls_per_search" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.max_urls_per_search" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Maximum pages per source" />
                                <TextInput v-model="form.max_pages_per_source" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.max_pages_per_source" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Search Timeout (seconds)" />
                                <TextInput v-model="form.search_timeout" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.search_timeout" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Collector Retry Attempts" />
                                <TextInput v-model="form.collector_retry_attempts" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.collector_retry_attempts" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Duplicate Similarity Threshold" />
                                <TextInput v-model="form.duplicate_similarity_threshold" type="number" step="0.001" min="0.5" max="1" class="mt-1 block w-full" />
                                <InputError :message="form.errors.duplicate_similarity_threshold" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Queue Size" />
                                <TextInput v-model="form.queue_size" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.queue_size" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Max Source Bytes" />
                                <TextInput v-model="form.max_source_bytes" type="number" class="mt-1 block w-full" />
                                <InputError :message="form.errors.max_source_bytes" class="mt-1" />
                            </div>
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">Save Settings</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
