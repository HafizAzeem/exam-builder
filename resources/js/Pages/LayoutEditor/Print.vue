<script setup>
import PaperPreview from '@/Components/PaperPreview.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({ preview: Object, institutionOverride: Object });

onMounted(() => {
    setTimeout(() => window.print(), 500);
});
</script>

<template>
    <Head title="Print Paper" />
    <div class="no-print fixed right-4 top-4 z-50">
        <button class="rounded bg-indigo-600 px-4 py-2 text-white" @click="window.print()">Print</button>
    </div>
    <PaperPreview
        :title="preview.paper.title"
        :layout="preview.layout"
        :institution="institutionOverride || preview.institution"
        :exam-meta="preview.exam_meta"
        :settings="preview.settings"
        :sections="preview.sections"
        :paper-content="preview.layout?.paper_content"
        :dual-medium="preview.layout.dual_medium"
        :omr-rows="preview.omr_rows"
        :answer-key="preview.answer_key"
    />
</template>

<style>
@import '../../../css/print.css';
body {
    margin: 0;
    background: white;
}
</style>
