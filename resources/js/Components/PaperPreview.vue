<script setup>
const props = defineProps({
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

const headerTemplate = () => Number(props.layout?.header_template ?? 1);
</script>

<template>
    <div
        class="paper-preview rounded border bg-white p-6 text-sm shadow-inner"
        :style="{
            fontFamily: layout?.font_family || 'Arial',
            fontSize: (layout?.font_size || 12) + 'pt',
            color: layout?.font_color || '#000',
            lineHeight: layout?.line_height || 1.5,
            transform: layout?.scale ? `scale(${layout.scale / 100})` : undefined,
            transformOrigin: layout?.scale ? 'top left' : undefined,
            '--print-margin-top': (layout?.margins?.top ?? 15) + 'mm',
            '--print-margin-right': (layout?.margins?.right ?? 15) + 'mm',
            '--print-margin-bottom': (layout?.margins?.bottom ?? 15) + 'mm',
            '--print-margin-left': (layout?.margins?.left ?? 15) + 'mm',
            '--wm-opacity': layout?.watermark_opacity ?? 0.1,
            '--wm-angle': (layout?.watermark_angle ?? 45) + 'deg',
        }"
    >
        <div v-if="layout?.enable_watermark && layout?.watermark_text" class="watermark-layer text-4xl font-bold text-gray-400">
            {{ layout.watermark_text }}
        </div>

        <header class="mb-6 border-b pb-4">
            <template v-if="headerTemplate() === 1">
                <div class="text-center">
                    <img
                        v-if="institution?.logo_path"
                        :src="institution.logo_path.startsWith('http') ? institution.logo_path : `/storage/${institution.logo_path}`"
                        alt="Logo"
                        class="mx-auto mb-2 h-20 object-contain"
                    />
                    <h1 class="text-lg font-bold">{{ institution?.name || 'Institution Name' }}</h1>
                    <h2 class="mt-2 text-base font-semibold">{{ title }}</h2>
                </div>
            </template>

            <template v-else-if="headerTemplate() === 2">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <img
                            v-if="institution?.logo_path"
                            :src="institution.logo_path.startsWith('http') ? institution.logo_path : `/storage/${institution.logo_path}`"
                            alt="Logo"
                            class="h-16 w-16 object-contain"
                        />
                        <div>
                            <h1 class="text-lg font-bold leading-tight">{{ institution?.name || 'Institution Name' }}</h1>
                            <p class="text-xs text-gray-600">{{ institution?.city || '' }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <h2 class="text-base font-semibold leading-tight">{{ title }}</h2>
                        <p class="text-xs text-gray-600">Generated: {{ new Date().toLocaleDateString() }}</p>
                    </div>
                </div>
            </template>

            <template v-else-if="headerTemplate() === 3">
                <div class="grid grid-cols-3 items-center gap-4">
                    <div>
                        <p class="text-xs text-gray-600">Institute</p>
                        <p class="font-bold">{{ institution?.name || 'Institution Name' }}</p>
                        <p class="text-xs text-gray-600">{{ institution?.phone || '' }}</p>
                    </div>
                    <div class="text-center">
                        <img
                            v-if="institution?.logo_path"
                            :src="institution.logo_path.startsWith('http') ? institution.logo_path : `/storage/${institution.logo_path}`"
                            alt="Logo"
                            class="mx-auto h-14 object-contain"
                        />
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-600">Exam</p>
                        <p class="text-base font-semibold">{{ title }}</p>
                    </div>
                </div>
            </template>

            <template v-else-if="headerTemplate() === 4">
                <div class="text-center">
                    <h1 class="text-lg font-extrabold tracking-wide uppercase">{{ institution?.name || 'Institution Name' }}</h1>
                    <div class="mt-2 flex items-center justify-center gap-3">
                        <span class="h-px w-16 bg-gray-300" />
                        <h2 class="text-base font-semibold">{{ title }}</h2>
                        <span class="h-px w-16 bg-gray-300" />
                    </div>
                </div>
            </template>

            <template v-else-if="headerTemplate() === 5">
                <div class="flex items-center gap-4">
                    <img
                        v-if="institution?.logo_path"
                        :src="institution.logo_path.startsWith('http') ? institution.logo_path : `/storage/${institution.logo_path}`"
                        alt="Logo"
                        class="h-20 w-20 object-contain"
                    />
                    <div class="flex-1">
                        <h1 class="text-lg font-bold">{{ institution?.name || 'Institution Name' }}</h1>
                        <h2 class="mt-1 text-base font-semibold">{{ title }}</h2>
                        <p class="mt-1 text-xs text-gray-600">Address: {{ institution?.city || '' }}</p>
                    </div>
                </div>
            </template>

            <template v-else-if="headerTemplate() === 6">
                <div class="flex items-center justify-between">
                    <div class="text-left">
                        <h1 class="text-base font-bold">{{ institution?.name || 'Institution Name' }}</h1>
                        <p class="text-xs text-gray-600">{{ institution?.city || '' }}</p>
                    </div>
                    <div class="text-center">
                        <h2 class="text-lg font-semibold">{{ title }}</h2>
                    </div>
                    <div class="text-right text-xs text-gray-600">
                        <div>Phone: {{ institution?.phone || '-' }}</div>
                        <div>Date: {{ new Date().toLocaleDateString() }}</div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div class="text-center">
                    <h1 class="text-lg font-bold">{{ institution?.name || 'Institution Name' }}</h1>
                    <h2 class="mt-2 text-base font-semibold">{{ title }}</h2>
                    <p class="mt-1 text-xs text-gray-600">Official Exam Paper</p>
                </div>
            </template>
        </header>

        <template v-if="sections?.length">
            <div
                v-for="section in sections"
                :key="section.type"
                class="mb-6"
                :class="layout?.dual_column ? 'questions-container dual-col' : ''"
            >
                <h3 class="mb-3 font-bold">{{ section.title }}</h3>
                <div
                    v-for="(q, idx) in section.questions"
                    :key="q.id"
                    class="question-block mb-4"
                >
                    <p>
                        <strong>Q{{ idx + 1 }}.</strong> {{ q.text_en }}
                        <span
                            v-if="layout?.show_past_paper_tags && q.source === 'past_paper' && q.past_paper_tag"
                            class="ms-2 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
                        >
                            {{ q.past_paper_tag.board_name }} {{ q.past_paper_tag.year }}
                        </span>
                    </p>
                    <p v-if="dualMedium && q.text_ur" class="question-ur">{{ q.text_ur }}</p>

                    <div v-if="q.type === 'long' && q.has_parts && q.parts?.length" class="ms-6 mt-2 space-y-2">
                        <div v-for="(p, pIdx) in q.parts" :key="p.id">
                            <p>
                                <strong>({{ String.fromCharCode(97 + pIdx) }})</strong>
                                {{ p.text_en }}
                            </p>
                            <p v-if="dualMedium && p.text_ur" class="question-ur">{{ p.text_ur }}</p>
                        </div>
                    </div>
                    <div v-if="q.type === 'mcq' && q.mcq_options" class="ms-6 mt-1 grid grid-cols-2 gap-1">
                        <span>A) {{ q.mcq_options.option_a_en }}</span>
                        <span>B) {{ q.mcq_options.option_b_en }}</span>
                        <span>C) {{ q.mcq_options.option_c_en }}</span>
                        <span>D) {{ q.mcq_options.option_d_en }}</span>
                    </div>
                    <div v-else-if="q.type === 'truefalse'" class="ms-6 mt-2 flex items-center gap-6">
                        <label class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded border border-gray-900" />
                            True
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <span class="inline-block h-4 w-4 rounded border border-gray-900" />
                            False
                        </label>
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
