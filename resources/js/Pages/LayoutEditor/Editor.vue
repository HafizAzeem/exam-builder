<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperSettingsSidebar from '@/Components/LayoutEditor/PaperSettingsSidebar.vue';
import SavePaperModal from '@/Components/LayoutEditor/SavePaperModal.vue';
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

const todayIso = () => new Date().toISOString().slice(0, 10);

const resolveInitialDualMedium = () =>
    Boolean(
        props.preview.layout?.dual_medium
        ?? props.preview.config?.dual_medium
        ?? false,
    );

const editorSettings = ref({
    enable_omr: props.preview.settings?.enable_omr ?? false,
    enable_answer_key: props.preview.settings?.enable_answer_key ?? false,
    enable_watermark: props.preview.settings?.enable_watermark ?? false,
    show_past_paper_tags: props.preview.settings?.show_past_paper_tags ?? false,
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
    watermark_type: props.preview.layout?.watermark_type
        ?? (props.preview.layout?.enable_watermark ? 'text' : 'none'),
    enable_watermark: props.preview.layout?.enable_watermark ?? editorSettings.value.enable_watermark,
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

const activePaperContent = computed(() => (editingPaper.value ? editDraft.value : paperContent.value));

const previewLayout = computed(() => ({
    ...layout.value,
    enable_omr: editorSettings.value.enable_omr,
    enable_answer_key: editorSettings.value.enable_answer_key,
    enable_watermark: layout.value.watermark_type === 'text',
    show_past_paper_tags: editorSettings.value.show_past_paper_tags,
}));

const previewOmr = computed(() => (previewLayout.value.enable_omr ? props.preview.omr_rows : []));
const previewAnswerKey = computed(() => (previewLayout.value.enable_answer_key ? props.preview.answer_key : []));

const layoutForm = useForm({ layout_snapshot: { ...layout.value } });

const saveForm = useForm({
    title: props.savedPaper.title,
    paper_type: examMeta.paper_type ?? paperContent.value.header?.paper_type ?? props.savedPaper.title,
    paper_date: examMeta.paper_date ?? todayIso(),
    time_allowed: examMeta.time ?? '2 Hours',
    total_marks: examMeta.marks ?? '',
    class: examMeta.class ?? '',
    subject: examMeta.subject ?? '',
});

let layoutSaveTimer = null;

watch(
    layout,
    (val) => {
        val.enable_watermark = val.watermark_type === 'text';
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
    saveForm.class = header.class || examMeta.class || '';
    saveForm.subject = header.subject || examMeta.subject || '';
    showSaveModal.value = true;
};

const submitSavePaper = () => {
    const content = clonePaperContent(editingPaper.value ? editDraft.value : paperContent.value);
    content.header = {
        ...content.header,
        paper_type: saveForm.paper_type,
        paper_time: saveForm.time_allowed,
        marks: saveForm.total_marks,
        class: saveForm.class,
        subject: saveForm.subject,
    };

    paperContent.value = content;
    layout.value.paper_content = content;
    layout.value.enable_watermark = layout.value.watermark_type === 'text';
    editorSettings.value.enable_watermark = layout.value.watermark_type === 'text';

    saveForm
        .transform((data) => ({
            title: data.title,
            exam_meta: {
                paper_type: data.paper_type,
                paper_date: data.paper_date,
                time: data.time_allowed,
                marks: data.total_marks,
                class: data.class,
                subject: data.subject,
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

        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 lg:grid-cols-3">
            <PaperSettingsSidebar
                v-model:layout="layout"
                v-model:settings="editorSettings"
                :header-templates="headerTemplates"
                class="lg:col-span-1"
            />

            <div class="lg:col-span-2">
                <div class="overflow-x-auto rounded-lg bg-gray-100 p-4 md:p-6">
                    <PaperPreview
                        :title="saveForm.title || savedPaper.title"
                        :layout="previewLayout"
                        :institution="preview.institution"
                        :exam-meta="{
                            ...preview.exam_meta,
                            paper_type: saveForm.paper_type,
                            time: saveForm.time_allowed,
                            marks: saveForm.total_marks,
                            class: saveForm.class,
                            subject: saveForm.subject,
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
            @close="showSaveModal = false"
            @submit="submitSavePaper"
        />
    </AuthenticatedLayout>
</template>
