<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePrivateChannel } from '@/composables/usePrivateChannel';

const props = defineProps({
    aiImport: { type: Object, required: true },
    statusPayload: { type: Object, required: true },
    assistantRequest: { type: Object, default: null },
    composeQuery: { type: Object, required: true },
});
const status = ref({ ...props.statusPayload });
const canceling = ref(false);
const cancelError = ref('');
let timer = null;
let redirected = false;
const processing = (current = status.value.status) => ['reserved', 'queued', 'uploaded', 'searching', 'reading', 'extracting', 'processing', 'importing'].includes(current);
const canceled = computed(() => status.value.status === 'canceled');
const visibleStage = computed(() => processing() && status.value.assistant_stage ? status.value.assistant_stage : status.value.status);
const label = computed(() => ({
    reserved: 'Queued', queued: 'Queued', uploaded: 'Queued', searching: 'Finding source material',
    reading: 'Reading source material', extracting: 'Reading your material', processing: 'Preparing your preview', preparing: 'Preparing your preview',
    importing: 'Adding selected questions', review: 'Ready to review', completed: 'Done',
    canceled: 'Request canceled', failed: 'Could not prepare questions',
}[visibleStage.value] || status.value.status));
const stageHint = computed(() => ({
    reserved: 'Your school request is waiting to start.', queued: 'Your school request is waiting to start.', uploaded: 'Your request is waiting to start.',
    searching: 'Looking for questions that match your confirmed curriculum.', reading: 'Checking the retrieved source material.',
    extracting: 'Reading questions and choices from your material.', processing: 'Organizing questions and checking chapter matches.', preparing: 'Organizing questions and checking chapter matches.',
    importing: 'Adding your selection to the paper.', review: 'Opening your questions for review.',
    completed: 'Opening your questions for review.', canceled: 'Processing has stopped. You can return to your paper.',
    failed: 'Review the message below, then try again from Question Assistant.',
}[visibleStage.value] || 'Preparing your questions.'));
const applyStatus = (data) => {
    if (!data || typeof data !== 'object') return;
    status.value = { ...status.value, ...data };
    if (!processing() && timer) { clearInterval(timer); timer = null; }
    if (['review', 'completed'].includes(status.value.status) && !redirected) {
        redirected = true;
        router.visit(route('builder.ai.review', props.aiImport.id));
    }
};
const pollOnce = async () => {
    try {
        const response = await fetch(route('builder.ai.status.json', props.aiImport.id), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (response.ok) applyStatus(await response.json());
    } catch { /* Keep the current state until the next update. */ }
};
const cancel = () => {
    if (canceling.value || !processing()) return;
    canceling.value = true;
    cancelError.value = '';
    router.post(route('builder.ai.cancel', props.aiImport.id), {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            if (page?.props?.statusPayload) applyStatus(page.props.statusPayload);
            else pollOnce();
        },
        onError: () => { cancelError.value = 'Could not cancel the request. Please try again.'; },
        onFinish: () => { canceling.value = false; },
    });
};
usePrivateChannel(`ai-import.${props.aiImport.id}`, '.progress.updated', applyStatus);
onMounted(() => {
    if (!processing()) { applyStatus(status.value); return; }
    timer = setInterval(pollOnce, 2500);
    pollOnce();
});
onUnmounted(() => { if (timer) clearInterval(timer); });
</script>

<template>
    <Head title="Preparing questions" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-xl font-semibold text-slate-800">Question Assistant</h2><p class="mt-1 text-sm text-slate-500">{{ aiImport.grade?.label_en }} · {{ aiImport.subject?.name_en }}</p></div>
                <Link :href="route('builder.create', composeQuery)" class="text-sm font-medium text-slate-600">← Back to paper</Link>
            </div>
        </template>
        <main class="mx-auto max-w-xl px-4 py-12 sm:py-20">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-live="polite" :aria-busy="processing()">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700" aria-hidden="true">
                        <svg v-if="processing()" class="h-6 w-6 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" opacity=".2"/><path d="M12 3a9 9 0 019 9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        <span v-else class="text-xl">{{ canceled ? '−' : status.status === 'failed' ? '!' : '✓' }}</span>
                    </div>
                    <div><h1 class="text-xl font-semibold text-slate-900">{{ label }}</h1><p class="mt-2 text-sm leading-6 text-slate-500">{{ stageHint }}</p></div>
                </div>
                <p v-if="status.questions_found" class="mt-5 text-sm text-slate-600">{{ status.questions_found }} questions prepared</p>
                <p v-if="status.error_message || (status.status === 'failed' && assistantRequest?.error_message)" role="alert" class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-700">{{ status.error_message || assistantRequest.error_message }}</p>
                <p v-if="cancelError" role="alert" class="mt-5 text-sm text-rose-700">{{ cancelError }}</p>
                <button v-if="assistantRequest && processing()" type="button" :disabled="canceling" class="mt-6 rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 disabled:opacity-50" @click="cancel">{{ canceling ? 'Canceling…' : 'Cancel request' }}</button>
                <Link v-if="!processing()" :href="route('builder.create', composeQuery)" class="mt-6 inline-flex rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">Return to paper</Link>
            </section>
            <p v-if="processing()" class="mt-4 text-center text-xs text-slate-500">Your preview will open when it is ready. Review questions before adding them to your paper.</p>
        </main>
    </AuthenticatedLayout>
</template>
