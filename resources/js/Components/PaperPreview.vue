<script setup>
defineProps({
    title: String,
    questions: { type: Array, default: () => [] },
    dualMedium: Boolean,
    settings: Object,
    layout: Object,
    institution: Object,
    sections: Array,
    omrRows: Array,
    answerKey: Array,
});
</script>

<template>
    <div
        class="paper-preview rounded border bg-white p-6 text-sm shadow-inner"
        :style="{
            fontFamily: layout?.font_family || 'Arial',
            fontSize: (layout?.font_size || 12) + 'pt',
            color: layout?.font_color || '#000',
            lineHeight: layout?.line_height || 1.5,
        }"
    >
        <div v-if="layout?.enable_watermark && layout?.watermark_text" class="watermark-layer text-4xl font-bold text-gray-400">
            {{ layout.watermark_text }}
        </div>

        <header class="mb-6 border-b pb-4 text-center">
            <img
                v-if="institution?.logo_path"
                :src="`/storage/${institution.logo_path}`"
                alt="Logo"
                class="mx-auto mb-2 h-20 object-contain"
            />
            <h1 class="text-lg font-bold">{{ institution?.name || 'Institution Name' }}</h1>
            <h2 class="mt-2 text-base font-semibold">{{ title }}</h2>
        </header>

        <template v-if="sections?.length">
            <div v-for="section in sections" :key="section.type" class="mb-6">
                <h3 class="mb-3 font-bold">{{ section.title }}</h3>
                <div
                    v-for="(q, idx) in section.questions"
                    :key="q.id"
                    class="question-block mb-4"
                >
                    <p>
                        <strong>Q{{ idx + 1 }}.</strong> {{ q.text_en }}
                    </p>
                    <p v-if="dualMedium && q.text_ur" class="question-ur">{{ q.text_ur }}</p>
                    <div v-if="q.type === 'mcq' && q.mcq_options" class="ms-6 mt-1 grid grid-cols-2 gap-1">
                        <span>A) {{ q.mcq_options.option_a_en }}</span>
                        <span>B) {{ q.mcq_options.option_b_en }}</span>
                        <span>C) {{ q.mcq_options.option_c_en }}</span>
                        <span>D) {{ q.mcq_options.option_d_en }}</span>
                    </div>
                    <div v-else-if="settings?.blank_lines" :style="{ height: settings.blank_lines * 24 + 'px' }" />
                </div>
            </div>
        </template>

        <template v-else>
            <div v-for="(q, idx) in questions" :key="q.id" class="question-block mb-4">
                <p><strong>Q{{ idx + 1 }}.</strong> {{ q.text_en }}</p>
                <p v-if="dualMedium && q.text_ur" class="question-ur">{{ q.text_ur }}</p>
            </div>
        </template>

        <div v-if="omrRows?.length" class="omr-sheet mt-8">
            <h3 class="mb-4 font-bold">OMR Answer Sheet</h3>
            <div v-for="row in omrRows" :key="row.number" class="mb-2 flex items-center gap-4">
                <span class="w-8">{{ row.number }}.</span>
                <span v-for="opt in row.options" :key="opt" class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-gray-800">{{ opt }}</span>
            </div>
        </div>

        <div v-if="answerKey?.length" class="section-break mt-8">
            <h3 class="mb-4 font-bold">Teacher Answer Key</h3>
            <div class="grid grid-cols-5 gap-2">
                <span v-for="a in answerKey" :key="a.number">{{ a.number }}: {{ a.answer }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.question-ur {
    direction: rtl;
    text-align: right;
}
</style>
