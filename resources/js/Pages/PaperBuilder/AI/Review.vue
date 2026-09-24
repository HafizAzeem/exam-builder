<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    aiImport: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    chapters: { type: Array, default: () => [] },
    composeQuery: { type: Object, required: true },
    paperLanguage: { type: String, default: 'english' },
});

const localQuestions = ref([...props.questions]);

const canSelect = (q) => Boolean(q.chapter_id) && (!q.is_duplicate || q.duplicate_of_question_id);

const selectable = computed(() =>
    localQuestions.value.filter((q) => canSelect(q))
);

const selected = ref(
    Object.fromEntries(
        selectable.value
            .filter((q) => !q.is_duplicate)
            .map((q) => [q.id, true])
    )
);

const selectedIds = computed(() =>
    Object.entries(selected.value)
        .filter(([, on]) => on)
        .map(([id]) => Number(id))
);

const selectedCount = computed(() => selectedIds.value.length);

const form = useForm({
    staging_ids: [],
});

const showWrite = ref(false);
const showSearch = ref(false);
const searchQ = ref('');
const searchType = ref('');
const searchChapterId = ref('');
const searchResults = ref([]);
const searchSelected = ref({});
const searching = ref(false);
const savingManual = ref(false);
const addingBank = ref(false);
const flashError = ref('');

const manual = reactive({
    type: 'mcq',
    chapter_id: props.chapters[0]?.id ?? '',
    text_en: '',
    text_ur: '',
    estimated_marks: 1,
    mcq_options: {
        option_a_en: '',
        option_b_en: '',
        option_c_en: '',
        option_d_en: '',
        option_a_ur: '',
        option_b_ur: '',
        option_c_ur: '',
        option_d_ur: '',
        correct_option: 'a',
    },
});

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const selectAll = () => {
    selectable.value.forEach((q) => {
        selected.value[q.id] = true;
    });
};

const deselectAll = () => {
    selectable.value.forEach((q) => {
        selected.value[q.id] = false;
    });
};

const removeRow = (id) => {
    selected.value[id] = false;
    // Paper-only: drop from this review checklist (does not delete bank questions).
    localQuestions.value = localQuestions.value.filter((q) => q.id !== id);
};

const typeLabel = (type) => ({
    mcq: 'MCQ',
    short: 'Short',
    long: 'Long',
    fill: 'Fill',
    truefalse: 'T/F',
}[type] || type);

const questionText = (q) => q.text_en || q.text_ur || '(empty)';

const chapterTitle = (chapterId) => {
    const c = props.chapters.find((ch) => Number(ch.id) === Number(chapterId));
    return c ? `${c.number}. ${c.title_en}` : '';
};

const mergeQuestions = (rows) => {
    const existing = new Set(localQuestions.value.map((q) => q.id));
    rows.forEach((q) => {
        if (!existing.has(q.id)) {
            localQuestions.value.push(q);
            selected.value[q.id] = true;
        }
    });
};

const runSearch = async () => {
    searching.value = true;
    flashError.value = '';
    try {
        const url = new URL(route('builder.ai.search-bank', props.aiImport.id), window.location.origin);
        if (searchQ.value) url.searchParams.set('search', searchQ.value);
        if (searchType.value) url.searchParams.set('type', searchType.value);
        if (searchChapterId.value) url.searchParams.set('chapter_id', String(searchChapterId.value));
        const res = await fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const json = await res.json();
        const data = Array.isArray(json?.data) ? json.data : (Array.isArray(json) ? json : []);
        searchResults.value = data;
        searchSelected.value = {};
    } catch {
        flashError.value = 'Search failed. Try again.';
    } finally {
        searching.value = false;
    }
};

watch(showSearch, (open) => {
    if (!open) return;
    searchQ.value = '';
    searchType.value = '';
    searchChapterId.value = '';
    runSearch();
});

watch([searchType, searchChapterId], () => {
    if (showSearch.value) {
        runSearch();
    }
});

const addSelectedFromBank = async () => {
    const ids = Object.entries(searchSelected.value).filter(([, v]) => v).map(([id]) => Number(id));
    if (!ids.length) return;
    addingBank.value = true;
    flashError.value = '';
    try {
        const res = await fetch(route('builder.ai.bank-questions', props.aiImport.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ question_ids: ids }),
        });
        if (!res.ok) throw new Error('failed');
        const json = await res.json();
        mergeQuestions(json.questions || []);
        showSearch.value = false;
    } catch {
        flashError.value = 'Could not add bank questions.';
    } finally {
        addingBank.value = false;
    }
};

const saveManual = async () => {
    savingManual.value = true;
    flashError.value = '';
    try {
        const payload = {
            type: manual.type,
            chapter_id: Number(manual.chapter_id),
            text_en: manual.text_en || null,
            text_ur: manual.text_ur || null,
            estimated_marks: Number(manual.estimated_marks) || 1,
            mcq_options: manual.type === 'mcq' ? { ...manual.mcq_options } : null,
        };
        const res = await fetch(route('builder.ai.manual-question', props.aiImport.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || 'failed');
        }
        const json = await res.json();
        if (json.question) {
            mergeQuestions([json.question]);
        }
        showWrite.value = false;
        manual.text_en = '';
        manual.text_ur = '';
    } catch (e) {
        flashError.value = e.message || 'Could not save question.';
    } finally {
        savingManual.value = false;
    }
};

const submit = () => {
    form.staging_ids = selectedIds.value;
    form.post(route('builder.ai.add-to-paper', props.aiImport.id));
};
</script>

<template>
    <Head title="Review AI questions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Review questions</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Uncheck or remove anything you don’t want. You can also search the bank or write your own.
                    </p>
                </div>
                <Link
                    :href="route('builder.create', composeQuery)"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    ← Back to paper
                </Link>
            </div>
        </template>

        <div class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50" @click="selectAll">
                        Select all
                    </button>
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 font-medium text-slate-700 hover:bg-slate-50" @click="deselectAll">
                        Deselect
                    </button>
                    <button type="button" class="rounded-lg border border-teal-200 bg-teal-50 px-3 py-1.5 font-medium text-teal-800 hover:bg-teal-100" @click="showSearch = true">
                        Search bank
                    </button>
                    <button type="button" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 font-medium text-indigo-800 hover:bg-indigo-100" @click="showWrite = true">
                        Write question
                    </button>
                    <span class="text-slate-500">{{ selectedCount }} selected</span>
                </div>
                <PrimaryButton type="button" :disabled="form.processing || selectedCount < 1" @click="submit">
                    {{ form.processing ? 'Adding…' : `Add ${selectedCount} to paper` }}
                </PrimaryButton>
            </div>
        </div>

        <div class="py-6">
            <div class="mx-auto max-w-4xl space-y-3 px-4 sm:px-6">
                <p v-if="flashError" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ flashError }}</p>

                <p v-if="!localQuestions.length" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-slate-600">
                    No questions yet. Search the bank or write your own.
                </p>

                <div
                    v-for="q in localQuestions"
                    :key="q.id"
                    class="rounded-xl border bg-white p-4 shadow-sm"
                    :class="q.is_duplicate ? 'border-amber-200 opacity-60' : 'border-slate-200'"
                >
                    <div class="flex items-start gap-3">
                        <input
                            v-if="canSelect(q)"
                            v-model="selected[q.id]"
                            type="checkbox"
                            class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="mb-1 flex flex-wrap items-center gap-2 text-xs font-medium">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-slate-700">{{ typeLabel(q.type) }}</span>
                                <span v-if="chapterTitle(q.chapter_id)" class="text-slate-500">{{ chapterTitle(q.chapter_id) }}</span>
                                <span v-if="q.is_duplicate" class="rounded bg-amber-100 px-2 py-0.5 text-amber-800">Already in bank</span>
                            </div>
                            <p class="whitespace-pre-wrap text-sm text-slate-800" :dir="q.text_ur && !q.text_en ? 'rtl' : undefined">
                                {{ questionText(q) }}
                            </p>
                            <p v-if="q.text_en && q.text_ur" class="mt-1 whitespace-pre-wrap text-sm text-slate-600" dir="rtl">
                                {{ q.text_ur }}
                            </p>
                            <ul v-if="q.type === 'mcq' && q.mcq_options" class="mt-2 grid gap-1 text-sm text-slate-600 sm:grid-cols-2">
                                <li>A. {{ q.mcq_options.option_a_en || q.mcq_options.option_a_ur }}</li>
                                <li>B. {{ q.mcq_options.option_b_en || q.mcq_options.option_b_ur }}</li>
                                <li>C. {{ q.mcq_options.option_c_en || q.mcq_options.option_c_ur }}</li>
                                <li>D. {{ q.mcq_options.option_d_en || q.mcq_options.option_d_ur }}</li>
                            </ul>
                            <p v-if="q.is_duplicate" class="mt-2 text-xs text-amber-700">
                                Check to reuse the bank copy on this paper, or Remove to skip it.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                            @click="removeRow(q.id)"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showSearch" max-width="2xl" @close="showSearch = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Search existing questions</h3>
                <div class="mt-4 flex flex-wrap gap-2">
                    <input v-model="searchQ" type="search" placeholder="Filter by text…" class="min-w-[12rem] flex-1 rounded-lg border-slate-300 text-sm" @keyup.enter="runSearch" />
                    <select v-model="searchChapterId" class="rounded-lg border-slate-300 text-sm">
                        <option value="">All chapters</option>
                        <option v-for="c in chapters" :key="c.id" :value="c.id">{{ c.number }}. {{ c.title_en }}</option>
                    </select>
                    <select v-model="searchType" class="rounded-lg border-slate-300 text-sm">
                        <option value="">All types</option>
                        <option value="mcq">MCQ</option>
                        <option value="short">Short</option>
                        <option value="long">Long</option>
                        <option value="fill">Fill</option>
                        <option value="truefalse">T/F</option>
                    </select>
                    <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" :disabled="searching" @click="runSearch">
                        {{ searching ? 'Loading…' : 'Refresh' }}
                    </button>
                </div>
                <div class="mt-4 max-h-80 space-y-2 overflow-y-auto">
                    <label v-for="q in searchResults" :key="q.id" class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50">
                        <input v-model="searchSelected[q.id]" type="checkbox" class="mt-1 rounded text-teal-600" />
                        <span class="min-w-0 flex-1 text-sm text-slate-800">
                            <span class="mr-2 rounded bg-slate-100 px-1.5 text-xs">{{ typeLabel(q.type) }}</span>
                            <span v-if="chapterTitle(q.chapter_id)" class="mr-2 text-xs text-slate-500">{{ chapterTitle(q.chapter_id) }}</span>
                            {{ q.text_en || q.text_ur }}
                        </span>
                    </label>
                    <p v-if="searching" class="py-6 text-center text-sm text-slate-500">Loading questions…</p>
                    <p v-else-if="!searchResults.length" class="py-6 text-center text-sm text-slate-500">No questions found for this filter.</p>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showSearch = false">Cancel</button>
                    <PrimaryButton type="button" :disabled="addingBank" @click="addSelectedFromBank">
                        {{ addingBank ? 'Adding…' : 'Add selected' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showWrite" max-width="2xl" @close="showWrite = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Write your own question</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="text-sm">
                        <span class="font-medium text-slate-700">Type</span>
                        <select v-model="manual.type" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="mcq">MCQ</option>
                            <option value="short">Short</option>
                            <option value="long">Long</option>
                            <option value="fill">Fill</option>
                            <option value="truefalse">True / False</option>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="font-medium text-slate-700">Chapter</span>
                        <select v-model="manual.chapter_id" class="mt-1 w-full rounded-lg border-slate-300">
                            <option v-for="c in chapters" :key="c.id" :value="c.id">{{ c.number }}. {{ c.title_en }}</option>
                        </select>
                    </label>
                    <label class="text-sm sm:col-span-2">
                        <span class="font-medium text-slate-700">English text</span>
                        <textarea v-model="manual.text_en" rows="3" class="mt-1 w-full rounded-lg border-slate-300" />
                    </label>
                    <label class="text-sm sm:col-span-2">
                        <span class="font-medium text-slate-700">Urdu text</span>
                        <textarea v-model="manual.text_ur" rows="3" class="mt-1 w-full rounded-lg border-slate-300" dir="rtl" />
                    </label>
                    <template v-if="manual.type === 'mcq'">
                        <label v-for="letter in ['a','b','c','d']" :key="letter" class="text-sm">
                            <span class="font-medium text-slate-700">Option {{ letter.toUpperCase() }} (EN)</span>
                            <input v-model="manual.mcq_options[`option_${letter}_en`]" class="mt-1 w-full rounded-lg border-slate-300" />
                        </label>
                        <label class="text-sm sm:col-span-2">
                            <span class="font-medium text-slate-700">Correct option</span>
                            <select v-model="manual.mcq_options.correct_option" class="mt-1 w-full rounded-lg border-slate-300">
                                <option value="a">A</option>
                                <option value="b">B</option>
                                <option value="c">C</option>
                                <option value="d">D</option>
                            </select>
                        </label>
                    </template>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="showWrite = false">Cancel</button>
                    <PrimaryButton type="button" :disabled="savingManual" @click="saveManual">
                        {{ savingManual ? 'Saving…' : 'Add question' }}
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
