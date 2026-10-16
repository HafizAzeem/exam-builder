<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperPreview from '@/Components/PaperPreview.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    paper: Object,
    questions: { type: Array, default: () => [] },
    title: String,
    examMeta: Object,
    layout: Object,
    institution: Object,
    query: Object,
});

const printHref = () => route('past-papers.print', props.query);
</script>

<template>
    <Head :title="title || 'Past paper'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">{{ paper.board_name }} — {{ paper.year }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ paper.grade_label }} · {{ paper.subject_name }}
                        <span v-if="paper.session"> · {{ paper.session }}</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('past-papers.index')"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        ← Repository
                    </Link>
                    <Link
                        :href="printHref()"
                        class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Print
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <PaperPreview
                        :title="title"
                        :questions="questions"
                        :exam-meta="examMeta"
                        :layout="layout"
                        :institution="institution"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
