<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProgressBar from '@/Components/ProgressBar.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { hasRealtime, usePrivateChannel } from '@/composables/usePrivateChannel';

const props = defineProps({
    collection: { type: Object, required: true },
    status: { type: Object, required: true },
});

const live = ref({ ...props.status });
let timer = null;

const stages = [
    'searching',
    'collecting_sources',
    'downloading',
    'extracting',
    'processing',
    'classifying',
    'review',
];

const stageLabel = (stage) => ({
    queued: 'Queued…',
    searching: 'Searching…',
    collecting_sources: 'Collecting Sources…',
    downloading: 'Downloading…',
    extracting: 'Extracting…',
    processing: 'Processing with Gemini…',
    classifying: 'Classifying…',
    review: 'Pending Review…',
    importing: 'Importing…',
    completed: 'Completed',
    failed: 'Failed',
}[stage] || stage);

const isTerminal = computed(() => ['review', 'completed', 'failed'].includes(live.value.status));

const applyStatus = (data) => {
    live.value = data;
};

const poll = async () => {
    try {
        const res = await fetch(route('super-admin.past-paper-collector.status', props.collection.id), {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            applyStatus(await res.json());
        }
    } catch {
        // ignore transient poll errors
    }
};

watch(isTerminal, (done) => {
    if (!done) {
        return;
    }
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
    router.reload({ only: ['collection', 'status'] });
});

usePrivateChannel(`paper-collection.${props.collection.id}`, '.progress.updated', applyStatus);

onMounted(() => {
    if (!isTerminal.value && !hasRealtime()) {
        timer = setInterval(poll, 2500);
    }
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const retryFailed = () => {
    router.post(route('super-admin.past-paper-collector.retry', props.collection.id));
};

const retrySource = (sourceId) => {
    router.post(route('super-admin.past-paper-collector.source.retry', [props.collection.id, sourceId]));
};
</script>

<template>
    <Head title="Collection Progress" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Collection Progress</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ collection.board }} · {{ collection.grade?.label_en }} {{ collection.subject?.name_en }} · {{ collection.year }}
                        <span v-if="collection.session"> · {{ collection.session }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('super-admin.past-paper-collector.create')">
                        <SecondaryButton>New Search</SecondaryButton>
                    </Link>
                    <Link
                        v-if="collection.ai_import_id && live.status === 'review'"
                        :href="route('super-admin.ai-import.review', collection.ai_import_id)"
                    >
                        <PrimaryButton>Review Questions</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <ProgressBar :value="live.progress_percent" :label="stageLabel(live.progress_stage)" />

                    <ol class="mt-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <li
                            v-for="stage in stages"
                            :key="stage"
                            class="rounded-lg border px-3 py-2 text-sm"
                            :class="live.progress_stage === stage || live.status === stage
                                ? 'border-indigo-300 bg-indigo-50 text-indigo-800'
                                : 'border-gray-200 text-gray-500'"
                        >
                            {{ stageLabel(stage) }}
                        </li>
                    </ol>

                    <div v-if="live.error_message" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        {{ live.error_message }}
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                        <div class="rounded-lg bg-gray-50 p-3"><div class="text-gray-500">URLs visited</div><div class="text-lg font-semibold">{{ live.urls_visited }}</div></div>
                        <div class="rounded-lg bg-gray-50 p-3"><div class="text-gray-500">Successful</div><div class="text-lg font-semibold text-emerald-700">{{ live.successful_sources }}</div></div>
                        <div class="rounded-lg bg-gray-50 p-3"><div class="text-gray-500">Failed / OCR</div><div class="text-lg font-semibold text-amber-700">{{ live.failed_sources }} / {{ live.ocr_required_sources }}</div></div>
                        <div class="rounded-lg bg-gray-50 p-3"><div class="text-gray-500">Questions</div><div class="text-lg font-semibold">{{ live.questions_found }} <span class="text-xs text-gray-500">(dup {{ live.duplicate_count }})</span></div></div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-500">
                        <span>Tokens in/out: {{ live.input_tokens }} / {{ live.output_tokens }}</span>
                        <span v-if="live.processing_time_ms">Time: {{ Math.round(live.processing_time_ms / 1000) }}s</span>
                    </div>

                    <div v-if="live.failed_sources || live.ocr_required_sources" class="mt-4">
                        <SecondaryButton @click="retryFailed">Retry Failed Sources</SecondaryButton>
                    </div>
                </div>

                <div v-if="live.generated_queries?.length" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-800">Generated Queries</h3>
                    <ul class="mt-3 list-disc space-y-1 ps-5 text-sm text-gray-700">
                        <li v-for="(q, i) in live.generated_queries" :key="i">{{ q }}</li>
                    </ul>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">Sources</div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Title / URL</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-500">Questions</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in (live.sources || collection.sources || [])" :key="s.id">
                                <td class="max-w-md px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ s.title || 'Untitled' }}</div>
                                    <a :href="s.url" target="_blank" rel="noopener" class="break-all text-xs text-indigo-600">{{ s.url }}</a>
                                    <p v-if="s.error_message" class="mt-1 text-xs text-rose-600">{{ s.error_message }}</p>
                                </td>
                                <td class="px-4 py-3 capitalize">{{ s.status }}</td>
                                <td class="px-4 py-3">{{ s.questions_extracted ?? 0 }}</td>
                                <td class="space-x-2 px-4 py-3 text-right">
                                    <Link :href="route('super-admin.past-paper-collector.source', [collection.id, s.id])" class="text-indigo-600">View</Link>
                                    <button
                                        v-if="['failed', 'ocr_required'].includes(s.status)"
                                        type="button"
                                        class="text-amber-700"
                                        @click="retrySource(s.id)"
                                    >
                                        Retry
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!(live.sources || collection.sources || []).length">
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">No sources yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
