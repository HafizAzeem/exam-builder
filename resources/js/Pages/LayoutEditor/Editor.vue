<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperSettingsSidebar from '@/Components/LayoutEditor/PaperSettingsSidebar.vue';
import SavePaperModal from '@/Components/LayoutEditor/SavePaperModal.vue';
import PaperPreview from '@/Components/PaperPreview.vue';
import { buildPaperContentFromPreview, clonePaperContent, DEFAULT_PAPER_NOTE, hydratePaperContentUrdu } from '@/utils/paperContent';
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

const todayIso = () => new Date().toISOString().slice(0, 10);

const resolveInitialDualMedium = () =>
    Boolean(
        props.preview.layout?.dual_medium
        ?? props.preview.config?.dual_medium
        ?? false,
    );

const editorSettings = ref({
    enable_omr: props.preview.settings?.enable_omr ?? props.preview.layout?.enable_omr ?? false,
    enable_answer_key: props.preview.settings?.enable_answer_key ?? props.preview.layout?.enable_answer_key ?? false,
    enable_watermark: props.preview.settings?.enable_watermark ?? props.preview.layout?.enable_watermark ?? false,
    show_past_paper_tags: props.preview.settings?.show_past_paper_tags ?? props.preview.layout?.show_past_paper_tags ?? false,
});

const layout = ref({
    header_template: props.preview.layout?.header_template ?? 1,
    font_family: props.preview.layout?.font_family ?? 'Arial',
    font_size: props.preview.layout?.font_size ?? 11,
    heading_font_size: props.preview.layout?.heading_font_size ?? 12,
    font_weight: props.preview.layout?.font_weight ?? 'normal',
    font_color: props.preview.layout?.font_color ?? '#000000',
    line_height: props.preview.layout?.line_height ?? 1.5,
    dual_medium: resolveInitialDualMedium(),
    dual_column: props.preview.layout?.dual_column ?? false,
    orientation: props.preview.layout?.orientation ?? 'portrait',
    scale: props.preview.layout?.scale ?? 100,
    paper_size: props.preview.layout?.paper_size ?? 'A4',
    show_note: props.preview.layout?.show_note ?? true,
    show_paper_note: props.preview.layout?.show_paper_note ?? true,
    watermark_type: props.preview.layout?.watermark_type
        ?? (props.preview.layout?.enable_watermark ? 'text' : 'none'),
    enable_watermark: props.preview.layout?.enable_watermark ?? editorSettings.value.enable_watermark,
    watermark_text: props.preview.layout?.watermark_text ?? '',
    watermark_opacity: props.preview.layout?.watermark_opacity ?? 0.18,
    watermark_angle: props.preview.layout?.watermark_angle ?? 45,
    watermark_size: props.preview.layout?.watermark_size ?? 22,
    watermark_image_path: props.preview.layout?.watermark_image_path ?? '',
    watermark_image_size: props.preview.layout?.watermark_image_size ?? 50,
    paper_content: props.preview.layout?.paper_content,
    omr_columns: props.preview.layout?.omr_columns ?? 2,
    margins: {
        top: 15,
        right: 15,
        bottom: 15,
        left: 15,
        ...(props.preview.layout?.margins ?? {}),
    },
});

if (
    layout.value.watermark_type === 'text'
    && !layout.value.watermark_text?.trim()
) {
    layout.value.watermark_text = props.preview.layout?.watermark_text
        || buildWatermarkText(props.preview.institution);
}

const examMeta = props.preview.exam_meta ?? {};

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
const showSaveModal = ref(false);

if (!paperContent.value.header?.paper_note?.trim()) {
    paperContent.value.header = {
        ...paperContent.value.header,
        paper_note: DEFAULT_PAPER_NOTE,
    };
}

const activePaperContent = computed(() => (editingPaper.value ? editDraft.value : paperContent.value));

const previewLayout = computed(() => ({
    ...layout.value,
    enable_omr: editorSettings.value.enable_omr,
    enable_answer_key: editorSettings.value.enable_answer_key,
    enable_watermark: layout.value.watermark_type === 'text' || layout.value.watermark_type === 'image',
    show_past_paper_tags: editorSettings.value.show_past_paper_tags,
}));

const buildOmrRows = (rows, sections) => {
    if (rows?.length) return rows;

    const mcqCount = (sections ?? [])
        .filter((section) => section.type === 'mcq')
        .reduce((sum, section) => sum + (section.questions?.length ?? section.question_count ?? 0), 0);

    return Array.from({ length: mcqCount }, (_, index) => ({
        number: index + 1,
        options: ['A', 'B', 'C', 'D'],
    }));
};

const previewOmr = computed(() => (
    previewLayout.value.enable_omr
        ? buildOmrRows(props.preview.omr_rows, props.preview.sections)
        : []
));
const previewAnswerKey = computed(() => (previewLayout.value.enable_answer_key ? props.preview.answer_key : []));

const layoutForm = useForm({ layout_snapshot: { ...layout.value } });

const paperClass = computed(() => props.preview.exam_meta?.class ?? examMeta.class ?? '');
const paperSubject = computed(() => props.preview.exam_meta?.subject ?? examMeta.subject ?? '');

const saveForm = useForm({
    title: props.savedPaper.title,
    paper_type: examMeta.paper_type ?? paperContent.value.header?.paper_type ?? props.savedPaper.title,
    paper_date: examMeta.paper_date ?? todayIso(),
    time_allowed: examMeta.time ?? '2 Hours',
    total_marks: examMeta.marks ?? '',
});

let layoutSaveTimer = null;

const flushLayoutSave = () => {
    clearTimeout(layoutSaveTimer);
    layoutForm.layout_snapshot = { ...layout.value, paper_content: paperContent.value };
    if (!editingPaper.value && !saveForm.processing) {
        layoutForm.patch(route('editor.update', props.savedPaper.id), {
            preserveScroll: true,
            preserveState: true,
        });
    }
};

const onWatermarkUploaded = (path) => {
    layout.value.watermark_image_path = path;
    layout.value.watermark_type = 'image';
    layout.value.enable_watermark = true;
    flushLayoutSave();
};

const ensureMargins = (target) => {
    const defaults = { top: 15, right: 15, bottom: 15, left: 15 };

    if (!target.margins || typeof target.margins !== 'object') {
        target.margins = { ...defaults };
        return;
    }

    for (const side of ['top', 'right', 'bottom', 'left']) {
        const n = Number(target.margins[side]);
        const fixed = Number.isFinite(n) ? n : defaults[side];
        if (target.margins[side] !== fixed) {
            target.margins[side] = fixed;
        }
    }
};

ensureMargins(layout.value);

watch(
    editorSettings,
    (val) => {
        layout.value.enable_omr = val.enable_omr;
        layout.value.enable_answer_key = val.enable_answer_key;
        layout.value.show_past_paper_tags = val.show_past_paper_tags;
    },
    { deep: true },
);

watch(
    layout,
    (val) => {
        const wantsWatermark = val.watermark_type === 'text' || val.watermark_type === 'image';
        if (val.enable_watermark !== wantsWatermark) {
            val.enable_watermark = wantsWatermark;
        }
        layoutForm.layout_snapshot = { ...val, paper_content: paperContent.value };
        clearTimeout(layoutSaveTimer);
        layoutSaveTimer = setTimeout(() => {
            if (!editingPaper.value && !saveForm.processing) {
                layoutForm.patch(route('editor.update', props.savedPaper.id), {
                    preserveScroll: true,
                    preserveState: true,
                });
            }
        }, 800);
    },
    { deep: true },
);

watch(
    () => layout.value.dual_medium,
    (enabled) => {
        if (!enabled) return;
        paperContent.value = hydratePaperContentUrdu(paperContent.value, props.preview);
    },
);

watch(
    () => layout.value.watermark_type,
    (type) => {
        const wantsWatermark = type === 'text' || type === 'image';
        if (layout.value.enable_watermark !== wantsWatermark) {
            layout.value.enable_watermark = wantsWatermark;
        }
        if (editorSettings.value.enable_watermark !== wantsWatermark) {
            editorSettings.value.enable_watermark = wantsWatermark;
        }
        if (wantsWatermark && !layout.value.watermark_text?.trim()) {
            layout.value.watermark_text = buildWatermarkText(props.preview.institution);
        }
    },
);

const onContentUpdate = (content) => {
    if (editingPaper.value) {
        editDraft.value = content;
    }
};

const startEditPaper = () => {
    editDraft.value = clonePaperContent(paperContent.value);
    if (!editDraft.value.header?.paper_note?.trim()) {
        editDraft.value.header = {
            ...editDraft.value.header,
            paper_note: DEFAULT_PAPER_NOTE,
        };
    }
    editingPaper.value = true;
};

const cancelEditPaper = () => {
    editDraft.value = null;
    editingPaper.value = false;
};

const applyTextEdits = () => {
    if (!editDraft.value) return;
    paperContent.value = clonePaperContent(editDraft.value);
    layout.value.paper_content = paperContent.value;
    editingPaper.value = false;
    editDraft.value = null;
};

const openSaveModal = () => {
    const header = paperContent.value.header ?? {};
    saveForm.title = props.savedPaper.title;
    saveForm.paper_type = header.paper_type || examMeta.paper_type || props.savedPaper.title;
    saveForm.paper_date = examMeta.paper_date || todayIso();
    saveForm.time_allowed = header.paper_time || examMeta.time || '2 Hours';
    saveForm.total_marks = header.marks || examMeta.marks || '';
    showSaveModal.value = true;
};

const submitSavePaper = () => {
    const content = clonePaperContent(editingPaper.value ? editDraft.value : paperContent.value);
    content.header = {
        ...content.header,
        paper_type: saveForm.paper_type,
        paper_time: saveForm.time_allowed,
        marks: saveForm.total_marks,
        class: paperClass.value,
        subject: paperSubject.value,
    };

    paperContent.value = content;
    layout.value.paper_content = content;
    const watermarkOn = layout.value.watermark_type === 'text' || layout.value.watermark_type === 'image';
    layout.value.enable_watermark = watermarkOn;
    editorSettings.value.enable_watermark = watermarkOn;

    saveForm
        .transform((data) => ({
            title: data.title,
            exam_meta: {
                paper_type: data.paper_type,
                paper_date: data.paper_date,
                time: data.time_allowed,
                marks: data.total_marks,
                class: paperClass.value,
                subject: paperSubject.value,
            },
            settings: { ...editorSettings.value },
            layout_snapshot: {
                ...layout.value,
                paper_content: content,
                enable_omr: editorSettings.value.enable_omr,
                enable_answer_key: editorSettings.value.enable_answer_key,
                show_past_paper_tags: editorSettings.value.show_past_paper_tags,
            },
        }))
        .patch(route('editor.update', props.savedPaper.id), {
            preserveScroll: true,
            onSuccess: () => {
                showSaveModal.value = false;
                editingPaper.value = false;
                editDraft.value = null;
            },
        });
};

const printPaper = () => window.open(route('editor.print', props.savedPaper.id), '_blank');
const requestPdf = () => layoutForm.post(route('editor.pdf', props.savedPaper.id), { preserveScroll: true });
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
                        class="rounded-md bg-amber-600 px-4 py-2 text-sm text-white hover:bg-amber-700"
                        @click="startEditPaper"
                    >
                        Edit paper
                    </button>
                    <template v-else>
                        <button
                            type="button"
                            class="rounded-md bg-gray-700 px-4 py-2 text-sm text-white hover:bg-gray-800"
                            @click="applyTextEdits"
                        >
                            Apply text
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            @click="cancelEditPaper"
                        >
                            Cancel edit
                        </button>
                    </template>
                    <button
                        type="button"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700"
                        @click="openSaveModal"
                    >
                        Save paper
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700"
                        @click="printPaper"
                    >
                        Print
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700"
                        @click="requestPdf"
                    >
                        PDF
                    </button>
                    <a
                        v-if="pdfUrl"
                        :href="pdfUrl"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white"
                        target="_blank"
                        rel="noopener"
                    >
                        Download PDF
                    </a>
                    <Link :href="route('dashboard')" class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-800">
                        Back
                    </Link>
                </div>
            </div>
        </template>

        <p v-if="editingPaper" class="mx-auto max-w-7xl px-4 pt-4 text-sm text-green-700">
            Click text on the preview to edit. Use <strong>Apply text</strong> or <strong>Save paper</strong> when done.
        </p>

        <div class="grid w-full gap-3 px-3 py-6 lg:grid-cols-[minmax(220px,260px)_minmax(0,1fr)] lg:px-4">
            <div class="editor-sidebar-scroll lg:sticky lg:top-4 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto lg:overscroll-contain lg:pr-1">
                <PaperSettingsSidebar
                    v-model:layout="layout"
                    v-model:settings="editorSettings"
                    :header-templates="headerTemplates"
                    :paper-id="savedPaper.id"
                    @watermark-uploaded="onWatermarkUploaded"
                />
            </div>

            <div class="min-w-0 w-full">
                <div class="w-full overflow-x-auto rounded-lg bg-gray-100 py-3 md:py-4">
                    <PaperPreview
                        fill-width
                        :title="saveForm.title || savedPaper.title"
                        :layout="previewLayout"
                        :institution="preview.institution"
                        :exam-meta="{
                            ...preview.exam_meta,
                            class: paperClass,
                            subject: paperSubject,
                            paper_type: saveForm.paper_type,
                            time: saveForm.time_allowed,
                            marks: saveForm.total_marks,
                            paper_date: saveForm.paper_date,
                        }"
                        :settings="editorSettings"
                        :sections="preview.sections"
                        :paper-content="activePaperContent"
                        :editable="editingPaper"
                        :dual-medium="layout.dual_medium"
                        :omr-rows="previewOmr"
                        :answer-key="previewAnswerKey"
                        @update:paper-content="onContentUpdate"
                    />
                </div>
            </div>
        </div>

        <SavePaperModal
            :show="showSaveModal"
            :form="saveForm"
            :processing="saveForm.processing"
            :paper-class="paperClass"
            :paper-subject="paperSubject"
            @close="showSaveModal = false"
            @submit="submitSavePaper"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
.editor-sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.editor-sidebar-scroll::-webkit-scrollbar {
    width: 6px;
}

.editor-sidebar-scroll::-webkit-scrollbar-thumb {
    border-radius: 3px;
    background-color: #cbd5e1;
}

.editor-sidebar-scroll::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8;
}
</style>
