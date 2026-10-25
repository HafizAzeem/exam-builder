<script setup>
import Modal from '@/Components/Modal.vue';
import axios from 'axios';
import DiagramAttachment from '@/Components/Diagrams/DiagramAttachment.vue';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
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

const defaultChapterId = () => Number(props.selectedChapterIds?.[0] || props.chapters?.[0]?.id || '') || '';

const emptyRow = (from = null) => ({
    key: rowSeq++,
    type: from?.type || 'short',
    source: from?.source || 'additional',
    board_name: from?.source === 'past_paper' ? (from.board_name || '') : '',
    chapter_id: from?.chapter_id || defaultChapterId(),
    includeUrdu: !!from?.includeUrdu,
    text_en: '',
    text_ur: '',
    correct_answer: '',
    mcq: emptyMcq(),
    diagram: null,
});

const draft = reactive({
    rows: [emptyRow(), emptyRow(), emptyRow()],
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
    `own-questions:exam:${props.gradeId || 'g'}:${props.subjectId || 's'}`,
);

const rowFromDraft = (row, parsed = {}) => ({
    key: rowSeq++,
    type: row.type || parsed.type || 'short',
    source: row.source || 'additional',
    board_name: row.board_name || '',
    chapter_id: Number(row.chapter_id || parsed.chapter_id) || defaultChapterId(),
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
    },
);

watch(draft, () => {
    if (props.show && ready.value) persistDraft();
}, { deep: true });

const addRow = () => {
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
        if (!row.diagram && !Object.keys(row.mcq.option_diagrams || {}).length && (seen.has(normalize(en)) || seen.has(normalize(ur)) || seen.has(key))) return;
        seen.add(key);
        if (en) seen.add(normalize(en));
        if (ur) seen.add(normalize(ur));
        if (row.type === 'mcq' && !row.mcq.option_a_en && !row.mcq.option_b_en && !Object.keys(row.mcq.option_diagrams || {}).length) return;
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
        draft.rows = [emptyRow(), emptyRow(), emptyRow()];
        emit('close');
    } catch (e) {
        error.value = e.message || 'Could not save questions.';
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Modal :show="show" max-width="6xl" @close="emit('close')">
        <div class="flex max-h-[90vh] flex-col" data-own-questions>
            <div class="border-b px-6 py-4">
                <h3 class="text-lg font-semibold text-slate-900">Add my questions</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Choose a type, source and chapter for each question. Add rows, then submit once.
                </p>
                <p v-if="error" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    {{ error }}
                </p>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto px-6 py-4">
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
                    <div class="mb-3 grid gap-3 sm:grid-cols-2" :class="row.source === 'past_paper' ? 'lg:grid-cols-7' : 'lg:grid-cols-6'">
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
                        <label class="text-sm" :class="row.source === 'past_paper' ? 'lg:col-span-1' : 'lg:col-span-2'">
                            <span class="font-medium text-slate-700">Question source</span>
                            <select v-model="row.source" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option value="additional">Additional</option>
                                <option value="exercise">Exercise</option>
                                <option value="past_paper">Past Paper</option>
                            </select>
                        </label>
                        <label class="text-sm lg:col-span-2">
                            <span class="font-medium text-slate-700">Chapter</span>
                            <select v-model="row.chapter_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                                <option v-for="c in chapters" :key="c.id" :value="c.id">{{ c.number }}. {{ c.title_en }}</option>
                            </select>
                        </label>
                        <div v-if="row.source === 'past_paper'" class="min-w-0 text-sm lg:col-span-2">
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
                    <textarea
                        v-model="row.text_en"
                        rows="2"
                        class="w-full rounded-lg border-slate-300 text-sm"
                        :placeholder="`English question ${index + 1}`"
                    />
                    <textarea
                        v-if="row.includeUrdu"
                        v-model="row.text_ur"
                        rows="2"
                        class="mt-2 w-full rounded-lg border-slate-300 text-sm"
                        dir="rtl"
                        :placeholder="`اردو سوال ${index + 1}`"
                    />
                    <DiagramAttachment :diagram="row.diagram" editable @update="row.diagram=$event" />
                    <div v-if="row.type === 'mcq'" class="mt-2 grid gap-2 sm:grid-cols-2">
                        <input v-model="row.mcq.option_a_en" class="rounded-lg border-slate-300 text-sm" placeholder="A" />
                        <input v-model="row.mcq.option_b_en" class="rounded-lg border-slate-300 text-sm" placeholder="B" />
                        <input v-model="row.mcq.option_c_en" class="rounded-lg border-slate-300 text-sm" placeholder="C" />
                        <input v-model="row.mcq.option_d_en" class="rounded-lg border-slate-300 text-sm" placeholder="D" />
                        <div v-for="key in ['A','B','C','D']" :key="key" class="rounded border bg-white p-2">
                            <span class="text-xs font-medium">Option {{ key }} picture</span>
                            <DiagramAttachment :diagram="row.mcq.option_diagrams?.[key]" editable @update="row.mcq.option_diagrams={...row.mcq.option_diagrams,[key]:$event}" />
                        </div>
                        <select v-model="row.mcq.correct_option" class="rounded-lg border-slate-300 text-sm sm:col-span-2">
                            <option value="a">Correct: A</option>
                            <option value="b">Correct: B</option>
                            <option value="c">Correct: C</option>
                            <option value="d">Correct: D</option>
                        </select>
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

            <div class="flex flex-wrap items-center justify-between gap-2 border-t px-6 py-4">
                <button
                    type="button"
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
                        {{ saving ? 'Adding…' : `Add all to paper (${filledRows.length})` }}
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
