<script setup>
import PaperPreview from '@/Components/PaperPreview.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    paper: Object,
    questions: { type: Array, default: () => [] },
    title: String,
    examMeta: Object,
    layout: Object,
    institution: Object,
});

onMounted(() => {
    setTimeout(() => window.print(), 400);
});
</script>

<template>
    <Head :title="title || 'Print past paper'" />
    <div class="no-print fixed right-4 top-4 z-50">
        <button class="rounded bg-indigo-600 px-4 py-2 text-white" @click="window.print()">Print</button>
    </div>
    <PaperPreview
        :title="title"
        :questions="questions"
        :exam-meta="examMeta"
        :layout="layout"
        :institution="institution"
    />
</template>

<style>
@import '../../../css/print.css';
body {
    margin: 0;
    background: white;
}
</style>
