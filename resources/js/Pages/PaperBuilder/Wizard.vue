<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperPreview from '@/Components/PaperPreview.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({ grades: Array, teacherPermissions: Object, institution: Object });

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

const previewLayout = computed(() => ({
    header_template: 1,
    font_family: 'Arial',
    font_size: 12,
    line_height: 1.5,
    dual_medium: dualMedium.value,
    enable_omr: settings.value.enable_omr,
    enable_answer_key: settings.value.enable_answer_key,
    enable_watermark: settings.value.enable_watermark,
    watermark_text: settings.value.enable_watermark ? buildWatermarkText(props.institution) : '',
    watermark_opacity: 0.18,
    watermark_angle: 45,
    show_past_paper_tags: settings.value.show_past_paper_tags,
    show_paper_note: true,
}));

const step = ref(1);
const gradeId = ref(null);
const subjectId = ref(null);
const chapterIds = ref([]);
const sources = ref(['exercise']);
const dualMedium = ref(false);
const subjects = ref([]);
const chapters = ref([]);
const questions = ref([]);
const manualPaginator = ref(null);
const selectedIds = ref([]);
const selectedQuestionById = ref({});
const previewLoading = ref(false);
const selectingAll = ref(false);
const mode = ref('manual');
const randomConfig = ref({ mcq: 0, short: 0, long: 0 });
const randomCacheKey = ref('');
const randomizing = ref(false);
const manualType = ref('');
const manualSearch = ref('');
const perPage = ref(15);
const paperTitle = ref('Mid Term Examination');
const examMeta = ref({ class: '', subject: '', time: '2 Hours', marks: '50' });
const settings = ref({
    blank_lines: 3,
    questions_per_line: 1,
    enable_omr: true,
    enable_answer_key: true,
    enable_watermark: false,
    show_past_paper_tags: false,
});

const allowedCategories = computed(() => props.teacherPermissions?.allowed_categories ?? null);
const allowedSources = computed(() => {
    // If no explicit category restriction, allow all.
    if (!Array.isArray(allowedCategories.value) || !allowedCategories.value.length) {
        return ['exercise', 'additional', 'past_paper'];
    }
    return allowedCategories.value;
});

const allChaptersSelected = computed({
    get() {
        if (!chapters.value.length) return false;
        return chapters.value.every((c) => chapterIds.value.includes(c.id));
    },
    set(value) {
        chapterIds.value = value ? chapters.value.map((c) => c.id) : [];
    },
});

watch(
    allowedSources,
    (allowed) => {
        // Ensure currently selected sources are permitted.
        sources.value = sources.value.filter((s) => allowed.includes(s));
        if (!sources.value.length) {
            sources.value = [allowed[0]];
        }
    },
    { immediate: true },
);

const loadSubjects = async () => {
    if (!gradeId.value) return;
    const { data } = await axios.get(`/api/builder/grades/${gradeId.value}/subjects`);
    subjects.value = data;
    subjectId.value = null;
    chapters.value = [];
    chapterIds.value = [];
};

const loadChapters = async () => {
    if (!subjectId.value) return;
    const { data } = await axios.get(`/api/builder/subjects/${subjectId.value}/chapters`);
    chapters.value = data;
    chapterIds.value = [];
};

const loadQuestions = async () => {
    if (!chapterIds.value.length) return;
    const { data } = await axios.get('/api/builder/questions', {
        params: {
            chapter_ids: chapterIds.value,
            sources: sources.value,
            type: manualType.value || undefined,
            search: manualSearch.value || undefined,
            per_page: perPage.value,
        },
    });
    manualPaginator.value = data;
    questions.value = data.data ?? data;
    syncSelectedFromList(questions.value);
};

const loadPage = async (url) => {
    if (!url) return;
    const { data } = await axios.get(url, {
        params: {
            chapter_ids: chapterIds.value,
            sources: sources.value,
            type: manualType.value || undefined,
            search: manualSearch.value || undefined,
            per_page: perPage.value,
        },
    });
    manualPaginator.value = data;
    questions.value = data.data ?? data;
    syncSelectedFromList(questions.value);
};

const normalizeQuestion = (q) => ({
    ...q,
    mcq_options: q.mcq_options ?? q.mcqOptions ?? null,
    past_paper_tag: q.past_paper_tag ?? q.pastPaperTag ?? null,
    parts: q.parts ?? [],
});

const syncSelectedFromList = (list) => {
    const next = { ...selectedQuestionById.value };
    for (const q of list) {
        if (selectedIds.value.includes(q.id)) {
            next[q.id] = normalizeQuestion(q);
        }
    }
    selectedQuestionById.value = next;
};

const randomize = async (refresh = false) => {
    if (!chapterIds.value.length) return [];
    if (!randomCacheKey.value || refresh) {
        randomCacheKey.value = `random-${gradeId.value}-${subjectId.value}-${Date.now()}`;
    }
    randomizing.value = true;
    try {
        const { data } = await axios.post('/api/builder/questions/random', {
            chapter_ids: chapterIds.value,
            sources: sources.value,
            config: randomConfig.value,
            cache_key: randomCacheKey.value,
            refresh,
        });
        const normalized = data.map(normalizeQuestion);
        selectedIds.value = normalized.map((q) => q.id);
        questions.value = normalized;
        selectedQuestionById.value = Object.fromEntries(normalized.map((q) => [q.id, q]));
        return normalized;
    } finally {
        randomizing.value = false;
    }
};

const randomTargetCount = computed(() =>
    Number(randomConfig.value.mcq || 0)
    + Number(randomConfig.value.short || 0)
    + Number(randomConfig.value.long || 0),
);

const randomSelectionSummary = computed(() => {
    const counts = { mcq: 0, short: 0, long: 0 };
    for (const q of selectedQuestions.value) {
        if (counts[q.type] !== undefined) counts[q.type]++;
    }
    return counts;
});

const randomSelectionStale = computed(
    () => mode.value === 'random'
        && randomTargetCount.value > 0
        && selectedIds.value.length !== randomTargetCount.value,
);

watch(gradeId, loadSubjects);
watch(subjectId, loadChapters);

const toggleQuestion = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) {
        selectedIds.value.splice(idx, 1);
        const next = { ...selectedQuestionById.value };
        delete next[id];
        selectedQuestionById.value = next;
    } else {
        selectedIds.value.push(id);
        const q = questions.value.find((x) => x.id === id);
        if (q) {
            selectedQuestionById.value = {
                ...selectedQuestionById.value,
                [id]: normalizeQuestion(q),
            };
        }
    }
};

const selectedQuestions = computed(() =>
    selectedIds.value.map((id) => selectedQuestionById.value[id]).filter(Boolean),
);

const previewOmrRows = computed(() => {
    if (!settings.value.enable_omr) return [];

    const mcqCount = selectedQuestions.value.filter((question) => question.type === 'mcq').length;

    return Array.from({ length: mcqCount }, (_, index) => ({
        number: index + 1,
        options: ['A', 'B', 'C', 'D'],
    }));
});

const previewAnswerKey = computed(() => {
    if (!settings.value.enable_answer_key) return [];

    let number = 0;

    return selectedQuestions.value
        .filter((question) => question.type === 'mcq')
        .map((question) => {
            number++;
            const options = question.mcq_options ?? question.mcqOptions ?? {};
            const correct = String(options.correct_option ?? '').trim();

            return {
                number,
                answer: correct ? correct.charAt(0).toUpperCase() : '',
            };
        })
        .filter((entry) => entry.answer);
});

const fillExamMetaDefaults = () => {
    if (!examMeta.value.class && gradeId.value) {
        const grade = props.grades?.find((g) => g.id === gradeId.value);
        if (grade) examMeta.value.class = grade.label_en;
    }
    if (!examMeta.value.subject && subjectId.value) {
        const subject = subjects.value.find((s) => s.id === subjectId.value);
        if (subject) examMeta.value.subject = subject.name_en;
    }
};

const loadSelectedQuestionsForPreview = async () => {
    if (!selectedIds.value.length) return;
    previewLoading.value = true;
    try {
        const { data } = await axios.post('/api/builder/questions/by-ids', {
            ids: selectedIds.value,
        });
        const byId = Object.fromEntries(data.map((q) => [q.id, normalizeQuestion(q)]));
        selectedQuestionById.value = Object.fromEntries(
            selectedIds.value.filter((id) => byId[id]).map((id) => [id, byId[id]]),
        );
    } finally {
        previewLoading.value = false;
    }
};

const clearSelection = () => {
    selectedIds.value = [];
    selectedQuestionById.value = {};
};

const selectAllMatchingQuestions = async () => {
    if (!chapterIds.value.length) return;
    selectingAll.value = true;
    try {
        const { data } = await axios.get('/api/builder/questions/all', {
            params: {
                chapter_ids: chapterIds.value,
                sources: sources.value,
                type: manualType.value || undefined,
                search: manualSearch.value || undefined,
            },
        });
        const normalized = data.map(normalizeQuestion);
        selectedIds.value = normalized.map((q) => q.id);
        selectedQuestionById.value = Object.fromEntries(normalized.map((q) => [q.id, q]));
    } finally {
        selectingAll.value = false;
    }
};

watch(step, async (s) => {
    if (s === 4) {
        if (mode.value === 'random' && randomSelectionStale.value) {
            await randomize(true);
        }
        fillExamMetaDefaults();
        await loadSelectedQuestionsForPreview();
    }
});

const proceedToStep3 = async () => {
    if (mode.value === 'random') {
        if (randomTargetCount.value <= 0) return;
        if (randomSelectionStale.value || !selectedIds.value.length) {
            await randomize(true);
        }
        if (!selectedIds.value.length) return;
    } else if (!selectedIds.value.length) {
        return;
    }
    step.value = 3;
};

const savePaper = () => {
    router.post(route('builder.store'), {
        title: paperTitle.value,
        config: {
            grade_id: gradeId.value,
            subject_id: subjectId.value,
            chapter_ids: chapterIds.value,
            sources: sources.value,
            dual_medium: dualMedium.value,
            question_ids: selectedIds.value,
            exam_meta: examMeta.value,
            settings: settings.value,
            layout: {
                header_template: 1,
                font_family: 'Arial',
                font_size: 12,
                dual_medium: dualMedium.value,
                enable_omr: settings.value.enable_omr,
                enable_answer_key: settings.value.enable_answer_key,
                enable_watermark: settings.value.enable_watermark,
                watermark_text: settings.value.enable_watermark ? buildWatermarkText(props.institution) : '',
                watermark_opacity: 0.18,
                watermark_angle: 45,
                show_past_paper_tags: settings.value.show_past_paper_tags,
            },
        },
    });
};
</script>

<template>
    <Head title="Paper Builder" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Paper Generation Wizard</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6">
            <div class="mb-6 flex gap-2">
                <span
                    v-for="n in 4"
                    :key="n"
                    class="rounded-full px-3 py-1 text-sm"
                    :class="step === n ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'"
                >
                    Step {{ n }}
                </span>
            </div>

            <div v-show="step === 1" class="space-y-4 rounded-lg bg-white p-6 shadow">
                <div>
                    <label class="block text-sm font-medium">Grade</label>
                    <select v-model="gradeId" class="mt-1 w-full rounded-md border-gray-300">
                        <option :value="null">Select grade</option>
                        <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.label_en }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Subject</label>
                    <select v-model="subjectId" class="mt-1 w-full rounded-md border-gray-300" :disabled="!subjects.length">
                        <option :value="null">Select subject</option>
                        <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name_en }}</option>
                    </select>
                </div>
                <div>
                    <div class="flex items-center justify-between gap-4">
                        <label class="block text-sm font-medium">Chapters</label>
                        <label
                            v-if="chapters.length"
                            class="flex items-center gap-2 text-sm font-medium text-indigo-600"
                        >
                            <input v-model="allChaptersSelected" type="checkbox" />
                            Select all
                        </label>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <label v-for="c in chapters" :key="c.id" class="flex items-center gap-2 text-sm">
                            <input v-model="chapterIds" type="checkbox" :value="c.id" />
                            Ch {{ c.number }}: {{ c.title_en }}
                        </label>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4">
                    <label v-for="src in allowedSources" :key="src" class="flex items-center gap-2 text-sm">
                        <input v-model="sources" type="checkbox" :value="src" />
                        {{ src }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="dualMedium" type="checkbox" />
                        Dual Medium (EN + UR)
                    </label>
                    <label v-if="sources.includes('past_paper')" class="flex items-center gap-2 text-sm">
                        <input v-model="settings.show_past_paper_tags" type="checkbox" />
                        Show Board Name &amp; Year
                    </label>
                </div>
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-white" @click="step = 2">Next</button>
            </div>

            <div v-show="step === 2" class="rounded-lg bg-white p-6 shadow">
                <div class="mb-4 flex gap-4">
                    <button :class="mode === 'manual' ? 'bg-indigo-600 text-white' : 'bg-gray-200'" class="rounded px-3 py-1" @click="mode = 'manual'; loadQuestions()">Manual</button>
                    <button :class="mode === 'random' ? 'bg-indigo-600 text-white' : 'bg-gray-200'" class="rounded px-3 py-1" @click="mode = 'random'">Random</button>
                </div>

                <div v-if="mode === 'random'" class="mb-4 space-y-3">
                    <div class="flex flex-wrap items-end gap-4">
                        <label class="text-sm">MCQs <input v-model.number="randomConfig.mcq" type="number" min="0" class="w-20 rounded border" /></label>
                        <label class="text-sm">Short <input v-model.number="randomConfig.short" type="number" min="0" class="w-20 rounded border" /></label>
                        <label class="text-sm">Long <input v-model.number="randomConfig.long" type="number" min="0" class="w-20 rounded border" /></label>
                        <button
                            type="button"
                            class="rounded bg-indigo-600 px-4 py-1.5 text-sm text-white disabled:opacity-50"
                            :disabled="randomizing || randomTargetCount <= 0"
                            @click="randomize(true)"
                        >
                            {{ randomizing ? 'Generating…' : 'Generate selection' }}
                        </button>
                    </div>
                    <p class="text-sm text-gray-600">
                        Target: <strong>{{ randomTargetCount }}</strong> questions
                        ({{ randomConfig.mcq || 0 }} MCQ + {{ randomConfig.short || 0 }} Short + {{ randomConfig.long || 0 }} Long)
                        · Loaded: <strong>{{ selectedIds.length }}</strong>
                        <span v-if="selectedIds.length">
                            ({{ randomSelectionSummary.mcq }} MCQ, {{ randomSelectionSummary.short }} Short, {{ randomSelectionSummary.long }} Long)
                        </span>
                    </p>
                    <p v-if="randomSelectionStale" class="text-sm text-amber-600">
                        Counts changed — click <strong>Generate selection</strong> or press Next to apply before preview.
                    </p>
                    <p v-if="selectedIds.length && selectedIds.length < randomTargetCount" class="text-sm text-amber-600">
                        Only {{ selectedIds.length }} of {{ randomTargetCount }} could be loaded. There may not be enough questions in the selected chapters/sources.
                    </p>
                </div>

                <div v-else class="mb-4">
                    <div class="grid gap-3 md:grid-cols-4">
                        <input
                            v-model="manualSearch"
                            class="rounded border-gray-300"
                            placeholder="Search questions..."
                            @keyup.enter="loadQuestions"
                        />
                        <select v-model="manualType" class="rounded border-gray-300">
                            <option value="">All types</option>
                            <option value="mcq">MCQ</option>
                            <option value="short">Short</option>
                            <option value="long">Long</option>
                            <option value="fill">Fill</option>
                            <option value="truefalse">True/False</option>
                        </select>
                        <select v-model.number="perPage" class="rounded border-gray-300">
                            <option :value="10">10 / page</option>
                            <option :value="15">15 / page</option>
                            <option :value="20">20 / page</option>
                            <option :value="30">30 / page</option>
                        </select>
                        <button class="rounded bg-gray-800 px-3 py-2 text-sm text-white" @click="loadQuestions">Search</button>
                    </div>
                </div>

                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-gray-600">Selected: {{ selectedIds.length }} questions</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="mode === 'manual'"
                            type="button"
                            class="rounded border border-indigo-600 px-3 py-1 text-sm text-indigo-600 hover:bg-indigo-50 disabled:opacity-50"
                            :disabled="selectingAll || !chapterIds.length"
                            @click="selectAllMatchingQuestions"
                        >
                            {{ selectingAll ? 'Loading…' : 'Select all matching questions' }}
                        </button>
                        <button
                            v-if="selectedIds.length"
                            type="button"
                            class="rounded border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
                            @click="clearSelection"
                        >
                            Clear selection
                        </button>
                    </div>
                </div>

                <div class="max-h-96 space-y-2 overflow-y-auto">
                    <div
                        v-for="q in questions"
                        :key="q.id"
                        class="cursor-pointer rounded border p-3 text-sm"
                        :class="selectedIds.includes(q.id) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
                        @click="toggleQuestion(q.id)"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <span class="rounded bg-gray-200 px-2 py-0.5 text-xs uppercase">{{ q.type }}</span>
                            <img
                                v-if="q.image_path"
                                :src="`/storage/${q.image_path}`"
                                alt="Question image"
                                class="h-12 w-12 rounded object-cover ring-1 ring-gray-200"
                            />
                        </div>
                        <p class="mt-1">{{ q.text_en }}</p>
                        <p v-if="dualMedium && q.text_ur" class="question-ur mt-1">{{ q.text_ur }}</p>
                    </div>
                </div>

                <div v-if="manualPaginator?.links && mode === 'manual'" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="(lnk, idx) in manualPaginator.links"
                        :key="idx"
                        class="rounded border px-3 py-1 text-sm"
                        :class="lnk.active ? 'bg-indigo-600 text-white' : 'bg-white'"
                        :disabled="!lnk.url"
                        v-html="lnk.label"
                        @click="loadPage(lnk.url)"
                    />
                </div>

                <div class="mt-4 flex gap-2">
                    <button class="rounded bg-gray-300 px-4 py-2" @click="step = 1">Back</button>
                    <button
                        class="rounded bg-indigo-600 px-4 py-2 text-white"
                        :disabled="(mode === 'random' ? randomTargetCount <= 0 : !selectedIds.length) || randomizing"
                        @click="proceedToStep3"
                    >
                        Next
                    </button>
                </div>
            </div>

            <div v-show="step === 3" class="space-y-4 rounded-lg bg-white p-6 shadow">
                <input v-model="paperTitle" class="w-full rounded border-gray-300" placeholder="Paper title" />
                <div class="grid grid-cols-2 gap-4">
                    <input v-model="examMeta.class" placeholder="Class" class="rounded border-gray-300" />
                    <input v-model="examMeta.subject" placeholder="Subject" class="rounded border-gray-300" />
                    <input v-model="examMeta.time" placeholder="Time" class="rounded border-gray-300" />
                    <input v-model="examMeta.marks" placeholder="Total marks" class="rounded border-gray-300" />
                </div>
                <label class="flex items-center gap-2 text-sm"><input v-model="settings.enable_omr" type="checkbox" /> OMR Bubble Sheet</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="settings.enable_answer_key" type="checkbox" /> Teacher Answer Key</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="settings.enable_watermark" type="checkbox" /> Watermark</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="settings.show_past_paper_tags" type="checkbox" /> Show past paper board &amp; year</label>
                <div class="flex gap-2">
                    <button class="rounded bg-gray-300 px-4 py-2" @click="step = 2">Back</button>
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white" @click="step = 4">Preview</button>
                </div>
            </div>

            <div v-show="step === 4" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-4 py-3 shadow">
                    <p class="text-sm text-gray-600">
                        <span class="font-medium text-gray-900">{{ selectedQuestions.length }}</span>
                        of
                        <span class="font-medium text-gray-900">{{ selectedIds.length }}</span>
                        selected question(s) in preview
                    </p>
                    <p v-if="previewLoading" class="text-sm text-indigo-600">Loading all questions…</p>
                    <p
                        v-else-if="selectedQuestions.length < selectedIds.length"
                        class="text-sm text-amber-600"
                    >
                        Some questions could not be loaded.
                    </p>
                </div>

                <div class="overflow-x-auto rounded-lg bg-gray-100 p-4 md:p-6">
                    <PaperPreview
                        v-if="selectedQuestions.length"
                        :title="paperTitle"
                        :questions="selectedQuestions"
                        :dual-medium="dualMedium"
                        :settings="settings"
                        :exam-meta="examMeta"
                        :layout="previewLayout"
                        :institution="institution"
                        :omr-rows="previewOmrRows"
                        :answer-key="previewAnswerKey"
                    />
                    <p v-else class="py-12 text-center text-gray-500">No questions selected. Go back to Step 2 and select questions.</p>
                </div>

                <div class="flex gap-2">
                    <button class="rounded bg-gray-300 px-4 py-2" @click="step = 3">Back</button>
                    <button
                        class="rounded bg-green-600 px-4 py-2 text-white"
                        :disabled="!selectedQuestions.length || previewLoading"
                        @click="savePaper"
                    >
                        Save Paper
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
