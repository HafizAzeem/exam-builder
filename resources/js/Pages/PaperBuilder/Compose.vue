<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperPreview from '@/Components/PaperPreview.vue';
import QuestionMenuModal from '@/Components/PaperBuilder/QuestionMenuModal.vue';
import SavePaperModal from '@/Components/LayoutEditor/SavePaperModal.vue';
import Modal from '@/Components/Modal.vue';
import { buildPaperContentFromPreview, clonePaperContent, DEFAULT_PAPER_NOTE } from '@/utils/paperContent';
import { applyPrintStyles, clearPrintStyles } from '@/utils/printStyles';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onUnmounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    grades: Array,
    subjects: { type: Array, default: () => [] },
    chapters: { type: Array, default: () => [] },
    selectedGradeId: { type: Number, default: null },
    selectedSubjectId: { type: Number, default: null },
    selectedChapterIds: { type: Array, default: () => [] },
    selectedTopicIds: { type: Array, default: () => [] },
    teacherPermissions: Object,
    institution: Object,
});

const showQuestionMenu = ref(false);
const showEditMeta = ref(false);
const showSaveModal = ref(false);
const saving = ref(false);
const paperTitle = ref('Mid Term Examination');
const editingPaper = ref(false);
const editDraft = ref(null);
const paperContent = ref(null);

const todayIso = () => new Date().toISOString().slice(0, 10);

const saveForm = reactive({
    title: 'Mid Term Examination',
    paper_type: 'Mid Term',
    paper_date: todayIso(),
    time_allowed: '2 Hours',
    total_marks: '50',
});

const selectedGrade = computed(() =>
    props.grades?.find((g) => Number(g.id) === Number(props.selectedGradeId)) ?? null,
);
const selectedSubject = computed(() =>
    props.subjects?.find((s) => Number(s.id) === Number(props.selectedSubjectId)) ?? null,
);

const gradeLabel = computed(() => {
    const n = selectedGrade.value?.number;
    if (!n) return selectedGrade.value?.label_en ?? '';
    if (n === 11) return '1st Year';
    if (n === 12) return '2nd Year';
    return selectedGrade.value?.label_en || `${n}th`;
});

const examMeta = ref({
    class: '',
    subject: '',
    time: '2 Hours',
    marks: '50',
    paper_type: 'Mid Term',
    paper_date: todayIso(),
});

// Keep class/subject filled from the selected grade & subject.
watch(
    [selectedGrade, selectedSubject, gradeLabel],
    () => {
        if (!examMeta.value.class?.trim()) {
            examMeta.value.class = selectedGrade.value?.label_en || gradeLabel.value || '';
        }
        if (!examMeta.value.subject?.trim()) {
            examMeta.value.subject = selectedSubject.value?.name_en || '';
        }
    },
    { immediate: true },
);

const resolvedClass = computed(() =>
    examMeta.value.class?.trim()
    || selectedGrade.value?.label_en
    || gradeLabel.value
    || '',
);

const resolvedSubject = computed(() =>
    examMeta.value.subject?.trim()
    || selectedSubject.value?.name_en
    || '',
);

const settings = ref({
    blank_lines: 3,
    questions_per_line: 1,
    tabular_mcqs: true,
    enable_omr: true,
    enable_answer_key: true,
    enable_watermark: true,
    show_past_paper_tags: false,
});

const sectionSettings = ref({
    mcq: { marks: 1, tabular_mcqs: true },
    short: { marks: 2, blank_lines: 3, questions_per_line: 1, choice_questions: 0 },
    long: { marks: 5, blank_lines: 0, choice_questions: 0, show_parts: true },
    fill: { marks: 1 },
    truefalse: { marks: 1 },
});

const dualMedium = ref(true);
const selectedQuestions = ref([]);
const addFeedback = ref('');

const allowedCategories = computed(() => props.teacherPermissions?.allowed_categories ?? null);
const allowedSources = computed(() => {
    if (!Array.isArray(allowedCategories.value) || !allowedCategories.value.length) {
        return ['exercise', 'additional', 'past_paper'];
    }
    return allowedCategories.value;
});

const selectedChapters = computed(() =>
    props.chapters.filter((c) => props.selectedChapterIds.includes(c.id)),
);

const buildWatermarkText = (inst) => {
    if (!inst) return '';
    const name = (inst.name || '').toUpperCase();
    const location = [inst.address, inst.city].filter(Boolean).join(', ');
    return [name, location ? location.toUpperCase() : null, inst.phone ? `PH: ${inst.phone}` : null]
        .filter(Boolean)
        .join('\n');
};

const previewLayout = computed(() => ({
    header_template: 1,
    font_family: 'Arial',
    font_size: 11,
    heading_font_size: 12,
    font_weight: 'normal',
    font_color: '#000000',
    line_height: 1.5,
    dual_medium: dualMedium.value,
    enable_omr: settings.value.enable_omr,
    enable_answer_key: settings.value.enable_answer_key,
    enable_watermark: settings.value.enable_watermark,
    watermark_type: settings.value.enable_watermark ? 'text' : 'none',
    watermark_text: settings.value.enable_watermark ? buildWatermarkText(props.institution) : '',
    watermark_opacity: 0.18,
    watermark_angle: 45,
    watermark_size: 22,
    show_past_paper_tags: settings.value.show_past_paper_tags,
    show_note: true,
    show_paper_note: true,
    tabular_mcqs: settings.value.tabular_mcqs,
    questions_per_line: settings.value.questions_per_line,
    section_settings: sectionSettings.value,
    paper_size: 'A4',
    orientation: 'portrait',
    scale: 100,
    margins: { top: 15, right: 15, bottom: 15, left: 15 },
}));

const previewOmrRows = computed(() => {
    if (!settings.value.enable_omr) return [];
    const mcqCount = selectedQuestions.value.filter((q) => q.type === 'mcq').length;
    return Array.from({ length: mcqCount }, (_, index) => ({
        number: index + 1,
        options: ['A', 'B', 'C', 'D'],
    }));
});

const paperPreviewProps = computed(() => ({
    title: paperTitle.value,
    questions: selectedQuestions.value,
    dualMedium: dualMedium.value,
    settings: settings.value,
    examMeta: {
        ...examMeta.value,
        class: resolvedClass.value,
        subject: resolvedSubject.value,
    },
    layout: previewLayout.value,
    institution: props.institution,
    omrRows: previewOmrRows.value,
    paperContent: activePaperContent.value,
}));

const groupQuestionsByType = (list) => {
    const order = ['mcq', 'short', 'long', 'fill', 'truefalse'];
    const grouped = {};
    for (const q of list) {
        (grouped[q.type] ||= []).push(q);
    }
    let num = 1;
    return order
        .filter((t) => grouped[t]?.length)
        .map((type) => ({
            type,
            number: num++,
            questions: grouped[type],
            question_count: grouped[type].length,
        }));
};

const buildCurrentPaperContent = () => {
    const built = buildPaperContentFromPreview({
        sections: groupQuestionsByType(selectedQuestions.value),
        layout: previewLayout.value,
        institution: props.institution,
        exam_meta: {
            ...examMeta.value,
            class: resolvedClass.value,
            subject: resolvedSubject.value,
        },
        paper: { title: paperTitle.value },
    }, paperTitle.value);

    if (!built.header?.paper_note?.trim()) {
        built.header = { ...built.header, paper_note: DEFAULT_PAPER_NOTE };
    }

    built.header = {
        ...built.header,
        class: resolvedClass.value || built.header.class || '',
        subject: resolvedSubject.value || built.header.subject || '',
    };

    return built;
};

const mergePaperContentEdits = (fresh, previous) => {
    if (!previous?.sections?.length) return fresh;

    const next = clonePaperContent(fresh);

    if (previous.header) {
        next.header = {
            ...next.header,
            institute_name: previous.header.institute_name ?? next.header.institute_name,
            institute_address: previous.header.institute_address ?? next.header.institute_address,
            paper_note: previous.header.paper_note ?? next.header.paper_note,
            paper_type: previous.header.paper_type ?? next.header.paper_type,
            paper_time: previous.header.paper_time ?? next.header.paper_time,
            class: previous.header.class || next.header.class,
            subject: previous.header.subject || next.header.subject,
            marks: previous.header.marks ?? next.header.marks,
        };
    }

    for (const section of next.sections ?? []) {
        const prevSection = previous.sections.find((s) => s.type === section.type);
        if (!prevSection) continue;

        if (prevSection.heading_en) section.heading_en = prevSection.heading_en;
        if (prevSection.heading_ur) section.heading_ur = prevSection.heading_ur;
        if (prevSection.note !== undefined) section.note = prevSection.note;

        for (const question of section.questions ?? []) {
            const prevQ = prevSection.questions?.find((q) => q.id === question.id);
            if (!prevQ) continue;
            question.text_en = prevQ.text_en ?? question.text_en;
            question.text_ur = prevQ.text_ur ?? question.text_ur;
            question.past_ref = prevQ.past_ref ?? question.past_ref;
            if (prevQ.options) question.options = prevQ.options;
            if (prevQ.parts?.length) {
                question.parts = (question.parts ?? []).map((part) => {
                    const prevPart = prevQ.parts.find((p) => p.id === part.id || p.label === part.label);
                    if (!prevPart) return part;
                    return {
                        ...part,
                        text_en: prevPart.text_en ?? part.text_en,
                        text_ur: prevPart.text_ur ?? part.text_ur,
                    };
                });
            }
        }
    }

    return next;
};

const activePaperContent = computed(() => (editingPaper.value ? editDraft.value : paperContent.value));

// Keep saved paper_content header in sync when Class/Subject resolve.
watch([resolvedClass, resolvedSubject], ([cls, sub]) => {
    const fill = (content) => {
        if (!content?.header) return null;
        const needsClass = cls && !content.header.class?.trim();
        const needsSubject = sub && !content.header.subject?.trim();
        if (!needsClass && !needsSubject) return null;
        const next = clonePaperContent(content);
        if (needsClass) next.header.class = cls;
        if (needsSubject) next.header.subject = sub;
        return next;
    };

    if (paperContent.value) {
        const next = fill(paperContent.value);
        if (next) paperContent.value = next;
    }
    if (editDraft.value) {
        const next = fill(editDraft.value);
        if (next) editDraft.value = next;
    }
});

const startEditPaper = () => {
    const base = paperContent.value ?? buildCurrentPaperContent();
    editDraft.value = clonePaperContent(base);
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
    // Flush the currently focused contenteditable before reading draft.
    if (typeof document !== 'undefined' && document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
    }

    if (editDraft.value) {
        paperContent.value = clonePaperContent(editDraft.value);
    }
    editingPaper.value = false;
    editDraft.value = null;
};

const onContentUpdate = (content) => {
    if (editingPaper.value) {
        editDraft.value = content;
    }
};

const questionIds = computed(() => selectedQuestions.value.map((q) => q.id));

const onAddQuestions = ({ questions, options }) => {
    const existing = new Set(questionIds.value);
    const toAdd = questions.filter((q) => !existing.has(q.id));
    if (!toAdd.length) {
        addFeedback.value = 'Those questions are already on the paper.';
        return;
    }

    selectedQuestions.value = [...selectedQuestions.value, ...toAdd];
    addFeedback.value = `Added ${toAdd.length} question(s). You can search again or close the menu.`;

    if (options?.dualMedium !== undefined) {
        dualMedium.value = options.dualMedium;
    }
    if (options?.showBoardYears !== undefined) {
        settings.value.show_past_paper_tags = options.showBoardYears;
    }

    const type = options?.type || toAdd[0]?.type || 'mcq';
    const nextType = {
        ...(sectionSettings.value[type] ?? {}),
        marks: Number(options?.marks) || sectionSettings.value[type]?.marks || 1,
    };

    if (type === 'mcq') {
        nextType.tabular_mcqs = options?.tabularMcqs ?? true;
        settings.value.tabular_mcqs = nextType.tabular_mcqs;
    }
    if (type === 'short' || type === 'long') {
        nextType.blank_lines = Number(options?.blankLines) || 0;
        nextType.choice_questions = Number(options?.choiceQuestions) || 0;
        nextType.questions_per_line = Number(options?.questionsPerLine) || 1;
        if (type === 'long') {
            nextType.show_parts = options?.showParts !== false;
        }
        if (type === 'short') {
            settings.value.blank_lines = nextType.blank_lines;
            settings.value.questions_per_line = nextType.questions_per_line;
        }
    }

    sectionSettings.value = {
        ...sectionSettings.value,
        [type]: nextType,
    };

    const marks = Object.entries(
        selectedQuestions.value.reduce((acc, q) => {
            acc[q.type] = (acc[q.type] || 0) + 1;
            return acc;
        }, {}),
    ).reduce((sum, [t, count]) => {
        const per = Number(sectionSettings.value[t]?.marks) || 1;
        return sum + (count * per);
    }, 0);
    examMeta.value.marks = String(marks);

    // Keep prior text edits when new questions are added.
    if (paperContent.value || editingPaper.value) {
        const previous = editingPaper.value ? editDraft.value : paperContent.value;
        const merged = mergePaperContentEdits(buildCurrentPaperContent(), previous);
        paperContent.value = merged;
        if (editingPaper.value) {
            editDraft.value = clonePaperContent(merged);
        }
    }
};

const openSaveModal = () => {
    if (!questionIds.value.length) return;

    saveForm.title = paperTitle.value;
    saveForm.paper_type = examMeta.value.paper_type || paperTitle.value;
    saveForm.paper_date = examMeta.value.paper_date || todayIso();
    saveForm.time_allowed = examMeta.value.time || '2 Hours';
    saveForm.total_marks = examMeta.value.marks || '';
    showSaveModal.value = true;
};

const savePaper = () => {
    if (!questionIds.value.length) return;

    if (editingPaper.value && editDraft.value) {
        paperContent.value = clonePaperContent(editDraft.value);
        editingPaper.value = false;
        editDraft.value = null;
    }

    paperTitle.value = saveForm.title?.trim() || paperTitle.value;
    examMeta.value = {
        ...examMeta.value,
        paper_type: saveForm.paper_type || examMeta.value.paper_type,
        paper_date: saveForm.paper_date || examMeta.value.paper_date,
        time: saveForm.time_allowed || examMeta.value.time,
        marks: saveForm.total_marks || examMeta.value.marks,
    };

    const content = paperContent.value
        ? clonePaperContent(paperContent.value)
        : buildCurrentPaperContent();

    content.header = {
        ...content.header,
        paper_type: examMeta.value.paper_type || paperTitle.value,
        paper_time: examMeta.value.time || content.header?.paper_time,
        paper_date: examMeta.value.paper_date || content.header?.paper_date,
        marks: examMeta.value.marks || content.header?.marks,
        class: resolvedClass.value || content.header?.class || '',
        subject: resolvedSubject.value || content.header?.subject || '',
    };
    paperContent.value = content;

    saving.value = true;
    router.post(route('builder.store'), {
        title: paperTitle.value,
        config: {
            grade_id: props.selectedGradeId,
            subject_id: props.selectedSubjectId,
            chapter_ids: props.selectedChapterIds,
            topic_ids: props.selectedTopicIds,
            sources: allowedSources.value,
            dual_medium: dualMedium.value,
            question_ids: questionIds.value,
            exam_meta: {
                ...examMeta.value,
                class: resolvedClass.value,
                subject: resolvedSubject.value,
            },
            settings: {
                ...settings.value,
                section_settings: sectionSettings.value,
            },
            layout: {
                header_template: 1,
                font_family: 'Arial',
                font_size: 11,
                heading_font_size: 12,
                dual_medium: dualMedium.value,
                enable_omr: settings.value.enable_omr,
                enable_answer_key: settings.value.enable_answer_key,
                enable_watermark: settings.value.enable_watermark,
                watermark_type: settings.value.enable_watermark ? 'text' : 'none',
                watermark_text: settings.value.enable_watermark ? buildWatermarkText(props.institution) : '',
                watermark_opacity: 0.18,
                watermark_angle: 45,
                watermark_size: 22,
                show_past_paper_tags: settings.value.show_past_paper_tags,
                show_note: true,
                show_paper_note: true,
                tabular_mcqs: settings.value.tabular_mcqs,
                questions_per_line: settings.value.questions_per_line,
                section_settings: sectionSettings.value,
                paper_size: 'A4',
                orientation: 'portrait',
                margins: { top: 15, right: 15, bottom: 15, left: 15 },
                paper_content: content,
            },
        },
    }, {
        onFinish: () => { saving.value = false; },
        onSuccess: () => { showSaveModal.value = false; },
    });
};

const printPaper = () => {
    applyPrintStyles();
    document.body.classList.add('print-active');
    window.addEventListener('afterprint', onAfterPrint);
    window.print();
};

const onAfterPrint = () => {
    clearPrintStyles();
    document.body.classList.remove('print-active');
    window.removeEventListener('afterprint', onAfterPrint);
};

onUnmounted(() => {
    clearPrintStyles();
    document.body.classList.remove('print-active');
    window.removeEventListener('afterprint', onAfterPrint);
});

const cancelPaper = () => {
    if (selectedQuestions.value.length && !confirm('Discard this paper and go back?')) return;
    router.visit(route('builder.chapters', {
        grade: props.selectedGradeId,
        subject: props.selectedSubjectId,
    }));
};
</script>

<template>
    <Head :title="`Compose · ${selectedSubject?.name_en || 'Paper'}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Paper Composer</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ gradeLabel }} · {{ selectedSubject?.name_en }}
                        <span class="mx-1 text-gray-300">·</span>
                        {{ selectedQuestions.length }} question(s) on paper
                    </p>
                </div>
                <Link
                    :href="route('builder.chapters', { grade: selectedGradeId, subject: selectedSubjectId })"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    ← Chapters
                </Link>
            </div>
        </template>

        <!-- Action bar -->
        <div class="no-print border-b border-slate-800 bg-slate-900">
            <div class="mx-auto flex max-w-7xl flex-wrap">
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-2 bg-emerald-600 px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-emerald-500 sm:flex-none sm:px-8"
                    @click="showQuestionMenu = true"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    Question's Menu
                </button>
                <button
                    v-if="!editingPaper"
                    type="button"
                    class="flex flex-1 items-center justify-center gap-2 border-l border-slate-700 px-4 py-3.5 text-sm font-medium text-white transition hover:bg-slate-800 sm:flex-none sm:px-6"
                    @click="startEditPaper"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Paper
                </button>
                <template v-else>
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-2 border-l border-slate-700 bg-amber-600 px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-amber-500 sm:flex-none sm:px-6"
                        @click="applyTextEdits"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Apply Text
                    </button>
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-2 border-l border-slate-700 px-4 py-3.5 text-sm font-medium text-white transition hover:bg-slate-800 sm:flex-none sm:px-6"
                        @click="cancelEditPaper"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel Edit
                    </button>
                </template>
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-2 border-l border-slate-700 px-4 py-3.5 text-sm font-medium text-white transition hover:bg-slate-800 sm:flex-none sm:px-6"
                    @click="showEditMeta = true"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Paper Details
                </button>
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-2 border-l border-slate-700 px-4 py-3.5 text-sm font-medium text-white transition hover:bg-slate-800 sm:flex-none sm:px-6"
                    @click="printPaper"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z" />
                    </svg>
                    Print Paper
                </button>
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-2 border-l border-slate-700 px-4 py-3.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:opacity-40 sm:flex-none sm:px-6"
                    :disabled="!selectedQuestions.length || saving"
                    @click="openSaveModal"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    {{ saving ? 'Saving…' : 'Save Paper' }}
                </button>
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-2 border-l border-slate-700 px-4 py-3.5 text-sm font-semibold text-rose-400 transition hover:bg-slate-800 sm:flex-none sm:px-6"
                    @click="cancelPaper"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Cancel Paper
                </button>
            </div>
        </div>

        <div class="no-print bg-slate-100 py-8">
            <div class="mx-auto max-w-5xl px-4">
                <div
                    v-if="addFeedback"
                    class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
                >
                    {{ addFeedback }}
                </div>
                <div
                    v-if="!selectedQuestions.length"
                    class="mb-6 rounded-2xl border border-dashed border-teal-300 bg-white/80 px-6 py-10 text-center"
                >
                    <p class="text-lg font-semibold text-slate-800">Your paper header is ready</p>
                    <p class="mt-2 text-sm text-slate-600">
                        Click <strong>Question's Menu</strong> to search and add questions. You can open it again anytime to add more.
                    </p>
                    <button
                        type="button"
                        class="mt-5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700"
                        @click="showQuestionMenu = true"
                    >
                        Open Question's Menu
                    </button>
                </div>

                <p v-if="editingPaper" class="mb-3 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-700">
                    Click text on the preview to edit. Use <strong>Apply Text</strong> or <strong>Save Paper</strong> when done.
                </p>

                <!-- Screen preview: same Layout Template 1 A4 format as editor -->
                <div class="overflow-x-auto rounded-lg bg-gray-100 py-4">
                    <PaperPreview
                        v-bind="paperPreviewProps"
                        :editable="editingPaper"
                        @update:paper-content="onContentUpdate"
                    />
                </div>
            </div>
        </div>

        <!-- Print clone: same as Layout Editor Template 1 -->
        <Teleport to="body">
            <div id="exam-print-root" class="print-paper-root">
                <PaperPreview v-bind="paperPreviewProps" :editable="false" />
            </div>
        </Teleport>

        <QuestionMenuModal
            :show="showQuestionMenu"
            :grade-label="gradeLabel"
            :subject-name="selectedSubject?.name_en ?? ''"
            :chapters="selectedChapters"
            :initial-topic-ids="selectedTopicIds"
            :chapter-ids="selectedChapterIds"
            :allowed-sources="allowedSources"
            :already-selected-ids="questionIds"
            @close="showQuestionMenu = false"
            @add="onAddQuestions"
        />

        <SavePaperModal
            :show="showSaveModal"
            :form="saveForm"
            :processing="saving"
            :paper-class="resolvedClass"
            :paper-subject="resolvedSubject"
            @close="showSaveModal = false"
            @submit="savePaper"
        />

        <Modal :show="showEditMeta" max-width="lg" @close="showEditMeta = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Edit paper details</h3>
                <p class="mt-1 text-sm text-slate-500">These appear on the paper header.</p>
                <div class="mt-4 space-y-3">
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">Paper title</span>
                        <input v-model="paperTitle" class="mt-1 w-full rounded-lg border-slate-300" />
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">Paper type</span>
                            <input v-model="examMeta.paper_type" class="mt-1 w-full rounded-lg border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">Time</span>
                            <input v-model="examMeta.time" class="mt-1 w-full rounded-lg border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">Maximum marks</span>
                            <input v-model="examMeta.marks" class="mt-1 w-full rounded-lg border-slate-300" />
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">Class</span>
                            <input v-model="examMeta.class" class="mt-1 w-full rounded-lg border-slate-300" />
                        </label>
                        <label class="block text-sm sm:col-span-2">
                            <span class="font-medium text-slate-700">Subject</span>
                            <input v-model="examMeta.subject" class="mt-1 w-full rounded-lg border-slate-300" />
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showEditMeta = false">Done</button>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
@import '../../../css/print.css';
</style>
