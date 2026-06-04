<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperPreview from '@/Components/PaperPreview.vue';
import { buildPaperContentFromPreview, clonePaperContent, hydratePaperContentUrdu } from '@/utils/paperContent';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    savedPaper: Object,
    preview: Object,
    headerTemplates: Array,
    pdfUrl: String,
});

const buildWatermarkText = (inst) => {
    if (!inst) return '';
    const name = (inst.name || '').toUpperCase();
    const location = [inst.address, inst.city].filter(Boolean).join(', ');
    const lines = [
        name,
        location ? location.toUpperCase() : null,
        inst.phone ? `PH: ${inst.phone}` : null,
    ].filter(Boolean);
    return lines.join('\n');
};

const resolveInitialDualMedium = () =>
    Boolean(
        props.preview.layout?.dual_medium
        ?? props.preview.config?.dual_medium
        ?? false,
    );

/** Wizard-only options (read-only summary in sidebar). */
const paperOptions = computed(() => {
    const settings = props.preview.settings ?? {};
    const serverLayout = props.preview.layout ?? {};

    return {
        showPastPaperTags: Boolean(serverLayout.show_past_paper_tags ?? settings.show_past_paper_tags ?? false),
        enableOmr: Boolean(serverLayout.enable_omr ?? settings.enable_omr ?? false),
        enableAnswerKey: Boolean(serverLayout.enable_answer_key ?? settings.enable_answer_key ?? false),
        enableWatermark: Boolean(serverLayout.enable_watermark ?? settings.enable_watermark ?? false),
    };
});

const layout = ref({
    header_template: props.preview.layout?.header_template ?? 1,
    font_family: props.preview.layout?.font_family ?? 'Arial',
    font_size: props.preview.layout?.font_size ?? 12,
    font_color: props.preview.layout?.font_color ?? '#000000',
    line_height: props.preview.layout?.line_height ?? 1.5,
    dual_medium: resolveInitialDualMedium(),
    dual_column: props.preview.layout?.dual_column ?? false,
    orientation: props.preview.layout?.orientation ?? 'portrait',
    scale: props.preview.layout?.scale ?? 100,
    paper_size: props.preview.layout?.paper_size ?? 'A4',
    enable_watermark: paperOptions.value.enableWatermark,
    watermark_text: props.preview.layout?.watermark_text ?? '',
    watermark_opacity: props.preview.layout?.watermark_opacity ?? 0.18,
    watermark_angle: props.preview.layout?.watermark_angle ?? 45,
    paper_content: props.preview.layout?.paper_content,
    margins: {
        top: 15,
        right: 15,
        bottom: 15,
        left: 15,
        ...(props.preview.layout?.margins ?? {}),
    },
});

if (layout.value.enable_watermark && !layout.value.watermark_text) {
    layout.value.watermark_text = props.preview.layout?.watermark_text
        || buildWatermarkText(props.preview.institution);
}

const previewLayout = computed(() => ({
    ...layout.value,
    show_past_paper_tags: paperOptions.value.showPastPaperTags,
    enable_omr: paperOptions.value.enableOmr,
    enable_answer_key: paperOptions.value.enableAnswerKey,
}));

const paperOptionLabels = computed(() => [
    { label: 'Past paper board & year', on: paperOptions.value.showPastPaperTags },
    { label: 'OMR answer sheet', on: paperOptions.value.enableOmr },
    { label: 'Teacher answer key', on: paperOptions.value.enableAnswerKey },
    { label: 'Watermark', on: paperOptions.value.enableWatermark },
]);

const initialContent = () => {
    const base = clonePaperContent(
        layout.value.paper_content
            ?? buildPaperContentFromPreview(props.preview, props.savedPaper.title),
    );

    return layout.value.dual_medium
        ? hydratePaperContentUrdu(base, props.preview)
        : base;
};

const paperContent = ref(initialContent());
const editDraft = ref(null);
const editingPaper = ref(false);
const savingPaper = ref(false);

const activePaperContent = computed(() => (editingPaper.value ? editDraft.value : paperContent.value));

const form = useForm({ layout_snapshot: { ...layout.value } });

let layoutSaveTimer = null;

watch(
    layout,
    (val) => {
        form.layout_snapshot = { ...val, paper_content: paperContent.value };
        clearTimeout(layoutSaveTimer);
        layoutSaveTimer = setTimeout(() => {
            if (!editingPaper.value && !savingPaper.value) {
                form.patch(route('editor.update', props.savedPaper.id), { preserveScroll: true, preserveState: true });
            }
        }, 600);
    },
    { deep: true },
);

watch(
    () => layout.value.enable_watermark,
    (enabled) => {
        if (enabled && !layout.value.watermark_text) {
            layout.value.watermark_text = buildWatermarkText(props.preview.institution);
        }
    },
);

watch(
    () => layout.value.dual_medium,
    (enabled) => {
        if (!enabled) return;
        paperContent.value = hydratePaperContentUrdu(paperContent.value, props.preview);
    },
);

const onContentUpdate = (content) => {
    if (editingPaper.value) {
        editDraft.value = content;
    }
};

const startEditPaper = () => {
    editDraft.value = clonePaperContent(paperContent.value);
    editingPaper.value = true;
};

const cancelEditPaper = () => {
    editDraft.value = null;
    editingPaper.value = false;
};

const savePaperContent = () => {
    if (!editDraft.value) return;

    savingPaper.value = true;
    paperContent.value = clonePaperContent(editDraft.value);
    layout.value.paper_content = paperContent.value;
    form.layout_snapshot = { ...layout.value, paper_content: paperContent.value };

    form.patch(route('editor.update', props.savedPaper.id), {
        preserveScroll: true,
        onFinish: () => {
            savingPaper.value = false;
            editingPaper.value = false;
            editDraft.value = null;
        },
    });
};

const printPaper = () => window.open(route('editor.print', props.savedPaper.id), '_blank');
const requestPdf = () => form.post(route('editor.pdf', props.savedPaper.id), { preserveScroll: true });
</script>

<template>
    <Head :title="`Edit: ${savedPaper.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-gray-800">{{ savedPaper.title }}</h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="!editingPaper"
                        type="button"
                        class="rounded bg-amber-600 px-4 py-2 text-sm text-white hover:bg-amber-700"
                        @click="startEditPaper"
                    >
                        Edit Paper
                    </button>
                    <template v-else>
                        <button
                            type="button"
                            class="rounded bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700 disabled:opacity-60"
                            :disabled="savingPaper"
                            @click="savePaperContent"
                        >
                            {{ savingPaper ? 'Saving…' : 'Save Paper' }}
                        </button>
                        <button
                            type="button"
                            class="rounded border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50"
                            :disabled="savingPaper"
                            @click="cancelEditPaper"
                        >
                            Cancel
                        </button>
                    </template>
                    <button type="button" class="rounded bg-green-600 px-4 py-2 text-sm text-white" @click="printPaper">
                        Print
                    </button>
                    <button type="button" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white" @click="requestPdf">
                        Generate PDF
                    </button>
                    <a
                        v-if="pdfUrl"
                        :href="pdfUrl"
                        class="rounded bg-gray-900 px-4 py-2 text-sm text-white"
                        target="_blank"
                        rel="noopener"
                    >Download PDF</a>
                    <Link :href="route('dashboard')" class="rounded bg-gray-200 px-4 py-2 text-sm">Back</Link>
                </div>
            </div>
        </template>

        <p
            v-if="editingPaper"
            class="mx-auto mb-2 max-w-7xl px-4 text-sm text-green-700"
        >
            Edit mode: click any English or Urdu text on the paper. Green dashed border marks the question area. Save when finished.
        </p>

        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 lg:grid-cols-3">
            <div class="toolbar space-y-4 rounded-lg bg-white p-4 shadow lg:col-span-1">
                <h3 class="font-semibold">Print &amp; layout</h3>
                <p class="text-xs text-gray-500">
                    Fonts, margins, and template. Paper options (dual medium, OMR, etc.) were set in the builder wizard.
                </p>

                <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">From paper builder</p>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700">
                        <li v-for="item in paperOptionLabels" :key="item.label" class="flex items-center gap-2">
                            <span
                                class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded text-xs"
                                :class="item.on ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-500'"
                            >{{ item.on ? '✓' : '—' }}</span>
                            {{ item.label }}
                        </li>
                    </ul>
                    <Link
                        :href="route('builder')"
                        class="mt-2 inline-block text-xs text-indigo-600 hover:underline"
                    >
                        Create a new paper to change these options
                    </Link>
                </div>

                <div>
                    <label class="text-sm">Header Template</label>
                    <select v-model="layout.header_template" class="mt-1 w-full rounded border-gray-300">
                        <option v-for="t in headerTemplates" :key="t" :value="t">Template {{ t }}</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm">Font Family</label>
                    <select v-model="layout.font_family" class="mt-1 w-full rounded border-gray-300">
                        <option>Jameel Noori Nastaleeq</option>
                        <option>Noto Nastaliq Urdu</option>
                        <option>Arial</option>
                        <option>Times New Roman</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm">Font Size (pt)</label>
                    <input v-model.number="layout.font_size" type="range" min="10" max="18" class="w-full" />
                    <input v-model.number="layout.font_size" type="number" class="mt-1 w-20 rounded border" />
                </div>

                <div>
                    <label class="text-sm">Line Height</label>
                    <input v-model.number="layout.line_height" type="number" step="0.1" min="1" max="3" class="w-full rounded border" />
                </div>

                <div>
                    <label class="text-sm">Font Colour</label>
                    <input v-model="layout.font_color" type="color" class="h-10 w-full" />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="layout.dual_column" type="checkbox" />
                    Dual Column (use Landscape)
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm">Orientation</label>
                        <select v-model="layout.orientation" class="mt-1 w-full rounded border-gray-300">
                            <option value="portrait">Portrait</option>
                            <option value="landscape">Landscape</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm">Scale (%)</label>
                        <input v-model.number="layout.scale" type="number" min="50" max="120" class="mt-1 w-full rounded border" />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium">Page margins (mm)</label>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <input v-model.number="layout.margins.top" type="number" min="0" class="rounded border" placeholder="Top" />
                        <input v-model.number="layout.margins.right" type="number" min="0" class="rounded border" placeholder="Right" />
                        <input v-model.number="layout.margins.bottom" type="number" min="0" class="rounded border" placeholder="Bottom" />
                        <input v-model.number="layout.margins.left" type="number" min="0" class="rounded border" placeholder="Left" />
                    </div>
                </div>

                <div v-if="paperOptions.enableWatermark">
                    <label class="text-sm font-medium">Watermark (text &amp; style)</label>
                    <input v-model="layout.watermark_text" class="mt-2 w-full rounded border text-sm" />
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-gray-600">Opacity</label>
                            <input v-model.number="layout.watermark_opacity" type="number" min="0.05" max="0.3" step="0.01" class="mt-1 w-full rounded border" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Angle</label>
                            <input v-model.number="layout.watermark_angle" type="number" min="0" max="60" class="mt-1 w-full rounded border" />
                        </div>
                    </div>
                </div>

                <div class="rounded-md border border-indigo-200 bg-indigo-50 p-3">
                    <label class="flex items-center gap-2 text-sm font-medium text-indigo-900">
                        <input
                            v-model="layout.dual_medium"
                            type="checkbox"
                            class="rounded border-indigo-400 text-indigo-600"
                        />
                        Dual Medium (English + Urdu)
                    </label>
                    <p class="mt-1 text-xs text-indigo-700">
                        Matches your wizard choice by default. Toggle here to show or hide Urdu on this paper.
                    </p>
                </div>
            </div>

            <div class="lg:col-span-2">
                <PaperPreview
                    :title="savedPaper.title"
                    :layout="previewLayout"
                    :institution="preview.institution"
                    :exam-meta="preview.exam_meta"
                    :settings="preview.settings"
                    :sections="preview.sections"
                    :paper-content="activePaperContent"
                    :editable="editingPaper"
                    :dual-medium="layout.dual_medium"
                    :omr-rows="preview.omr_rows"
                    :answer-key="preview.answer_key"
                    @update:paper-content="onContentUpdate"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
