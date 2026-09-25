<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperPreview from '@/Components/PaperPreview.vue';
import QuestionMenuModal from '@/Components/PaperBuilder/QuestionMenuModal.vue';
import SavePaperModal from '@/Components/LayoutEditor/SavePaperModal.vue';
import Modal from '@/Components/Modal.vue';
import { buildPaperContentFromPreview, clonePaperContent, DEFAULT_PAPER_NOTE } from '@/utils/paperContent';
import { applyPrintStyles, clearPrintStyles, measurePrintFitScale } from '@/utils/printStyles';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

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
    useSystemQuestionBank: { type: Boolean, default: false },
    pendingQuestionIds: { type: Array, default: () => [] },
    aiToolsAllowed: { type: Boolean, default: true },
});

const showQuestionMenu = ref(false);
const showEditMeta = ref(false);
const showSaveModal = ref(false);
const showPrintLandscapeHint = ref(false);
const saving = ref(false);
const paperTitle = ref('Mid Term Examination');
const editingPaper = ref(false);
const editDraft = ref(null);
const paperContent = ref(null);
const pageView = ref('single'); // single | double (2-up print)

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
    blank_lines: 0,
    questions_per_line: 1,
    tabular_mcqs: true,
    enable_omr: true,
    enable_answer_key: true,
    enable_watermark: true,
    show_past_paper_tags: true,
});

const sectionSettings = ref({
    mcq: { marks: 1, tabular_mcqs: true },
    short: { marks: 2, blank_lines: 0, questions_per_line: 1, choice_questions: 0 },
    long: { marks: 5, blank_lines: 0, choice_questions: 0, show_parts: true },
    fill: { marks: 1 },
    truefalse: { marks: 1 },
});

const dualMedium = ref(false);
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
    line_height: 1.35,
    dual_medium: dualMedium.value,
    enable_omr: settings.value.enable_omr,
    enable_answer_key: settings.value.enable_answer_key,
    enable_watermark: settings.value.enable_watermark,
    watermark_type: settings.value.enable_watermark ? 'text' : 'none',
    watermark_text: settings.value.enable_watermark ? buildWatermarkText(props.institution) : '',
    watermark_opacity: 0.16,
    watermark_angle: 45,
    watermark_size: 20,
    show_past_paper_tags: settings.value.show_past_paper_tags,
    show_note: true,
    show_paper_note: true,
    tabular_mcqs: settings.value.tabular_mcqs,
    questions_per_line: settings.value.questions_per_line,
    section_settings: sectionSettings.value,
    paper_size: 'A4',
    orientation: pageView.value === 'double' ? 'landscape' : 'portrait',
    page_view: pageView.value,
    scale: 100,
    // Tighter Lahore-board style margins for A4 fitting
    margins: { top: 8, right: 8, bottom: 8, left: 8 },
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

const onAddQuestions = ({ questions, options, silent = false }) => {
    const existing = new Set(questionIds.value);
    const toAdd = questions.filter((q) => !existing.has(q.id));
    if (!toAdd.length) {
        if (!silent) {
            addFeedback.value = 'Those questions are already on the paper.';
        }
        showQuestionMenu.value = false;
        return;
    }

    selectedQuestions.value = [...selectedQuestions.value, ...toAdd];
    if (!silent) {
        addFeedback.value = `Added ${toAdd.length} question(s) to the paper.`;
    }

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

    // Always rebuild paper content so the preview matches the Layout Editor.
    const previous = editingPaper.value ? editDraft.value : paperContent.value;
    const merged = previous
        ? mergePaperContentEdits(buildCurrentPaperContent(), previous)
        : buildCurrentPaperContent();
    paperContent.value = merged;
    if (editingPaper.value) {
        editDraft.value = clonePaperContent(merged);
    }

    showQuestionMenu.value = false;
    nextTick(() => {
        document.getElementById('compose-paper-preview')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

const composeQuery = computed(() => ({
    grade: props.selectedGradeId,
    subject: props.selectedSubjectId,
    chapters: (props.selectedChapterIds || []).join(','),
}));

const aiPasteUrl = computed(() => route('builder.ai.paste', composeQuery.value));
const aiGenerateUrl = computed(() => route('builder.ai.generate', composeQuery.value));
const aiExtractPastPaperUrl = computed(() => route('builder.ai.extract-past-paper', composeQuery.value));

const loadPendingQuestions = async () => {
    const ids = (props.pendingQuestionIds || []).map((id) => Number(id)).filter(Boolean);
    if (!ids.length) return;

    try {
        const res = await fetch('/api/builder/questions/by-ids', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ ids }),
        });
        if (!res.ok) return;
        const questions = await res.json();
        // FlashBanner already shows server success — avoid a second banner.
        onAddQuestions({ questions: Array.isArray(questions) ? questions : [], silent: true });
    } catch {
        // ignore
    }
};

onMounted(() => {
    loadPendingQuestions();
});

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
                watermark_opacity: 0.16,
                watermark_angle: 45,
                watermark_size: 20,
                show_past_paper_tags: settings.value.show_past_paper_tags,
                show_note: true,
                show_paper_note: true,
                tabular_mcqs: settings.value.tabular_mcqs,
                questions_per_line: settings.value.questions_per_line,
                section_settings: sectionSettings.value,
                paper_size: 'A4',
                orientation: pageView.value === 'double' ? 'landscape' : 'portrait',
                page_view: pageView.value,
                margins: { top: 8, right: 8, bottom: 8, left: 8 },
                paper_content: content,
            },
        },
    }, {
        onFinish: () => { saving.value = false; },
        onSuccess: () => { showSaveModal.value = false; },
    });
};

const runPrint = () => {
    const dual = pageView.value === 'double';
    // Measure before print-active so we can auto-fit to 1 page when close.
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
    if (pageView.value === 'double') {
        showPrintLandscapeHint.value = true;
        return;
    }
    runPrint();
};

const confirmDualPrint = () => {
    showPrintLandscapeHint.value = false;
    runPrint();
};

const onAfterPrint = () => {
    clearPrintStyles();
    document.body.classList.remove('print-active', 'print-dual', 'print-single');
    window.removeEventListener('afterprint', onAfterPrint);
};

onUnmounted(() => {
    clearPrintStyles();
    document.body.classList.remove('print-active', 'print-dual', 'print-single');
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
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-40"
                        :disabled="!selectedQuestions.length || saving"
                        @click="openSaveModal"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40"
                        :disabled="!selectedQuestions.length"
                        @click="printPaper"
                    >
                        Print
                    </button>
                    <Link
                        :href="route('builder.chapters', { grade: selectedGradeId, subject: selectedSubjectId })"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        ← Chapters
                    </Link>
                </div>
            </div>
        </template>

        <!-- Light sticky toolbar: Add | Adjust | Discard -->
        <div class="no-print sticky top-0 z-20 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-2 px-3 py-2.5 sm:gap-3 sm:px-6">
                <!-- Add -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="hidden text-[10px] font-semibold uppercase tracking-wide text-slate-400 sm:inline">Add</span>
                    <button
                        v-if="useSystemQuestionBank"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500"
                        @click="showQuestionMenu = true"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        Question menu
                    </button>
                    <Link
                        v-if="aiToolsAllowed"
                        :href="aiGenerateUrl"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-white shadow-sm"
                        :class="useSystemQuestionBank ? 'bg-teal-700 hover:bg-teal-600' : 'bg-emerald-600 hover:bg-emerald-500'"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        AI Generate
                    </Link>
                    <Link
                        v-if="aiToolsAllowed"
                        :href="aiExtractPastPaperUrl"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-800 hover:bg-indigo-100"
                    >
                        Extract past paper
                    </Link>
                    <Link
                        v-if="aiToolsAllowed"
                        :href="aiPasteUrl"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Paste text
                    </Link>
                </div>

                <div class="hidden h-6 w-px bg-slate-200 sm:block" aria-hidden="true" />

                <!-- Adjust -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="hidden text-[10px] font-semibold uppercase tracking-wide text-slate-400 sm:inline">Paper</span>
                    <template v-if="!editingPaper">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-40"
                            :disabled="!selectedQuestions.length"
                            @click="startEditPaper"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </button>
                    </template>
                    <template v-else>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-400"
                            @click="applyTextEdits"
                        >
                            Apply edits
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            @click="cancelEditPaper"
                        >
                            Cancel edit
                        </button>
                    </template>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        @click="showEditMeta = true"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Details
                    </button>
                </div>

                <div class="ml-auto flex items-center gap-1.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50"
                        @click="cancelPaper"
                    >
                        Discard
                    </button>
                </div>
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
                        <template v-if="aiToolsAllowed">
                            Use <strong>AI Generate</strong>, <strong>Extract past paper</strong>, or <strong>Paste text</strong> to create questions, review them, then add to this paper.
                        </template>
                        <template v-else-if="useSystemQuestionBank">
                            Click <strong>Question menu</strong> to search and add questions.
                        </template>
                        <template v-else>
                            Select class 9–12 to use AI paper tools.
                        </template>
                    </p>
                    <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            v-if="aiToolsAllowed"
                            :href="aiGenerateUrl"
                            class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700"
                        >
                            AI Generate
                        </Link>
                        <Link
                            v-if="aiToolsAllowed"
                            :href="aiExtractPastPaperUrl"
                            class="rounded-xl border border-indigo-300 bg-indigo-50 px-5 py-2.5 text-sm font-semibold text-indigo-800 hover:bg-indigo-100"
                        >
                            Extract past paper
                        </Link>
                        <Link
                            v-if="aiToolsAllowed"
                            :href="aiPasteUrl"
                            class="rounded-xl border border-emerald-600 px-5 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
                        >
                            Paste text
                        </Link>
                        <button
                            v-if="useSystemQuestionBank"
                            type="button"
                            class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-emerald-700"
                            @click="showQuestionMenu = true"
                        >
                            Open Question menu
                        </button>
                    </div>
                </div>

                <p v-if="editingPaper" class="mb-3 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-700">
                    Click text on the preview to edit. Use <strong>Apply Text</strong> or <strong>Save Paper</strong> when done.
                </p>

                <!-- Screen preview: same Layout Template 1 A4 format as editor -->
                <div id="compose-paper-preview" class="overflow-x-auto rounded-lg bg-gray-100 py-4">
                    <div v-if="pageView === 'double'" class="mx-auto">
                        <p class="mb-2 text-center text-xs font-medium text-slate-500">
                            Dual page · two complete copies side-by-side (print in Landscape)
                        </p>
                        <div class="flex flex-wrap justify-center gap-3">
                            <PaperPreview
                                v-bind="paperPreviewProps"
                                :editable="editingPaper"
                                @update:paper-content="onContentUpdate"
                            />
                            <PaperPreview
                                v-bind="paperPreviewProps"
                                :editable="false"
                            />
                        </div>
                    </div>
                    <PaperPreview
                        v-else
                        v-bind="paperPreviewProps"
                        :editable="editingPaper"
                        @update:paper-content="onContentUpdate"
                    />
                </div>
            </div>
        </div>

        <!-- Print clone: same as Layout Editor Template 1 -->
        <Teleport to="body">
            <div
                id="exam-print-root"
                class="print-paper-root"
                :class="{ 'exam-print-dual': pageView === 'double' }"
            >
                <div v-if="pageView === 'double'" class="exam-print-dual-sheet">
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

        <QuestionMenuModal
            v-if="useSystemQuestionBank"
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
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
                            <input
                                v-model="pageView"
                                type="checkbox"
                                class="mt-0.5 rounded text-teal-600"
                                true-value="double"
                                false-value="single"
                            />
                            <span>
                                <span class="block text-sm font-medium text-slate-800">Dual page print (2 copies / sheet)</span>
                                <span class="mt-0.5 block text-xs text-slate-500">
                                    Prints two complete papers on one landscape A4 sheet to save paper.
                                </span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
                            <input v-model="settings.enable_watermark" type="checkbox" class="mt-0.5 rounded text-teal-600" />
                            <span>
                                <span class="block text-sm font-medium text-slate-800">Protect print (watermark)</span>
                                <span class="mt-0.5 block text-xs text-slate-500">
                                    Overlays institute name on the paper to discourage unauthorized copies.
                                </span>
                            </span>
                        </label>
                        <label class="block text-sm sm:col-span-2">
                            <span class="font-medium text-slate-700">Language on paper</span>
                            <select
                                class="mt-1 w-full rounded-lg border-slate-300"
                                :value="dualMedium ? 'both' : 'english'"
                                @change="dualMedium = $event.target.value === 'both'"
                            >
                                <option value="english">English only (default)</option>
                                <option value="both">English + Urdu (dual medium)</option>
                            </select>
                            <span class="mt-1 block text-xs text-slate-500">Urdu-only display uses Urdu text when present on each question.</span>
                        </label>
                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:col-span-2">
                            <input
                                v-model="settings.questions_per_line"
                                type="checkbox"
                                class="mt-0.5 rounded text-teal-600"
                                :true-value="2"
                                :false-value="1"
                            />
                            <span>
                                <span class="block text-sm font-medium text-slate-800">Two short questions per line</span>
                                <span class="mt-0.5 block text-xs text-slate-500">
                                    Off = one question per line. On = two short questions side by side when layout allows.
                                </span>
                            </span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showEditMeta = false">Done</button>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="showPrintLandscapeHint" max-width="md" @close="showPrintLandscapeHint = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Print dual page</h3>
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
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                        @click="confirmDualPrint"
                    >
                        Continue to print
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
@import '../../../css/print.css';
</style>
