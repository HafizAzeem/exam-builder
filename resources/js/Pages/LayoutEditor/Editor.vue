<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperSettingsSidebar from '@/Components/LayoutEditor/PaperSettingsSidebar.vue';
import SavePaperModal from '@/Components/LayoutEditor/SavePaperModal.vue';
import PaperPreview from '@/Components/PaperPreview.vue';
import Modal from '@/Components/Modal.vue';
import { buildPaperContentFromPreview, clonePaperContent, DEFAULT_PAPER_NOTE, hydratePaperContentUrdu } from '@/utils/paperContent';
import { applyPrintStyles, clearPrintStyles, measurePrintFitScale } from '@/utils/printStyles';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePrivateChannel } from '@/composables/usePrivateChannel';

const props = defineProps({
    savedPaper: Object,
    preview: Object,
    headerTemplates: Array,
    pdfUrl: String,
});

const livePdfUrl = ref(props.pdfUrl || null);
const pdfGenerating = ref(false);
const pdfError = ref(null);

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
    header_template: 1,
    font_family: props.preview.layout?.font_family ?? 'Arial',
    font_size: props.preview.layout?.font_size ?? 11,
    heading_font_size: props.preview.layout?.heading_font_size ?? 12,
    font_weight: props.preview.layout?.font_weight ?? 'normal',
    font_color: props.preview.layout?.font_color ?? '#000000',
    line_height: props.preview.layout?.line_height ?? 1.5,
    dual_medium: resolveInitialDualMedium(),
    dual_column: props.preview.layout?.dual_column ?? false,
    page_view: props.preview.layout?.page_view ?? 'single',
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

    const meta = props.preview.exam_meta ?? {};
    base.header = {
        ...base.header,
        class: (base.header?.class || '').trim() || meta.class || '',
        subject: (base.header?.subject || '').trim() || meta.subject || '',
        marks: (base.header?.marks || '').toString().trim() || meta.marks || '',
        paper_time: (base.header?.paper_time || '').trim() || meta.time || '',
        paper_type: (base.header?.paper_type || '').trim() || meta.paper_type || props.savedPaper.title || '',
    };

    return layout.value.dual_medium
        ? hydratePaperContentUrdu(base, props.preview)
        : base;
};

const paperContent = ref(initialContent());
const editDraft = ref(null);
const editingPaper = ref(false);
const showSaveModal = ref(false);
const showPrintLandscapeHint = ref(false);

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

const paperPreviewProps = computed(() => ({
    title: saveForm.title || props.savedPaper.title,
    layout: previewLayout.value,
    institution: props.preview.institution,
    examMeta: {
        ...props.preview.exam_meta,
        class: paperClass.value,
        subject: paperSubject.value,
        paper_type: saveForm.paper_type,
        time: saveForm.time_allowed,
        marks: saveForm.total_marks,
        paper_date: saveForm.paper_date,
    },
    settings: editorSettings.value,
    sections: props.preview.sections,
    paperContent: activePaperContent.value,
    dualMedium: layout.value.dual_medium,
    omrRows: previewOmr.value,
    answerKey: previewAnswerKey.value,
}));

const layoutForm = useForm({ layout_snapshot: { ...layout.value } });

const paperClass = computed(() =>
    (props.preview.exam_meta?.class || paperContent.value?.header?.class || examMeta.class || '').trim(),
);
const paperSubject = computed(() =>
    (props.preview.exam_meta?.subject || paperContent.value?.header?.subject || examMeta.subject || '').trim(),
);

const saveForm = useForm({
    title: props.savedPaper.title,
    paper_type: examMeta.paper_type ?? paperContent.value.header?.paper_type ?? props.savedPaper.title,
    paper_date: examMeta.paper_date ?? todayIso(),
    time_allowed: examMeta.time ?? paperContent.value.header?.paper_time ?? '2 Hours',
    total_marks: examMeta.marks ?? paperContent.value.header?.marks ?? '',
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
    () => layout.value.page_view,
    (view) => {
        if (view === 'double') {
            layout.value.orientation = 'landscape';
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

const onAfterPrint = () => {
    clearPrintStyles();
    document.body.classList.remove('print-active', 'print-dual', 'print-single');
    window.removeEventListener('afterprint', onAfterPrint);
};

const runPrint = () => {
    const dual = layout.value.page_view === 'double';
    if (dual) {
        layout.value.orientation = 'landscape';
    }

    const fitScale = measurePrintFitScale({ dual });
    applyPrintStyles({ dual, fitScale });
    document.body.classList.add('print-active');
    document.body.classList.toggle('print-dual', dual);
    document.body.classList.toggle('print-single', !dual);

    window.addEventListener('afterprint', onAfterPrint);
    requestAnimationFrame(() => {
        setTimeout(() => window.print(), 50);
    });
};

const printPaper = () => {
    // Double page needs Landscape in the browser print dialog.
    // We cannot force @page size without Chrome/Edge hiding the Layout dropdown.
    if (layout.value.page_view === 'double') {
        showPrintLandscapeHint.value = true;
        return;
    }
    runPrint();
};

const confirmDualPrint = () => {
    showPrintLandscapeHint.value = false;
    runPrint();
};

onMounted(() => {
    const url = new URL(window.location.href);
    if (url.searchParams.get('print') === '1') {
        url.searchParams.delete('print');
        window.history.replaceState({}, '', url.pathname);
        setTimeout(() => printPaper(), 400);
    }
});

onUnmounted(() => {
    clearPrintStyles();
    window.removeEventListener('afterprint', onAfterPrint);
});

usePrivateChannel(`paper.${props.savedPaper.id}`, '.pdf.status', (data) => {
    pdfGenerating.value = false;
    if (data.status === 'ready' && data.pdf_url) {
        livePdfUrl.value = data.pdf_url;
        pdfError.value = null;
        return;
    }

    pdfError.value = data.message || 'PDF generation failed.';
});

const requestPdf = () => {
    pdfGenerating.value = true;
    pdfError.value = null;
    layoutForm.post(route('editor.pdf', props.savedPaper.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Edit: ${savedPaper.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="no-print flex flex-wrap items-center justify-between gap-3">
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
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="pdfGenerating"
                        @click="requestPdf"
                    >
                        {{ pdfGenerating ? 'Generating PDF…' : 'PDF' }}
                    </button>
                    <a
                        v-if="livePdfUrl"
                        :href="livePdfUrl"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white"
                        target="_blank"
                        rel="noopener"
                    >
                        Download PDF
                    </a>
                    <Link :href="route('saved-papers.index')" class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-800">
                        Back
                    </Link>
                </div>
            </div>
        </template>

        <p v-if="editingPaper" class="mx-auto max-w-7xl px-4 pt-4 text-sm text-green-700">
            Click text on the preview to edit. Use <strong>Apply text</strong> or <strong>Save paper</strong> when done.
        </p>
        <p v-if="pdfError" class="mx-auto max-w-7xl px-4 pt-4 text-sm text-rose-700">
            {{ pdfError }}
        </p>

        <div class="no-print grid w-full gap-3 px-3 py-6 lg:grid-cols-[minmax(220px,260px)_minmax(0,1fr)] lg:px-4">
            <div class="editor-sidebar-scroll no-print lg:sticky lg:top-4 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto lg:overscroll-contain lg:pr-1">
                <PaperSettingsSidebar
                    v-model:layout="layout"
                    v-model:settings="editorSettings"
                    :header-templates="headerTemplates"
                    :paper-id="savedPaper.id"
                    @watermark-uploaded="onWatermarkUploaded"
                />
            </div>

            <div class="min-w-0 w-full">
                <div
                    class="w-full overflow-x-auto rounded-lg bg-gray-100 py-3 md:py-4"
                >
                    <div
                        v-if="layout.page_view === 'double'"
                        class="editor-dual-sheet mx-auto"
                    >
                        <div class="editor-dual-sheet-label no-print">
                            Landscape sheet · 2 complete pages side-by-side (saves paper when printing)
                        </div>
                        <div class="editor-dual-page-spread">
                            <div class="editor-dual-page-frame">
                                <div class="dual-page-zoom-inner">
                                    <PaperPreview
                                        v-bind="{ ...paperPreviewProps, layout: { ...paperPreviewProps.layout, scale: 100 } }"
                                        :editable="editingPaper"
                                        @update:paper-content="onContentUpdate"
                                    />
                                </div>
                            </div>
                            <div class="editor-dual-page-divider" aria-hidden="true" />
                            <div class="editor-dual-page-frame">
                                <div class="dual-page-zoom-inner">
                                    <PaperPreview
                                        v-bind="{ ...paperPreviewProps, layout: { ...paperPreviewProps.layout, scale: 100 } }"
                                        :editable="false"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                    <PaperPreview
                        v-else
                        v-bind="paperPreviewProps"
                        fill-width
                        :editable="editingPaper"
                        @update:paper-content="onContentUpdate"
                    />
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div
                id="exam-print-root"
                class="print-paper-root"
                :class="{ 'exam-print-dual': layout.page_view === 'double' }"
            >
                <div v-if="layout.page_view === 'double'" class="exam-print-dual-sheet">
                    <div class="exam-print-dual-slot">
                        <div class="exam-print-dual-scaler">
                            <PaperPreview v-bind="paperPreviewProps" :editable="false" />
                        </div>
                    </div>
                    <div class="exam-print-dual-slot">
                        <div class="exam-print-dual-scaler">
                            <PaperPreview v-bind="paperPreviewProps" :editable="false" />
                        </div>
                    </div>
                </div>
                <PaperPreview
                    v-else
                    v-bind="paperPreviewProps"
                    :editable="false"
                />
            </div>
        </Teleport>

        <Modal :show="showPrintLandscapeHint" max-width="md" @close="showPrintLandscapeHint = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Print double page</h3>
                <p class="mt-2 text-sm text-slate-600">
                    In the print dialog, set <strong>Layout</strong> to <strong>Landscape</strong>
                    so both complete copies fit on one sheet.
                </p>
                <ol class="mt-4 list-decimal space-y-1.5 pl-5 text-sm text-slate-700">
                    <li>Open <strong>More settings</strong> if needed</li>
                    <li>Choose <strong>Layout → Landscape</strong></li>
                    <li>Confirm preview shows two pages side-by-side on <strong>1 sheet</strong></li>
                </ol>
                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        @click="showPrintLandscapeHint = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                        @click="confirmDualPrint"
                    >
                        Open print dialog
                    </button>
                </div>
            </div>
        </Modal>

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

<style>
@import '../../../css/print.css';
</style>

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

.editor-dual-page-preview {
    background: #e5e7eb;
}

.editor-dual-sheet {
    width: 100%;
    max-width: 100%;
    padding: 0.5rem 0.75rem 0.75rem;
}

.editor-dual-sheet-label {
    margin-bottom: 0.5rem;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
}

.editor-dual-page-spread {
    display: flex;
    flex-direction: row;
    align-items: stretch;
    gap: 0;
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
    padding: 0.5rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    box-shadow: inset 0 1px 2px rgb(0 0 0 / 0.04);
}

.editor-dual-page-frame {
    flex: 1 1 0;
    min-width: 0;
    background: #fff;
    overflow: hidden;
}

.editor-dual-page-divider {
    flex: 0 0 2px;
    width: 2px;
    align-self: stretch;
    margin: 0 2px;
    background: repeating-linear-gradient(
        to bottom,
        #94a3b8 0 6px,
        transparent 6px 12px
    );
}

/* zoom reduces both visual AND layout size so overflow never clips */
.dual-page-zoom-inner {
    zoom: 0.48;
}

.dual-page-zoom-inner :deep(.paper-preview) {
    max-width: 210mm;
    margin: 0;
    border: none !important;
    box-shadow: none !important;
}
</style>
