<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    collection: { type: Object, required: true },
    source: { type: Object, required: true },
});

const retry = () => {
    router.post(route('super-admin.past-paper-collector.source.retry', [props.collection.id, props.source.id]));
};
</script>

<template>
    <Head title="Source Detail" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Source Detail</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ source.title || 'Untitled source' }}</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('super-admin.past-paper-collector.show', collection.id)">
                        <SecondaryButton>Back</SecondaryButton>
                    </Link>
                    <SecondaryButton
                        v-if="['failed', 'ocr_required'].includes(source.status)"
                        @click="retry"
                    >
                        Retry
                    </SecondaryButton>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm text-sm">
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="text-gray-500">URL</dt>
                            <dd><a :href="source.url" class="break-all text-indigo-600" target="_blank" rel="noopener">{{ source.url }}</a></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Status</dt>
                            <dd class="capitalize">{{ source.status }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Content type</dt>
                            <dd>{{ source.content_type || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">HTTP / Size</dt>
                            <dd>{{ source.http_status || '—' }} / {{ source.file_size || 0 }} bytes</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Questions extracted</dt>
                            <dd>{{ source.questions_extracted }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Content hash</dt>
                            <dd class="break-all font-mono text-xs">{{ source.content_hash || '—' }}</dd>
                        </div>
                    </dl>
                    <p v-if="source.error_message" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-rose-800">
                        {{ source.error_message }}
                    </p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-800">Extracted Text</h3>
                    <pre class="mt-3 max-h-[28rem] overflow-auto whitespace-pre-wrap rounded-lg bg-gray-50 p-4 text-xs text-gray-800">{{ source.extracted_text || 'No extracted text.' }}</pre>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-800">Staged Questions (sample)</h3>
                    <ul class="mt-3 space-y-3 text-sm">
                        <li v-for="q in source.questions || []" :key="q.id" class="rounded-lg border border-gray-100 p-3">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                                <span class="capitalize">{{ q.type }}</span>
                                <span>· {{ q.status }}</span>
                                <span v-if="q.confidence_score != null">· confidence {{ q.confidence_score }}</span>
                            </div>
                            <p class="mt-1 text-gray-900">{{ q.text_en || q.text_ur }}</p>
                        </li>
                        <li v-if="!(source.questions || []).length" class="text-gray-500">No staged questions from this source.</li>
                    </ul>
                    <div v-if="collection.ai_import_id" class="mt-4">
                        <Link :href="route('super-admin.ai-import.review', collection.ai_import_id)" class="text-indigo-600">
                            Open full review dashboard →
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
