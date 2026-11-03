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
const boardName = ref('');
const boardYear = ref('');
const boardOptions = ref([]);
const filterError = ref('');
const previewDualMedium = ref(true);
const initializing = ref(false);
let searchVersion = 0;
const yearOptions = ref([]);
const searching = ref(false);
const randomizing = ref(false);
const results = ref([]);
const selectedIds = ref([]);
const listFilter = ref('all'); // all | selected
const sourceMenuOpen = ref(false);
const lastAddedCount = ref(0);
const chapterFilterId = ref('');

const pastPapersSelected = computed(() => sources.value.includes('past_paper'));
const isMcq = computed(() => questionType.value === 'mcq');
const isShort = computed(() => questionType.value === 'short');
const isLong = computed(() => questionType.value === 'long');
const isShortOrLong = computed(() => isShort.value || isLong.value);

const effectiveChapterIds = computed(() => {
    if (chapterFilterId.value) {
        const id = Number(chapterFilterId.value);
        return props.chapterIds.includes(id) ? [id] : props.chapterIds;
    }
    return props.chapterIds;
});

const questionTypes = [
    { value: 'mcq', label: 'Multiple choice' },
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
    async (open) => {
        ++searchVersion;
        if (!open) { searching.value = false; return; }
        initializing.value = true;
        sources.value = props.allowedSources.includes('exercise')
            ? props.allowedSources.filter((s) => s !== 'past_paper').slice(0, 2)
            : [...props.allowedSources];
        if (!sources.value.length) sources.value = [...props.allowedSources];
        results.value = [];
        selectedIds.value = [];
        listFilter.value = 'all';
        sourceMenuOpen.value = false;
        lastAddedCount.value = 0;
        chapterFilterId.value = '';
        boardYear.value = '';
        applyTypeDefaults(questionType.value);
        const loaded = await loadPastPaperFilters(true);
        initializing.value = false;
        if (props.show && loaded) searchQuestions();
    },
);

watch(questionType, (type) => {
    applyTypeDefaults(type);
    results.value = [];
    selectedIds.value = [];
    lastAddedCount.value = 0;
    if (props.show && !initializing.value) searchQuestions();
});

watch(sources, vals => {
    if (!vals.includes('past_paper')) boardYear.value = '';
    if (props.show && !initializing.value) searchQuestions();
}, { deep: true });

const loadPastPaperFilters = async (useDefault = false) => {
    filterError.value = '';
    try {
        const { data } = await axios.get('/api/builder/past-paper-filters');
        boardOptions.value = data.boards ?? [];
        yearOptions.value = data.years ?? [];
        if (useDefault) boardName.value = data.default_board ?? '';
        if (boardName.value && !boardOptions.value.includes(boardName.value)) boardOptions.value.unshift(boardName.value);
        return true;
    } catch {
        filterError.value = 'Could not load board filters. Close and reopen Question Bank to retry.';
        return false;
    }
};

const applyTypeDefaults = type => {
    requiredCount.value = ({mcq:15, short:10, long:4})[type] ?? 10;
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
    if (!effectiveChapterIds.value.length || initializing.value) return;
    filterError.value = '';
    const version = ++searchVersion;
    searching.value = true;
    results.value = [];
    selectedIds.value = [];
    try {
        const { data } = await axios.get('/api/builder/questions/all', {
            params: {
                chapter_ids: effectiveChapterIds.value,
                // Prefer chapter scope only while topics are dummy; still send ids when present.
                topic_ids: undefined,
                sources: sources.value,
                type: questionType.value || undefined,
                board_name: boardName.value || undefined,
                year: pastPapersSelected.value && boardYear.value ? boardYear.value : undefined,
            },
        });
        if (version !== searchVersion || !props.show) return;
        results.value = (data ?? []).map(normalizeQuestion);
        selectedIds.value = [];
        listFilter.value = 'all';
    } catch {
        if (version === searchVersion) { results.value = []; selectedIds.value = []; filterError.value = 'Could not load questions. Please search again.'; }
    } finally {
        if (version === searchVersion) searching.value = false;
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
        options: { type: questionType.value },
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
        <div class="question-bank flex max-h-[88vh] flex-col bg-slate-50" data-question-bank>
            <!-- Header -->
            <div class="bank-header">
                <div class="bank-heading">
                    <span class="bank-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A1.5 1.5 0 015.5 4H20v16H5.5A1.5 1.5 0 014 18.5m0-13v13m0 0A1.5 1.5 0 015.5 17H20M8 8h8M8 12h5" /></svg></span>
                    <div><p class="bank-eyebrow">Build your paper</p><h3>Question bank</h3><p class="bank-context">{{ gradeLabel }} <span aria-hidden="true">·</span> {{ subjectName }}</p></div>
                </div>
                <button type="button" class="bank-close" aria-label="Close question bank" @click="emit('close')"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <!-- Filters -->
                <div class="bank-filters">
                    <div class="bank-section-title"><div><h4>Find the right questions</h4><p>Choose a type and source, then select questions for your paper.</p></div></div>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" :class="pastPapersSelected ? 'xl:grid-cols-[repeat(6,minmax(0,1fr))_auto]' : 'xl:grid-cols-[repeat(5,minmax(0,1fr))_auto]'">
                        <label class="block">
                            <span class="bank-label">Question type</span>
                            <select v-searchable v-model="questionType" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
                                <option v-for="t in questionTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </label>

                        <div class="relative block bank-source">
                            <span id="bank-source-label" class="bank-label">Question source</span>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm shadow-sm"
                                :aria-expanded="sourceMenuOpen" aria-labelledby="bank-source-label" aria-controls="bank-source-options" @click="sourceMenuOpen = !sourceMenuOpen"
                            >
                                <span>{{ sourcesLabel }}</span>
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div
                                v-if="sourceMenuOpen" id="bank-source-options"
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
                            <span class="bank-label">Chapter</span>
                            <select v-searchable
                                v-model="chapterFilterId"
                                class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                                @change="searchQuestions"
                            >
                                <option value="">All selected chapters</option>
                                <option v-for="c in chapters" :key="c.id" :value="c.id">{{ c.number }}. {{ c.title_en }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="bank-label">Questions needed</span>
                            <input v-model.number="requiredCount" type="number" min="1" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500" />
                        </label>

                        <label class="block">
                            <span class="bank-label">Board</span>
                            <select id="bank-board" v-searchable v-model="boardName" class="w-full rounded-lg border-slate-300 text-sm shadow-sm" @change="searchQuestions">
                                <option value="">All boards</option>
                                <option v-for="board in boardOptions" :key="board" :value="board">{{ board }}</option>
                            </select>
                        </label>
                        <label v-if="pastPapersSelected" class="block">
                            <span class="bank-label">Past-paper year</span>
                            <select v-searchable v-model="boardYear" class="w-full rounded-lg border-slate-300 text-sm shadow-sm" @change="searchQuestions">
                                <option value="">All years</option>
                                <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                            </select>
                        </label>
                        <div class="flex items-end self-end">
                        <button
                            type="button"
                            class="bank-button bank-button-primary w-full whitespace-nowrap"
                            :disabled="searching || initializing || !chapterIds.length"
                            @click="searchQuestions"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.5-5.5M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            {{ searching ? 'Searching…' : 'Search Questions' }}
                        </button>
                        </div>
                    </div>
                    <p v-if="filterError" role="alert" class="text-sm text-red-600">{{ filterError }}</p>

                    <div class="pt-1">
                        <p class="bank-results-summary" role="status" aria-live="polite"><strong>{{ results.length }}</strong> questions available<span v-if="lastAddedCount" class="bank-added">{{ lastAddedCount }} added to paper</span></p>
                    </div>
                </div>

                <!-- Results -->
                <div class="bank-results space-y-3" :aria-busy="searching">
                    <div
                        v-for="(q, index) in visibleResults"
                        :key="q.id"
                        class="bank-result cursor-pointer rounded-xl border bg-white p-4 transition"
                        :class="selectedIds.includes(q.id)
                            ? 'border-teal-500 bg-teal-50/50 ring-1 ring-teal-500'
                            : index % 2 === 1
                                ? 'border-slate-200'
                                : 'border-slate-200'"
                        @click="toggleResult(q.id)"
                    >
                        <div class="flex gap-3">
                            <input
                                type="checkbox"
                                class="mt-1 rounded text-teal-600" :aria-label="`Select question ${index + 1}`"
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
                                    <p v-if="previewDualMedium && q.text_ur" class="text-sm text-slate-700" dir="rtl">
                                        {{ q.text_ur }}
                                    </p>
                                </div>
                                <p
                                    v-if="getPastPaperTag(q)"
                                    class="mt-1 text-[11px] font-normal italic leading-tight text-slate-400"
                                >
                                    <template v-if="getPastPaperTag(q).year">Appeared in {{ getPastPaperTag(q).year }} · </template>{{ getPastPaperTag(q).board_name }}
                                </p>

                                <div
                                    v-if="q.type === 'mcq' && getMcqOptions(q)"
                                    class="mt-2 grid grid-cols-2 gap-1 md:grid-cols-4"
                                >
                                    <div
                                        v-for="letter in ['a', 'b', 'c', 'd']"
                                        :key="letter"
                                        class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs"
                                    >
                                        <span class="font-semibold">({{ letter.toUpperCase() }})</span>
                                        {{ getMcqOptions(q)[`option_${letter}_en`] }}
                                        <span
                                            v-if="previewDualMedium && getMcqOptions(q)[`option_${letter}_ur`]"
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

                    <div v-if="!results.length" class="bank-empty" role="status">
                        <span class="bank-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.5-5.5M17 10a7 7 0 11-14 0 7 7 0 0114 0z" /></svg></span>
                        <h4>{{ searching ? 'Finding your questions…' : 'No questions to show yet' }}</h4><p>{{ searching ? 'Please wait while we check the question bank.' : 'Try another question type, chapter or source, then search again.' }}</p>
                    </div>
                    <div v-else-if="!visibleResults.length" class="bank-empty"><h4>No questions selected</h4><p>Open All questions and select the ones you want to add.</p></div>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="bank-footer">
                <div class="bank-view-switch" aria-label="Question view">
                    <button
                        type="button"
                        class="bank-view-button"
                        :class="listFilter === 'all' ? 'active' : ''"
                        :aria-pressed="listFilter === 'all'" @click="listFilter = 'all'"
                    >
                        All questions <span>{{ results.length }}</span>
                    </button>
                    <button
                        type="button"
                        class="bank-view-button"
                        :class="listFilter === 'selected' ? 'active' : ''"
                        :aria-pressed="listFilter === 'selected'" @click="listFilter = 'selected'"
                    >
                        Selected <span>{{ selectedCount }}</span>
                    </button>
                </div>

                <div class="bank-footer-actions">
                    <button
                        type="button"
                        class="bank-button bank-button-secondary"
                        :disabled="!results.length || randomizing || searching || initializing"
                        @click="randomSelect"
                    >
                        Select randomly
                    </button>
                    <button
                        type="button"
                        class="bank-button bank-button-primary"
                        :disabled="!selectedCount || searching || initializing"
                        @click="addQuestions"
                    >
                        {{ selectedCount ? `Add ${selectedCount} question${selectedCount === 1 ? '' : 's'} to paper` : 'Add to paper' }}
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.question-bank{
    color:#334155;
    border-radius:12px;
    overflow:hidden}
    .bank-header{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        padding:22px 26px;
        background:linear-gradient(115deg,#f0fdfa,#fff 75%);
        border-bottom:1px solid #dcece8;
        flex-shrink:0}
        .bank-heading{
            display:flex;
            align-items:center;
            gap:14px;
            min-width:0}
            .bank-icon{
                display:grid;
                place-items:center;
                width:48px;
                height:48px;
                border:1px solid #b8e8df;
                border-radius:14px;
                background:#fff;
                color:#0f766e;
                flex-shrink:0}
                .bank-icon svg{
                    width:26px;
                    height:26px}
                    .bank-eyebrow{
                        font-size:10px;
                        font-weight:700;
                        text-transform:uppercase;
                        letter-spacing:.12em;
                        color:#0f766e}
                        .bank-heading h3{
                            font-size:22px;
                            font-weight:700;
                            letter-spacing:-.025em;
                            line-height:1.35;
                            color:#0f172a}
                            .bank-context{
                                font-size:12px;
                                color:#64748b;
                                margin-top:3px}
                                .bank-context span{
                                    padding:0 5px;
                                    color:#94a3b8}
                                    .bank-close{
                                        display:grid;
                                        place-items:center;
                                        border:1px solid #e2e8f0;
                                        border-radius:9px;
                                        background:#fff;
                                        color:#64748b;
                                        width:36px;
                                        height:36px;
                                        flex-shrink:0}
                                        .bank-close:hover{
                                            background:#f1f5f9;
                                            color:#0f172a}
                                            .bank-filters{
                                                padding:20px 26px;
                                                background:#fff;
                                                border-bottom:1px solid #e2e8f0;
                                                display:grid;
                                                gap:18px}
                                                .bank-section-title h4{
                                                    font-size:14px;
                                                    font-weight:650;
                                                    color:#0f172a}
                                                    .bank-section-title p{
                                                        font-size:12px;
                                                        color:#64748b;
                                                        margin-top:3px}
                                                        .bank-label{
                                                            display:block;
                                                            font-size:12px;
                                                            font-weight:600;
                                                            color:#475569;
                                                            margin-bottom:7px}
                                                            .bank-filters select,.bank-filters input[type=number],.bank-source>button{
                                                                border:1px solid #cbd5e1;
                                                                border-radius:8px;
                                                                box-shadow:0 1px 2px #0f172a04;
                                                                font-size:13px;
                                                                min-height:42px;
                                                                background-color:#fff;
                                                                color:#334155}
                                                                .question-bank input[type=checkbox]{
                                                                    border-color:#94a3b8;
                                                                    cursor:pointer}
                                                                    .question-bank button,.question-bank select{
                                                                        cursor:pointer}
                                                                        .question-bank button:disabled{
                                                                            cursor:not-allowed}
                                                                            .question-bank button:focus-visible,.question-bank input:focus-visible,.question-bank select:focus-visible{
                                                                                outline:2px solid #0d9488;
                                                                                outline-offset:3px}
                                                                                .bank-options{
                                                                                    border:1px solid #e2e8f0;
                                                                                    border-radius:9px;
                                                                                    background:#f8fafc;
                                                                                    padding:11px 14px}
                                                                                    .bank-options label{
                                                                                        font-size:12px}
                                                                                        .bank-button{
                                                                                            display:inline-flex;
                                                                                            align-items:center;
                                                                                            justify-content:center;
                                                                                            gap:8px;
                                                                                            border-radius:9px;
                                                                                            min-height:42px;
                                                                                            padding:10px 17px;
                                                                                            font-size:13px;
                                                                                            font-weight:600;
                                                                                            transition:background .15s,border-color .15s;
                                                                                            white-space:nowrap;
                                                                                            border:1px solid transparent}
                                                                                            .bank-button-primary{
                                                                                                background:#0f766e;
                                                                                                color:#fff;
                                                                                                box-shadow:0 2px 4px #0f766e15}
                                                                                                .bank-button-primary:hover:not(:disabled){
                                                                                                    background:#115e59}
                                                                                                    .bank-button-primary:disabled{
                                                                                                        background:#cbd5e1;
                                                                                                        color:#475569;
                                                                                                        box-shadow:none}
                                                                                                        .bank-button-secondary{
                                                                                                            background:#fff;
                                                                                                            color:#334155;
                                                                                                            border-color:#cbd5e1}
                                                                                                            .bank-button-secondary:hover:not(:disabled){
                                                                                                                background:#f8fafc;
                                                                                                                border-color:#94a3b8}
                                                                                                                .bank-button-secondary:disabled{
                                                                                                                    background:#f8fafc;
                                                                                                                    color:#94a3b8;
                                                                                                                    border-color:#e2e8f0}
                                                                                                                    .bank-results-summary{
                                                                                                                        font-size:12px;
                                                                                                                        color:#64748b}
                                                                                                                        .bank-results-summary strong{
                                                                                                                            color:#334155}
                                                                                                                            .bank-added{
                                                                                                                                display:inline-block;
                                                                                                                                margin-left:10px;
                                                                                                                                background:#f0fdfa;
                                                                                                                                border:1px solid #ccfbf1;
                                                                                                                                color:#0f766e;
                                                                                                                                padding:3px 8px;
                                                                                                                                border-radius:6px}
                                                                                                                                .bank-results{
                                                                                                                                    padding:18px 26px;
                                                                                                                                    background:#f8fafc;
                                                                                                                                    min-height:180px}
                                                                                                                                    .bank-result:hover{
                                                                                                                                        border-color:#5eead4;
                                                                                                                                        box-shadow:0 2px 8px #0f172a06}
                                                                                                                                        .bank-empty{
                                                                                                                                            display:flex;
                                                                                                                                            align-items:center;
                                                                                                                                            justify-content:center;
                                                                                                                                            flex-direction:column;
                                                                                                                                            text-align:center;
                                                                                                                                            min-height:165px;
                                                                                                                                            padding:24px;
                                                                                                                                            border:1px dashed #cbd5e1;
                                                                                                                                            border-radius:12px;
                                                                                                                                            background:#fff}
                                                                                                                                            .bank-empty-icon{
                                                                                                                                                display:grid;
                                                                                                                                                place-items:center;
                                                                                                                                                width:40px;
                                                                                                                                                height:40px;
                                                                                                                                                border-radius:12px;
                                                                                                                                                background:#f0fdfa;
                                                                                                                                                color:#0f766e;
                                                                                                                                                margin-bottom:10px}
                                                                                                                                                .bank-empty-icon svg{
                                                                                                                                                    width:21px;
                                                                                                                                                    height:21px}
                                                                                                                                                    .bank-empty h4{
                                                                                                                                                        font-size:14px;
                                                                                                                                                        font-weight:600;
                                                                                                                                                        color:#334155}
                                                                                                                                                        .bank-empty p{
                                                                                                                                                            font-size:12px;
                                                                                                                                                            color:#64748b;
                                                                                                                                                            margin-top:5px;
                                                                                                                                                            max-width:360px;
                                                                                                                                                            line-height:1.7}
                                                                                                                                                            .bank-footer{
                                                                                                                                                                display:flex;
                                                                                                                                                                align-items:center;
                                                                                                                                                                justify-content:space-between;
                                                                                                                                                                gap:16px;
                                                                                                                                                                padding:15px 26px;
                                                                                                                                                                background:#fff;
                                                                                                                                                                border-top:1px solid #e2e8f0;
                                                                                                                                                                flex-shrink:0}
                                                                                                                                                                .bank-view-switch{
                                                                                                                                                                    display:inline-flex;
                                                                                                                                                                    gap:3px;
                                                                                                                                                                    background:#f1f5f9;
                                                                                                                                                                    border:1px solid #e2e8f0;
                                                                                                                                                                    border-radius:9px;
                                                                                                                                                                    padding:4px}
                                                                                                                                                                    .bank-view-button{
                                                                                                                                                                        display:inline-flex;
                                                                                                                                                                        align-items:center;
                                                                                                                                                                        gap:8px;
                                                                                                                                                                        border-radius:6px;
                                                                                                                                                                        padding:8px 12px;
                                                                                                                                                                        font-size:12px;
                                                                                                                                                                        font-weight:600;
                                                                                                                                                                        color:#64748b;
                                                                                                                                                                        white-space:nowrap}
                                                                                                                                                                        .bank-view-button.active{
                                                                                                                                                                            background:#fff;
                                                                                                                                                                            box-shadow:0 1px 3px #0f172a14;
                                                                                                                                                                            color:#0f766e}
                                                                                                                                                                            .bank-view-button span{
                                                                                                                                                                                background:#e2e8f0;
                                                                                                                                                                                color:#475569;
                                                                                                                                                                                border-radius:5px;
                                                                                                                                                                                padding:1px 6px;
                                                                                                                                                                                font-size:10px}
                                                                                                                                                                                .bank-view-button.active span{
                                                                                                                                                                                    background:#ccfbf1;
                                                                                                                                                                                    color:#115e59}
                                                                                                                                                                                    .bank-footer-actions{
                                                                                                                                                                                        display:flex;
                                                                                                                                                                                        gap:9px;
                                                                                                                                                                                        align-items:center}
                                                                                                                                                                                        @media(max-width:640px){
                                                                                                                                                                                            .bank-header{
                                                                                                                                                                                                padding:16px}
                                                                                                                                                                                                .bank-heading h3{
                                                                                                                                                                                                    font-size:19px}
                                                                                                                                                                                                    .bank-icon{
                                                                                                                                                                                                        width:40px;
                                                                                                                                                                                                        height:40px;
                                                                                                                                                                                                        border-radius:11px}
                                                                                                                                                                                                        .bank-filters{
                                                                                                                                                                                                            padding:16px;
                                                                                                                                                                                                            gap:16px}
                                                                                                                                                                                                            .bank-results{
                                                                                                                                                                                                                padding:16px}
                                                                                                                                                                                                                .bank-footer{
                                                                                                                                                                                                                    padding:12px 16px;
                                                                                                                                                                                                                    flex-wrap:wrap;
                                                                                                                                                                                                                    gap:10px}
                                                                                                                                                                                                                    .bank-footer-actions{
                                                                                                                                                                                                                        width:100%}
                                                                                                                                                                                                                        .bank-footer-actions>.bank-button{
                                                                                                                                                                                                                            flex:1;
                                                                                                                                                                                                                            padding:9px 10px;
                                                                                                                                                                                                                            font-size:12px;
                                                                                                                                                                                                                            white-space:normal}
                                                                                                                                                                                                                            .bank-view-switch{
                                                                                                                                                                                                                                width:100%}
                                                                                                                                                                                                                                .bank-view-button{
                                                                                                                                                                                                                                    flex:1;
                                                                                                                                                                                                                                    justify-content:center}
                                                                                                                                                                                                                                    .bank-context{
                                                                                                                                                                                                                                        font-size:11px}
                                                                                                                                                                                                                                        .question-bank{
                                                                                                                                                                                                                                            max-height:88dvh}
                                                                                                                                                                                                                                            .bank-added{
                                                                                                                                                                                                                                                margin-left:0;
                                                                                                                                                                                                                                                margin-top:5px;
                                                                                                                                                                                                                                                display:block}
                                                                                                                                                                                                                                            }
</style>
