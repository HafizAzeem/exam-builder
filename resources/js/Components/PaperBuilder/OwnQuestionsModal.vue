<script setup>
import Modal from '@/Components/Modal.vue';
import CurriculumDropdowns from '@/Components/CurriculumDropdowns.vue';
import { selectionForChapter, copySelection } from '@/Support/curriculumSelection.mjs';
import axios from 'axios';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    inline: { type: Boolean, default: false },
    draftScope: { type: String, default: '' },
    show: { type: Boolean, default: false },
    chapters: { type: Array, default: () => [] },
    selectedChapterIds: { type: Array, default: () => [] },
    gradeId: { type: [Number, String], default: null },
    subjectId: { type: [Number, String], default: null },
    alreadyOnPaperTexts: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'add']);

let rowSeq = 1;
const emptyMcq = () => ({
    option_a_en: '',
    option_b_en: '',
    option_c_en: '',
    option_d_en: '',
    correct_option: 'a',
    option_diagrams: {},
});

const defaultChapterId = () => Number(props.selectedChapterIds?.[0] || (!props.inline && props.chapters?.[0]?.id) || '') || '';

const emptyRow = (from = null) => ({
    key: rowSeq++,
    type: from?.type || 'short',
    source: from?.source || 'additional',
    board_name: from?.source === 'past_paper' ? (from.board_name || '') : '',
    ...(props.inline ? (from ? copySelection(from) : selectionForChapter(props.chapters, defaultChapterId())) : { chapter_id: from?.chapter_id || defaultChapterId() }),
    includeUrdu: !!from?.includeUrdu,
    text_en: '',
    text_ur: '',
    correct_answer: '',
    mcq: emptyMcq(),
    diagram: null,
});

const draft = reactive({
    rows: props.inline ? [emptyRow()] : [emptyRow(), emptyRow(), emptyRow()],
});

const saving = ref(false);
const error = ref('');
const ready = ref(false);
const boards = ref([]);
const loadingBoards = ref(false);
const boardError = ref('');

const loadBoards = async () => {
    if (loadingBoards.value) return;
    loadingBoards.value = true;
    boardError.value = '';
    try {
        const { data } = await axios.get('/api/builder/past-paper-filters');
        boards.value = data.boards || [];
    } catch {
        boardError.value = 'Could not load boards. Please try again.';
    } finally {
        loadingBoards.value = false;
    }
};

const storageKey = computed(() =>
    props.inline ? `own-questions:bank:${props.draftScope}` : `own-questions:exam:${props.gradeId || 'g'}:${props.subjectId || 's'}`,
);

const rowFromDraft = (row, parsed = {}) => ({
    key: rowSeq++,
    type: row.type || parsed.type || 'short',
    source: row.source || 'additional',
    board_name: row.board_name || '',
    ...(props.inline ? (row.chapter_id ? selectionForChapter(props.chapters, row.chapter_id) : copySelection(row)) : { chapter_id: Number(row.chapter_id || parsed.chapter_id) || defaultChapterId() }),
    includeUrdu: row.includeUrdu ?? !!parsed.includeUrdu,
    text_en: row.text_en || '',
    text_ur: row.text_ur || '',
    correct_answer: row.correct_answer || '',
    mcq: { ...emptyMcq(), ...(row.mcq || {}) },
    diagram: row.diagram || null,
});

const loadDraft = () => {
    try {
        const raw = localStorage.getItem(storageKey.value);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed.rows) && parsed.rows.length) {
            draft.rows = parsed.rows.map((row) => rowFromDraft(row, parsed));
        }
    } catch {
        // ignore bad draft
    }
};

const persistDraft = () => {
    try {
        localStorage.setItem(storageKey.value, JSON.stringify({
            rows: draft.rows.map((row) => ({
                type: row.type,
                source: row.source,
                board_name: row.board_name,
                chapter_id: row.chapter_id,
                grade_id: row.grade_id,
                subject_id: row.subject_id,
                includeUrdu: row.includeUrdu,
                text_en: row.text_en,
                text_ur: row.text_ur,
                correct_answer: row.correct_answer,
                mcq: row.mcq,
                diagram: row.diagram,
            })),
        }));
    } catch {
        // quota
    }
};

const clearDraft = () => {
    try {
        localStorage.removeItem(storageKey.value);
    } catch {
        // ignore
    }
};

watch(
    () => props.show,
    (open) => {
        if (!open) {
            ready.value = false;
            return;
        }
        error.value = '';
        loadBoards();
        ready.value = false;
        loadDraft();
        const fallback = defaultChapterId();
        draft.rows.forEach((row) => {
            if (!row.chapter_id && fallback) {
                row.chapter_id = fallback;
            }
        });
        ready.value = true;
    }, { immediate: true },
);

watch(draft, () => {
    if (props.show && ready.value) persistDraft();
}, { deep: true });

const addRow = () => {
    if (draft.rows.length >= 50 || saving.value) return;
    draft.rows.push(emptyRow(draft.rows[draft.rows.length - 1]));
};

const removeRow = (index) => {
    if (draft.rows.length === 1) {
        draft.rows[0] = emptyRow();
        return;
    }
    draft.rows.splice(index, 1);
};

const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();

const filledRows = computed(() => {
    const seen = new Set(props.alreadyOnPaperTexts.map(normalize).filter(Boolean));
    const out = [];
    draft.rows.forEach((row) => {
        const en = row.text_en.trim();
        const ur = row.includeUrdu ? row.text_ur.trim() : '';
        if (!en && !ur) return;
        const key = `${normalize(en)}|${normalize(ur)}`;
        if (!props.inline && !row.diagram && !Object.keys(row.mcq.option_diagrams || {}).length && (seen.has(normalize(en)) || seen.has(normalize(ur)) || seen.has(key))) return;
        seen.add(key);
        if (en) seen.add(normalize(en));
        if (ur) seen.add(normalize(ur));
        if (!props.inline && row.type === 'mcq' && !row.mcq.option_a_en && !row.mcq.option_b_en && !Object.keys(row.mcq.option_diagrams || {}).length) return;
        out.push({
            type: row.type,
            source: row.source,
            board_name: row.source === 'past_paper' ? row.board_name : null,
            chapter_id: Number(row.chapter_id),
            text_en: en || null,
            text_ur: ur || null,
            correct_answer: row.type === 'truefalse' ? (row.correct_answer || null) : null,
            mcq: row.type === 'mcq' ? { ...row.mcq } : null,
            diagram: row.diagram,
        });
    });
    return out;
});

const submit = async () => {
    if (!filledRows.value.length) {
        error.value = 'Add at least one question row.';
        return;
    }
    if (filledRows.value.some((row) => !row.chapter_id)) {
        error.value = 'Select a chapter on each filled row.';
        return;
    }
    if (filledRows.value.some((row) => row.source === 'past_paper' && !row.board_name)) {
        error.value = 'Select a board for each Past Paper question.';
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        const res = await fetch(route('questions.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ questions: filledRows.value }),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
            const first = json.message
                || Object.values(json.errors || {}).flat()[0];
            throw new Error(first || 'Could not save questions.');
        }
        emit('add', {
            questions: json.questions || [],
        });
        ready.value = false;
        clearDraft();
        draft.rows = props.inline ? [emptyRow()] : [emptyRow(), emptyRow(), emptyRow()];
        if (!props.inline) emit('close');
    } catch (e) {
        error.value = e.message || 'Could not save questions.';
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <component :is="inline ? 'section' : Modal" v-bind="inline ? {} : { show, maxWidth: '6xl' }" :class="inline ? 'rounded-2xl border border-slate-200 bg-white' : ''" @close="emit('close')">
        <fieldset :disabled="saving" class="flex min-w-0 flex-col" :class="inline ? '' : 'max-h-[90vh]'" data-own-questions>
            <div class="border-b px-4 py-3">
                <h3 class="text-lg font-semibold text-slate-900">{{ inline ? 'Add questions to your school bank' : 'Add my questions' }}</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Choose a type, source and chapter for each question. Add rows, then submit once.
                </p>
                <button v-if="inline" type="button" :disabled="draft.rows.length >= 50" @click="addRow" class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-bold text-blue-700 disabled:opacity-50">+ Add row</button>
                <p v-if="error" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    {{ error }}
                </p>
            </div>

            <div class="flex-1 space-y-3 px-4 py-3" :class="inline ? 'overflow-visible' : 'overflow-y-auto'">
                <div
                    v-for="(row, index) in draft.rows"
                    :key="row.key"
                    :data-question-row="index"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Question {{ index + 1 }}</span>
                        <button type="button" class="text-xs font-medium text-rose-600 hover:underline" @click="removeRow(index)">
                            Remove
                        </button>
                    </div>
                    <CurriculumDropdowns v-if="inline" :model-value="row" :chapters="chapters" class="mb-4" @update:model-value="Object.assign(row, $event)" />
                    <div class="mb-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="text-sm">
                            <span class="font-medium text-slate-700">Type</span>
                            <select v-model="row.type" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option value="mcq">MCQ</option>
                                <option value="short">Short</option>
                                <option value="long">Long</option>
                                <option value="fill">Fill</option>
                                <option value="truefalse">True / False</option>
                            </select>
                        </label>
                        <label class="text-sm">
                            <span class="font-medium text-slate-700">Question source</span>
                            <select v-model="row.source" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option value="additional">Additional</option>
                                <option value="exercise">Exercise</option>
                                <option value="past_paper">Past Paper</option>
                            </select>
                        </label>
                        <label v-if="!inline" class="text-sm">
                            <span class="font-medium text-slate-700">Chapter</span>
                            <select v-model="row.chapter_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option v-for="c in chapters" :key="c.id" :value="c.id">{{ inline ? `${c.subject?.grade?.label_en || ''} · ${c.subject?.name_en || ''} · ` : '' }}{{ c.number }}. {{ c.title_en }}</option>
                            </select>
                        </label>
                        <div v-if="row.source === 'past_paper'" class="min-w-0 text-sm">
                            <label class="block text-sm">
                                <span class="font-medium text-slate-700">Board <span class="text-rose-600" aria-hidden="true">*</span></span>
                                <select v-model="row.board_name" :disabled="loadingBoards" required class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                    <option value="">{{ loadingBoards ? 'Loading boards…' : 'Select a board' }}</option>
                                    <option v-for="board in boards" :key="board" :value="board">{{ board }}</option>
                                </select>
                            </label>
                            <p v-if="boardError" class="mt-2 text-xs text-rose-700">{{ boardError }} <button type="button" class="font-semibold underline" @click="loadBoards">Retry</button></p>
                            <p v-else-if="!loadingBoards && !boards.length" class="mt-2 text-xs text-slate-600">No active boards are available. Ask your administrator to enable a board.</p>
                        </div>
                        <label class="mt-6 flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="row.includeUrdu" type="checkbox" class="rounded border-slate-300 text-indigo-600" />
                            Add Urdu text
                        </label>
                    </div>
                    <div class="grid gap-2" :class="row.includeUrdu ? 'sm:grid-cols-2' : ''">
                        <label class="text-xs font-semibold text-slate-700">English question<textarea v-model="row.text_en" rows="2" class="mt-1 w-full resize-y rounded-lg border-slate-300 text-sm" :placeholder="`English question ${index + 1}`" /></label>
                        <label v-if="row.includeUrdu" class="text-xs font-semibold text-slate-700">Urdu question<textarea v-model="row.text_ur" rows="2" class="mt-1 w-full resize-y rounded-lg border-slate-300 text-sm" dir="rtl" :placeholder="`اردو سوال ${index + 1}`" /></label>
                    </div>
                    <div v-if="row.type === 'mcq'" class="mt-3">
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <div v-for="key in ['A','B','C','D']" :key="key" class="min-w-0 rounded-lg border bg-white p-2">
                                <label class="text-xs font-semibold text-slate-700">Option {{ key }}<input v-model="row.mcq[`option_${key.toLowerCase()}_en`]" class="mt-1 w-full rounded-lg border-slate-300 py-1.5 text-sm" :placeholder="`Option ${key}`" /></label>
                            </div>
                        </div>
                        <label class="mt-2 inline-flex items-center gap-2 text-xs font-semibold">Correct option<select v-model="row.mcq.correct_option" class="rounded-lg border-slate-300 py-1.5 text-sm"><option v-for="key in ['a','b','c','d']" :key="key" :value="key">{{ key.toUpperCase() }}</option></select></label>
                    </div>
                    <select
                        v-if="row.type === 'truefalse'"
                        v-model="row.correct_answer"
                        class="mt-2 rounded-lg border-slate-300 text-sm"
                    >
                        <option value="">Correct answer (optional)</option>
                        <option value="true">True</option>
                        <option value="false">False</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3">
                <button
                    type="button"
                    :disabled="draft.rows.length >= 50"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="addRow"
                >
                    + Add row
                </button>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="emit('close')">Cancel</button>
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="saving"
                        @click="submit"
                    >
                        {{ saving ? 'Saving…' : inline ? `Save questions (${filledRows.length})` : `Add all to paper (${filledRows.length})` }}
                    </button>
                </div>
            </div>
        </fieldset>
    </component>
</template>
