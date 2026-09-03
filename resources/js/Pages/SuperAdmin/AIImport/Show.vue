<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProgressBar from '@/Components/ProgressBar.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    aiImport: { type: Object, required: true },
});

const status = ref({ ...props.aiImport });
let timer = null;

const processing = () => ['uploaded', 'extracting', 'processing', 'importing'].includes(status.value.status);

const poll = async () => {
    try {
        const { data } = await axios.get(route('super-admin.ai-import.status', props.aiImport.id));
        status.value = data;
        if (!processing() && timer) {
            clearInterval(timer);
            timer = null;
            if (data.status === 'review') {
                router.visit(route('super-admin.ai-import.review', props.aiImport.id));
            }
        }
    } catch (e) {
        // keep polling quietly
    }
};

onMounted(() => {
    if (processing()) {
        timer = setInterval(poll, 2500);
        poll();
    }
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

watch(() => props.aiImport, (value) => {
    status.value = { ...value };
});
</script>

<template>
    <Head :title="`Import #${aiImport.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Processing: {{ aiImport.original_filename }}
                </h2>
                <div class="flex gap-2">
                    <Link :href="route('super-admin.ai-import.download', aiImport.id)">
                        <SecondaryButton>Download File</SecondaryButton>
                    </Link>
                    <Link v-if="status.status === 'review' || status.status === 'completed'" :href="route('super-admin.ai-import.review', aiImport.id)">
                        <PrimaryButton>Review Questions</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-gray-500">Status</dt>
                            <dd class="mt-1 font-medium capitalize text-gray-900">{{ status.status }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Chunks</dt>
                            <dd class="mt-1 font-medium text-gray-900">{{ status.processed_chunks }} / {{ status.total_chunks }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Questions found</dt>
                            <dd class="mt-1 font-medium text-gray-900">{{ status.questions_found }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Duplicates</dt>
                            <dd class="mt-1 font-medium text-gray-900">{{ status.duplicate_count }}</dd>
                        </div>
                    </dl>

                    <div class="mt-6">
                        <ProgressBar :value="status.progress_percent" label="Progress" />
                    </div>

                    <p v-if="status.error_message" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
                        {{ status.error_message }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
