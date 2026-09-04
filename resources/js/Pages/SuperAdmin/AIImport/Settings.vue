<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    gemini_configured: { type: Boolean, default: false },
    openrouter_configured: { type: Boolean, default: false },
    openai_configured: { type: Boolean, default: false },
    google_search_configured: { type: Boolean, default: false },
    text_provider_configured: { type: Boolean, default: false },
    providers: { type: Object, default: () => ({}) },
});

const form = useForm({
    model_name: props.settings.model_name,
    temperature: props.settings.temperature,
    max_tokens: props.settings.max_tokens,
    prompt_template: props.settings.prompt_template || '',
    chunk_size: props.settings.chunk_size,
    retry_count: props.settings.retry_count,
    enable_queue: !!props.settings.enable_queue,
    preferred_text_provider: props.settings.preferred_text_provider || 'gemini',
    openrouter_model: props.settings.openrouter_model || '',
    gemini_api_key: '',
    openrouter_api_key: '',
    openai_api_key: '',
    google_search_api_key: '',
    google_cse_id: props.settings.google_cse_id || '',
    max_urls_per_search: props.settings.max_urls_per_search ?? 10,
    max_pages_per_source: props.settings.max_pages_per_source ?? 20,
    search_timeout: props.settings.search_timeout ?? 30,
    collector_retry_attempts: props.settings.collector_retry_attempts ?? 3,
    duplicate_similarity_threshold: props.settings.duplicate_similarity_threshold ?? 0.85,
    queue_size: props.settings.queue_size ?? 5,
    max_source_bytes: props.settings.max_source_bytes ?? 15000000,
    clear_gemini_api_key: false,
    clear_openrouter_api_key: false,
    clear_openai_api_key: false,
    clear_google_search_api_key: false,
});

const showOpenRouterModel = computed(() => form.preferred_text_provider === 'openrouter');

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
                <div class="rounded-lg border px-4 py-3 text-sm" :class="text_provider_configured ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                    Preferred text provider (<strong>{{ providers.preferred_text || 'gemini' }}</strong>):
                    <strong>{{ text_provider_configured ? 'Ready' : 'Missing key' }}</strong>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border px-4 py-3 text-sm" :class="gemini_configured ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                        Gemini:
                        <strong>{{ gemini_configured ? 'Configured' : 'Missing' }}</strong>
                        <div v-if="settings.gemini_key_masked" class="mt-1 font-mono text-xs">{{ settings.gemini_key_masked }}</div>
                    </div>
                    <div class="rounded-lg border px-4 py-3 text-sm" :class="openrouter_configured ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                        OpenRouter:
                        <strong>{{ openrouter_configured ? 'Configured' : 'Missing' }}</strong>
                        <div v-if="settings.openrouter_key_masked" class="mt-1 font-mono text-xs">{{ settings.openrouter_key_masked }}</div>
                    </div>
                    <div class="rounded-lg border px-4 py-3 text-sm" :class="openai_configured ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                        OpenAI:
                        <strong>{{ openai_configured ? 'Configured' : 'Missing' }}</strong>
                        <div v-if="settings.openai_key_masked" class="mt-1 font-mono text-xs">{{ settings.openai_key_masked }}</div>
                    </div>
                    <div class="rounded-lg border px-4 py-3 text-sm" :class="google_search_configured ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
                        Google Search ({{ providers.search || 'google_cse' }}):
                        <strong>{{ google_search_configured ? 'Configured' : 'Missing' }}</strong>
                        <div v-if="settings.google_search_key_masked" class="mt-1 font-mono text-xs">{{ settings.google_search_key_masked }}</div>
                    </div>
                </div>

                <form class="space-y-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Text Provider</h3>

                    <div>
                        <InputLabel value="Preferred provider (question extraction)" />
                        <select v-model="form.preferred_text_provider" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="gemini">Gemini</option>
                            <option value="openrouter">OpenRouter</option>
                            <option value="openai">OpenAI</option>
                        </select>
                        <InputError :message="form.errors.preferred_text_provider" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Model Name" />
                        <TextInput v-model="form.model_name" class="mt-1 block w-full" placeholder="gemini-2.5-flash" />
                        <InputError :message="form.errors.model_name" class="mt-1" />
                    </div>

                    <div v-if="showOpenRouterModel">
                        <InputLabel value="OpenRouter model override (optional)" />
                        <TextInput v-model="form.openrouter_model" class="mt-1 block w-full" placeholder="google/gemini-2.5-flash" />
                        <p class="mt-1 text-xs text-gray-500">If blank, Model Name above is used with OpenRouter.</p>
                        <InputError :message="form.errors.openrouter_model" class="mt-1" />
                    </div>

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
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
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

                    <hr class="border-gray-200">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">API Credentials</h3>
                    <p class="text-xs text-gray-500">
                        Stored encrypted in the database. Leave blank to keep the existing key.
                        Keys are managed only here — not from <code>.env</code>.
                    </p>

                    <div>
                        <InputLabel value="Gemini API Key" />
                        <TextInput v-model="form.gemini_api_key" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="••••••••" />
                        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                            <Checkbox v-model:checked="form.clear_gemini_api_key" />
                            Clear Gemini key
                        </label>
                        <InputError :message="form.errors.gemini_api_key" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="OpenRouter API Key" />
                        <TextInput v-model="form.openrouter_api_key" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="••••••••" />
                        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                            <Checkbox v-model:checked="form.clear_openrouter_api_key" />
                            Clear OpenRouter key
                        </label>
                        <InputError :message="form.errors.openrouter_api_key" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="OpenAI API Key" />
                        <TextInput v-model="form.openai_api_key" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="••••••••" />
                        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                            <Checkbox v-model:checked="form.clear_openai_api_key" />
                            Clear OpenAI key
                        </label>
                        <InputError :message="form.errors.openai_api_key" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Google Programmable Search API Key" />
                        <TextInput v-model="form.google_search_api_key" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="••••••••" />
                        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                            <Checkbox v-model:checked="form.clear_google_search_api_key" />
                            Clear Google Search key
                        </label>
                        <InputError :message="form.errors.google_search_api_key" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Google CSE ID" />
                        <TextInput v-model="form.google_cse_id" class="mt-1 block w-full" />
                        <InputError :message="form.errors.google_cse_id" class="mt-1" />
                    </div>

                    <hr class="border-gray-200">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Past Paper Collector</h3>

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

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">Save Settings</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
