<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { hasRealtime, usePrivateChannel } from '@/composables/usePrivateChannel';

const props = defineProps({
    aiImport: { type: Object, required: true },
    statusPayload: { type: Object, required: true },
    composeQuery: { type: Object, required: true },
});

const status = ref({ ...props.statusPayload });
let timer = null;
let redirected = false;

const processing = (current = status.value.status) =>
    ['uploaded', 'extracting', 'processing', 'importing'].includes(current);

const progress = computed(() =>
    Math.min(100, Math.max(0, Number(status.value.progress_percent) || 0))
);

const label = computed(() => {
    const map = {
        uploaded: 'Queued',
        extracting: 'Reading your text',
        processing: 'AI is preparing questions',
        review: 'Ready to review',
        importing: 'Saving to your paper',
        completed: 'Done',
        failed: 'Something went wrong',
    };
    return map[status.value.status] || status.value.status;
});

const stageHint = computed(() => {
    const map = {
        uploaded: 'Your request is in the queue…',
        extracting: 'Extracting content…',
        processing: 'Classifying and writing exam questions…',
        importing: 'Adding selected questions…',
        review: 'Opening review…',
        failed: 'You can go back and try again.',
    };
    return map[status.value.status] || '';
});

const applyStatus = (data) => {
    if (!data || typeof data !== 'object') return;
    status.value = { ...status.value, ...data };

    if (!processing(data.status) && timer) {
        clearInterval(timer);
        timer = null;
    }

    if ((data.status === 'review' || data.status === 'completed') && !redirected) {
        redirected = true;
        router.visit(route('builder.ai.review', props.aiImport.id));
    }
};

const pollOnce = async () => {
    try {
        const res = await fetch(route('builder.ai.status.json', props.aiImport.id), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        applyStatus(await res.json());
    } catch {
        // ignore
    }
};

usePrivateChannel(`ai-import.${props.aiImport.id}`, '.progress.updated', applyStatus);

onMounted(() => {
    if (!processing() && status.value.status !== 'failed') {
        applyStatus(status.value);
        return;
    }

    if (hasRealtime()) {
        // One sync in case the job finished before the socket subscribed — no interval.
        pollOnce();
        return;
    }

    // Fallback only when Pusher/Echo is not configured.
    timer = setInterval(pollOnce, 2500);
    pollOnce();
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <Head title="AI processing…" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Preparing questions</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ aiImport.grade?.label_en }} · {{ aiImport.subject?.name_en }}
                    </p>
                </div>
                <Link
                    :href="route('builder.create', composeQuery)"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    ← Back to paper
                </Link>
            </div>
        </template>

        <div class="py-12 sm:py-16">
            <div class="mx-auto max-w-xl px-4">
                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                    <div class="border-b border-slate-100 bg-gradient-to-br from-emerald-50 via-white to-slate-50 px-6 py-8 sm:px-8">
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
                                :class="status.status === 'failed' ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-700'"
                            >
                                <svg
                                    v-if="status.status === 'failed'"
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 5a7 7 0 100 14 7 7 0 000-14z" />
                                </svg>
                                <svg
                                    v-else-if="progress >= 100 || status.status === 'review'"
                                    class="h-6 w-6"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg
                                    v-else
                                    class="h-6 w-6 animate-pulse"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-lg font-semibold tracking-tight text-slate-900">{{ label }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ stageHint }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-2xl font-semibold tabular-nums text-slate-900">{{ progress }}%</p>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Progress</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="h-3 w-full overflow-hidden rounded-full bg-slate-200/90">
                                <div
                                    class="h-full rounded-full transition-all duration-700 ease-out"
                                    :class="status.status === 'failed' ? 'bg-rose-500' : 'bg-gradient-to-r from-emerald-500 to-teal-500'"
                                    :style="{ width: `${progress}%` }"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-3">
                        <div class="bg-white px-5 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Status</p>
                            <p class="mt-1 text-sm font-semibold capitalize text-slate-800">{{ status.status }}</p>
                        </div>
                        <div class="bg-white px-5 py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Questions</p>
                            <p class="mt-1 text-sm font-semibold tabular-nums text-slate-800">
                                {{ status.questions_found || 0 }}
                            </p>
                        </div>
                        <div class="col-span-2 bg-white px-5 py-4 sm:col-span-1">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Steps</p>
                            <p class="mt-1 text-sm font-semibold tabular-nums text-slate-800">
                                {{ status.processed_chunks || 0 }}
                                <span class="font-normal text-slate-400">/</span>
                                {{ status.total_chunks || 0 }}
                            </p>
                        </div>
                    </div>

                    <div v-if="status.error_message" class="border-t border-slate-100 px-6 py-4 sm:px-8">
                        <p class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {{ status.error_message }}
                        </p>
                        <Link
                            :href="route('builder.create', composeQuery)"
                            class="mt-4 inline-flex rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800"
                        >
                            Return to paper
                        </Link>
                    </div>
                </div>

                <p class="mt-4 text-center text-xs text-slate-400">
                    Live updates via realtime — this page will open review when ready.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
