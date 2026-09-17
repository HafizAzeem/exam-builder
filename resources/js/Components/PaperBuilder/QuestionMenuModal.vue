<script setup>
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    gradeLabel: { type: String, default: '' },
    subjectName: { type: String, default: '' },
    chapters: { type: Array, default: () => [] },
    initialTopicIds: { type: Array, default: () => [] },
    chapterIds: { type: Array, default: () => [] },
    allowedSources: { type: Array, default: () => ['exercise', 'additional', 'past_paper'] },
    alreadySelectedIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'add']);

const questionType = ref('mcq');
const sources = ref(['exercise', 'additional']);
const requiredCount = ref(15);
const marksPerQuestion = ref(1);
const dualMedium = ref(true);
const choiceQuestions = ref(0);
const blankLines = ref(0);
const tabularMcqs = ref(true);
const showBoardYears = ref(false);
const showParts = ref(true);
const questionsPerLine = ref(1);
const boardName = ref('');
const boardYear = ref('');
const boardOptions = ref([]);
const yearOptions = ref([]);
const searching = ref(false);
const randomizing = ref(false);
const results = ref([]);
const selectedIds = ref([]);
const listFilter = ref('all'); // all | selected
const sourceMenuOpen = ref(false);
const lastAddedCount = ref(0);

const pastPapersSelected = computed(() => sources.value.includes('past_paper'));
const isMcq = computed(() => questionType.value === 'mcq');
const isShort = computed(() => questionType.value === 'short');
const isLong = computed(() => questionType.value === 'long');
const isShortOrLong = computed(() => isShort.value || isLong.value);

const questionTypes = [
    { value: 'mcq', label: 'Multiple Option' },
    { value: 'short', label: 'Short Question' },
    { value: 'long', label: 'Long Question' },
    { value: 'fill', label: 'Fill in the Blanks' },
    { value: 'truefalse', label: 'True / False' },
];

const sourceOptions = computed(() =>
    props.allowedSources.map((value) => ({
        value,
        label: {
            exercise: 'Exercise (مشق)',
            additional: 'Additional (اضافی)',
            past_paper: 'Past Papers (پاسٹ پیپرز)',
        }[value] ?? value,
    })),
);

const sourcesLabel = computed(() => {
    if (!sources.value.length) return 'None selected';
    if (sources.value.length === sourceOptions.value.length) {
        return `All selected (${sources.value.length})`;
    }
    return `${sources.value.length} selected`;
});

const visibleResults = computed(() => {
    if (listFilter.value === 'selected') {
        return results.value.filter((q) => selectedIds.value.includes(q.id));
    }
    return results.value;
});

const selectedCount = computed(() => selectedIds.value.length);

watch(
    () => props.show,
    (open) => {
        if (!open) return;
        sources.value = props.allowedSources.includes('exercise')
            ? props.allowedSources.filter((s) => s !== 'past_paper').slice(0, 2)
            : [...props.allowedSources];
        if (!sources.value.length) sources.value = [...props.allowedSources];
        results.value = [];
        selectedIds.value = [];
        listFilter.value = 'all';
        sourceMenuOpen.value = false;
        lastAddedCount.value = 0;
        boardName.value = '';
        boardYear.value = '';
        applyTypeDefaults(questionType.value);
        if (sources.value.includes('past_paper')) {
            loadPastPaperFilters();
        }
    },
);

watch(questionType, (type) => {
    applyTypeDefaults(type);
    results.value = [];
    selectedIds.value = [];
    lastAddedCount.value = 0;
});

watch(sources, (vals) => {
    if (vals.includes('past_paper')) {
        showBoardYears.value = true;
        loadPastPaperFilters();
    } else {
        showBoardYears.value = false;
        boardName.value = '';
        boardYear.value = '';
    }
}, { deep: true });

const loadPastPaperFilters = async () => {
    if (boardOptions.value.length && yearOptions.value.length) return;
    try {
        const { data } = await axios.get('/api/builder/past-paper-filters');
        boardOptions.value = data.boards ?? [];
        yearOptions.value = data.years ?? [];
    } catch {
        boardOptions.value = [];
        yearOptions.value = [];
    }
};

const applyTypeDefaults = (type) => {
    if (type === 'mcq') {
        requiredCount.value = 15;
        marksPerQuestion.value = 1;
        tabularMcqs.value = true;
        choiceQuestions.value = 0;
        blankLines.value = 0;
        questionsPerLine.value = 1;
    } else if (type === 'short') {
        requiredCount.value = 10;
        marksPerQuestion.value = 2;
        choiceQuestions.value = 5;
        // Lahore board: compact short answers, two-column when dual medium.
        blankLines.value = 0;
        questionsPerLine.value = 2;
        showParts.value = true;
    } else if (type === 'long') {
        requiredCount.value = 4;
        marksPerQuestion.value = 5;
        choiceQuestions.value = 0;
        blankLines.value = 0;
        questionsPerLine.value = 1;
        showParts.value = true;
    } else {
        requiredCount.value = 10;
        marksPerQuestion.value = 1;
        choiceQuestions.value = 0;
        blankLines.value = 0;
        questionsPerLine.value = 1;
    }
};

const normalizeQuestion = (q) => ({
    ...q,
    mcq_options: q.mcq_options ?? q.mcqOptions ?? null,
    past_paper_tag: q.past_paper_tag ?? q.pastPaperTag ?? null,
    parts: q.parts ?? [],
});

const getMcqOptions = (q) => q?.mcq_options ?? q?.mcqOptions ?? null;
const getPastPaperTag = (q) => q?.past_paper_tag ?? q?.pastPaperTag ?? null;

const searchQuestions = async () => {
    if (!props.chapterIds.length) return;
    searching.value = true;
    try {
        const { data } = await axios.get('/api/builder/questions/all', {
            params: {
                chapter_ids: props.chapterIds,
                // Prefer chapter scope only while topics are dummy; still send ids when present.
                topic_ids: undefined,
                sources: sources.value,
                type: questionType.value || undefined,
                board_name: pastPapersSelected.value && boardName.value ? boardName.value : undefined,
                year: pastPapersSelected.value && boardYear.value ? boardYear.value : undefined,
            },
        });
        results.value = (data ?? []).map(normalizeQuestion);
        selectedIds.value = [];
        listFilter.value = 'all';
    } finally {
        searching.value = false;
    }
};

const toggleResult = (id) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
};

const randomSelect = () => {
    const pool = results.value
        .map((q) => q.id)
        .filter((id) => !props.alreadySelectedIds.includes(id));

    const count = Math.min(Number(requiredCount.value) || 0, pool.length);
    if (count <= 0) return;

    const shuffled = [...pool].sort(() => Math.random() - 0.5);
    selectedIds.value = shuffled.slice(0, count);
    listFilter.value = 'selected';
};

const addQuestions = () => {
    const picked = results.value.filter((q) => selectedIds.value.includes(q.id));
    if (!picked.length) return;

    emit('add', {
        questions: picked,
        options: {
            type: questionType.value,
            marks: Number(marksPerQuestion.value) || 1,
            dualMedium: dualMedium.value,
            choiceQuestions: Number(choiceQuestions.value) || 0,
            blankLines: Number(blankLines.value) || 0,
            tabularMcqs: tabularMcqs.value,
            showBoardYears: showBoardYears.value,
            showParts: showParts.value,
            questionsPerLine: Number(questionsPerLine.value) || 1,
        },
    });

    // Keep modal open for repeat adds; clear current selection.
    const addedCount = picked.length;
    selectedIds.value = [];
    // Drop added items from results so they are not re-added accidentally.
    const added = new Set(picked.map((q) => q.id));
    results.value = results.value.filter((q) => !added.has(q.id));
    lastAddedCount.value = addedCount;
};

const toggleSource = (value) => {
    if (sources.value.includes(value)) {
        if (sources.value.length === 1) return;
        sources.value = sources.value.filter((s) => s !== value);
    } else {
        sources.value = [...sources.value, value];
    }
};

const selectAllSources = (checked) => {
    sources.value = checked ? sourceOptions.value.map((s) => s.value) : [sourceOptions.value[0]?.value].filter(Boolean);
};
</script>

<template>
    <Modal :show="show" max-width="7xl" @close="emit('close')">
        <div class="flex max-h-[92vh] flex-col bg-slate-50">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 bg-gradient-to-r from-teal-700 to-teal-600 px-5 py-3 text-white">
                <div class="w-9" aria-hidden="true" />
                <h3 class="text-lg font-bold tracking-tight">
                    {{ gradeLabel }} — {{ subjectName }}
                </h3>
                <button type="button" class="rounded-lg p-1.5 hover:bg-white/15" aria-label="Close" @click="emit('close')">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <!-- Filters -->
                <div class="space-y-3 border-b border-slate-200 bg-slate-100/80 px-5 py-4">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <label class="block">
                            <span class="mb-1 block rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-white">Question Type</span>
                            <select v-model="questionType" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option v-for="t in questionTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </label>

                        <div class="relative block">
                            <span class="mb-1 block rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-white">Selection</span>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm shadow-sm"
                                @click="sourceMenuOpen = !sourceMenuOpen"
                            >
                                <span>{{ sourcesLabel }}</span>
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div
                                v-if="sourceMenuOpen"
                                class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
                            >
                                <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50">
                                    <input
                                        type="checkbox"
                                        class="rounded text-teal-600"
                                        :checked="sources.length === sourceOptions.length"
                                        @change="selectAllSources($event.target.checked)"
                                    />
                                    Select all
                                </label>
                                <label
                                    v-for="opt in sourceOptions"
                                    :key="opt.value"
                                    class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded text-teal-600"
                                        :checked="sources.includes(opt.value)"
                                        @change="toggleSource(opt.value)"
                                    />
                                    {{ opt.label }}
                                </label>
                            </div>
                        </div>

                        <label class="block">
                            <span class="mb-1 block rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-white">Required Questions</span>
                            <input v-model.number="requiredCount" type="number" min="1" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                        </label>

                        <label class="block">
                            <span class="mb-1 block rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-white">Each Question Marks</span>
                            <input v-model.number="marksPerQuestion" type="number" min="1" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                        </label>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <label class="block">
                            <span class="mb-1 block rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-white">Medium</span>
                            <select v-model="dualMedium" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                                <option :value="true">Dual Medium</option>
                                <option :value="false">English Only</option>
                            </select>
                        </label>

                        <!-- Short / Long dependent -->
                        <label v-if="isShortOrLong" class="block">
                            <span class="mb-1 block rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-white">Choice Questions</span>
                            <input v-model.number="choiceQuestions" type="number" min="0" class="w-full rounded-lg border-slate-300 text-sm shadow-sm" placeholder="e.g. Attempt any 5" />
                            <span class="mt-1 block text-[11px] text-slate-500">Shows as “Answer any X” on the paper</span>
                        </label>

                        <label v-if="isShortOrLong" class="block">
                            <span class="mb-1 block rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-white">Blank Lines</span>
                            <input v-model.number="blankLines" type="number" min="0" class="w-full rounded-lg border-slate-300 text-sm shadow-sm" />
                            <span class="mt-1 block text-[11px] text-slate-500">Answer space under each question</span>
                        </label>

                        <!-- Past Papers dependent filters -->
                        <label v-if="pastPapersSelected" class="block">
                            <span class="mb-1 block rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-white">Board</span>
                            <select v-model="boardName" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                                <option value="">All boards</option>
                                <option v-for="b in boardOptions" :key="b" :value="b">{{ b }}</option>
                            </select>
                        </label>

                        <label v-if="pastPapersSelected" class="block">
                            <span class="mb-1 block rounded-md bg-sky-700 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-white">Year</span>
                            <select v-model="boardYear" class="w-full rounded-lg border-slate-300 text-sm shadow-sm">
                                <option value="">All years</option>
                                <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <label v-if="isMcq" class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="tabularMcqs" type="checkbox" class="rounded text-teal-600" />
                            Tabular MCQs
                        </label>
                        <label v-if="isShort" class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="questionsPerLine" type="checkbox" :true-value="2" :false-value="1" class="rounded text-teal-600" />
                            2 Q(s) Per Line
                        </label>
                        <label v-if="isLong" class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="showParts" type="checkbox" class="rounded text-teal-600" />
                            Ques Parts (A/B · Alif/Bay)
                        </label>
                        <label v-if="pastPapersSelected" class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="showBoardYears" type="checkbox" class="rounded text-teal-600" />
                            Show Board &amp; Year on paper
                        </label>
                        <p v-if="!isMcq && !isShortOrLong && !pastPapersSelected" class="text-xs text-slate-400">
                            Extra layout options appear for MCQ / Short / Long, and when Past Papers is selected.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-teal-700/20 transition hover:bg-teal-700 disabled:opacity-50"
                            :disabled="searching || !chapterIds.length"
                            @click="searchQuestions"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            {{ searching ? 'Searching…' : 'Search Questions' }}
                        </button>
                        <p class="text-sm font-medium text-slate-600">
                            Selected
                            <span class="font-bold text-sky-700">{{ selectedCount }}</span>
                            Question(s) From
                            <span class="font-bold text-rose-600">{{ results.length }}</span>
                            <span v-if="lastAddedCount" class="ml-2 font-semibold text-emerald-700">
                                · Added {{ lastAddedCount }} to paper
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Results -->
                <div class="space-y-2 bg-[radial-gradient(circle_at_top,_#f8fafc,_#eef2ff)] px-4 py-4">
                    <div
                        v-for="(q, index) in visibleResults"
                        :key="q.id"
                        class="cursor-pointer rounded-xl border bg-white p-3 shadow-sm transition"
                        :class="selectedIds.includes(q.id)
                            ? 'border-emerald-500 bg-emerald-50 ring-1 ring-emerald-300'
                            : index % 2 === 1
                                ? 'border-slate-200 bg-emerald-50/40'
                                : 'border-slate-200'"
                        @click="toggleResult(q.id)"
                    >
                        <div class="flex gap-3">
                            <input
                                type="checkbox"
                                class="mt-1 rounded text-teal-600"
                                :checked="selectedIds.includes(q.id)"
                                @click.stop
                                @change="toggleResult(q.id)"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <p class="text-sm font-medium text-slate-900">
                                        <span class="mr-2 font-bold text-teal-700">{{ index + 1 }}.</span>
                                        {{ q.text_en }}
                                    </p>
                                    <p v-if="dualMedium && q.text_ur" class="text-sm text-slate-700" dir="rtl">
                                        {{ q.text_ur }}
                                    </p>
                                </div>
                                <p
                                    v-if="showBoardYears && getPastPaperTag(q)"
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    [{{ getPastPaperTag(q).board_name }}, {{ getPastPaperTag(q).year }}]
                                </p>

                                <div
                                    v-if="q.type === 'mcq' && getMcqOptions(q)"
                                    class="mt-2 grid gap-1"
                                    :class="tabularMcqs ? 'grid-cols-2 md:grid-cols-4' : 'grid-cols-1'"
                                >
                                    <div
                                        v-for="letter in ['a', 'b', 'c', 'd']"
                                        :key="letter"
                                        class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"
                                    >
                                        <span class="font-semibold">({{ letter.toUpperCase() }})</span>
                                        {{ getMcqOptions(q)[`option_${letter}_en`] }}
                                        <span
                                            v-if="dualMedium && getMcqOptions(q)[`option_${letter}_ur`]"
                                            class="mt-0.5 block text-slate-600"
                                            dir="rtl"
                                        >
                                            {{ getMcqOptions(q)[`option_${letter}_ur`] }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    v-if="q.type === 'long' && (q.parts ?? []).length"
                                    class="mt-2 space-y-1 border-t border-slate-100 pt-2"
                                >
                                    <p
                                        v-for="(p, pIdx) in q.parts"
                                        :key="p.id ?? pIdx"
                                        class="text-xs text-slate-600"
                                    >
                                        <span class="font-semibold">({{ String.fromCharCode(97 + pIdx) }})</span>
                                        {{ p.text_en }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="!results.length" class="rounded-xl border border-dashed border-slate-300 bg-white/70 px-4 py-10 text-center text-sm text-slate-500">
                        Choose filters, then click <strong>Search Questions</strong>.
                    </p>
                    <p v-else-if="!visibleResults.length" class="rounded-xl border border-dashed border-slate-300 bg-white/70 px-4 py-8 text-center text-sm text-slate-500">
                        No questions in this view.
                    </p>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white px-5 py-3">
                <div class="inline-flex overflow-hidden rounded-full border border-slate-200 bg-slate-50 p-1">
                    <button
                        type="button"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                        :class="listFilter === 'all' ? 'bg-sky-600 text-white shadow' : 'text-slate-600'"
                        @click="listFilter = 'all'"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                        :class="listFilter === 'selected' ? 'bg-sky-600 text-white shadow' : 'text-slate-600'"
                        @click="listFilter = 'selected'"
                    >
                        Selected
                    </button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-sky-800 disabled:opacity-40"
                        :disabled="!results.length || randomizing"
                        @click="randomSelect"
                    >
                        Random Select
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-emerald-700 disabled:opacity-40"
                        :disabled="!selectedCount"
                        @click="addQuestions"
                    >
                        Add Questions +
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
