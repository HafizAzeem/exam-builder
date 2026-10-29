<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import CurriculumDropdowns from '@/Components/CurriculumDropdowns.vue';
import { selectionForChapter } from '@/Support/curriculumSelection.mjs';
import DiagramAttachment from '@/Components/Diagrams/DiagramAttachment.vue';
import OwnQuestionsModal from '@/Components/PaperBuilder/OwnQuestionsModal.vue';

const props = defineProps({ questions: Object, filters: Object, chapters: Array, boards: Array, schoolWide: Boolean, entryChapters: Array, initialMode: String, draftScope: String });
const title = computed(() => 'School Question Bank');
const initialSelection = selectionForChapter(props.chapters, props.filters.chapter_id);
const filters = reactive({
    search: props.filters.search ?? '', type: props.filters.type ?? '',
    grade_id: props.filters.grade_id ?? initialSelection.grade_id,
    subject_id: props.filters.subject_id ?? initialSelection.subject_id,
    chapter_id: props.filters.chapter_id ?? '', scope: props.filters.scope || 'all',
});
const types = { short: 'Short answer', long: 'Long answer', mcq: 'Multiple choice', fill: 'Fill in the blank', truefalse: 'True / false' };
const selected = ref(null);
const deleting = ref(null);
const busy = ref(false);
const notice = ref('');
const errors = ref({});
const draft = reactive({});
const options = ['a', 'b', 'c', 'd'];
const chapterLabel = chapter => `${chapter.subject?.grade?.label_en || `Class ${chapter.subject?.grade?.number || ''}`} · ${chapter.subject?.name_en || ''} · ${chapter.title_en || chapter.title_ur || `Chapter ${chapter.number}`}`;
function browse(scope) {
    selected.value = null;
    filters.scope = scope;
    router.get(route('questions.index'), filters, { preserveScroll: false });
}
function added() {
    router.get(route('questions.index'), { scope: 'mine' });
}
function search() {
    router.get(route('questions.index'), filters, { preserveState: true, preserveScroll: true, replace: true });
}
function edit(question) {
    errors.value = {};
    Object.assign(draft, {
        ...selectionForChapter(props.entryChapters, question.chapter_id), type: question.type, source: question.source,
        text_en: question.text_en || '', text_ur: question.text_ur || '', includeUrdu: !!question.text_ur || (question.parts || []).some(part => !!part.text_ur),
        correct_answer: question.correct_answer || '', board_name: question.past_paper_tag?.board_name || '',
        diagram: question.diagram ? JSON.parse(JSON.stringify(question.diagram)) : null,
        mcq: {
            option_a_en: question.mcq_options?.option_a_en || '', option_b_en: question.mcq_options?.option_b_en || '',
            option_c_en: question.mcq_options?.option_c_en || '', option_d_en: question.mcq_options?.option_d_en || '',
            correct_option: question.mcq_options?.correct_option || 'a',
            option_diagrams: { ...JSON.parse(JSON.stringify(question.mcq_options?.option_diagrams || {})) },
        },
        parts: (question.parts || []).map(part => ({ id: part.id, text_en: part.text_en || '', text_ur: part.text_ur || '' })),
    });
    selected.value = question;
}
function showError(error) {
    errors.value = error.response?.data?.errors || { general: [error.response?.data?.message || 'Could not save this change. Please try again.'] };
}
async function save() {
    if (busy.value) return;
    busy.value = true;
    errors.value = {};
    try {
        await axios.patch(route('questions.update', selected.value.id), { questions: [draft] });
        selected.value = null;
        notice.value = 'Question updated. Existing saved papers are unchanged.';
        router.get(route('questions.index'), filters, { preserveState: true });
    } catch (error) { showError(error); }
    finally { busy.value = false; }
}
async function remove() {
    if (busy.value) return;
    busy.value = true;
    errors.value = {};
    try {
        await axios.delete(route('questions.destroy', deleting.value.id), { data: { confirmed: true } });
        deleting.value = null;
        notice.value = 'Question permanently deleted. Existing saved papers are unchanged.';
        router.get(route('questions.index'), filters, { preserveState: true, preserveScroll: true });
    } catch (error) { showError(error); }
    finally { busy.value = false; }
}
</script>

<template>
    <Head :title="title" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl px-4 py-5 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><h1 class="text-3xl font-bold text-slate-950">{{ title }}</h1><p class="mt-2 max-w-2xl leading-7 text-slate-600">Browse all questions added within your school, or choose My questions to find your own. {{ schoolWide ? 'You can manage every school question.' : 'You can edit and delete only your own additions.' }}</p></div>
                <Link :href="route('questions.create')" class="rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white">+ Add questions</Link>
            </div>
            <nav class="mt-4 flex flex-wrap gap-2 border-b border-slate-200 pb-3" aria-label="Question bank sections">
                <button @click="browse('all')" class="rounded-xl px-5 py-3 text-sm font-bold" :class="initialMode !== 'add' && !selected && filters.scope === 'all' ? 'bg-blue-600 text-white' : 'border bg-white text-slate-700'">All school questions</button>
                <button @click="browse('mine')" class="rounded-xl px-5 py-3 text-sm font-bold" :class="initialMode !== 'add' && !selected && filters.scope === 'mine' ? 'bg-blue-600 text-white' : 'border bg-white text-slate-700'">My questions</button>
                <Link :href="route('questions.create')" class="rounded-xl px-5 py-3 text-sm font-bold" :class="initialMode === 'add' ? 'bg-blue-600 text-white' : 'border bg-white text-slate-700'">Add questions</Link>
            </nav>
            <div v-if="initialMode === 'add' && !selected" class="mt-6">
                <p v-if="!entryChapters.length" class="mb-4 rounded-xl bg-amber-50 p-4 text-amber-800">No permitted active chapters are available. Ask your school administrator to check curriculum access.</p>
                <OwnQuestionsModal v-else show inline :chapters="entryChapters" :draft-scope="draftScope" @add="added" @close="browse('mine')" />
            </div>
            <section v-if="initialMode !== 'add' && !selected">
            <p v-if="notice" role="status" class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ notice }}</p>
            <form @submit.prevent="search" class="my-5 grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-12">
                <label class="min-w-0 text-sm font-medium text-slate-700 lg:col-span-3">Search<input v-model="filters.search" aria-label="Search questions" placeholder="English or Urdu text" maxlength="200" class="mt-1 h-10 w-full rounded-lg border-slate-300 py-2 text-sm" /></label>
                <label class="min-w-0 text-sm font-medium text-slate-700 lg:col-span-2">Question type<select v-searchable v-model="filters.type" aria-label="Question type" class="mt-1 h-10 w-full rounded-lg border-slate-300 py-2 text-sm"><option value="">All types</option><option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option></select></label>
                <CurriculumDropdowns :model-value="filters" :chapters="chapters" class="sm:col-span-2 lg:col-span-6" @update:model-value="Object.assign(filters, $event)" />
                <button aria-label="Search questions" class="h-10 rounded-lg border border-blue-200 bg-blue-50 px-3 text-sm font-semibold text-blue-700">Search</button>
            </form>
            <p class="mb-4 text-sm text-slate-500">{{ questions.total }} question(s)</p>
            <div v-if="!questions.data.length" class="rounded-2xl border bg-white p-10 text-center"><h2 class="font-bold text-slate-800">No questions found</h2><p class="mt-2 text-slate-500">Try different filters, or open Add questions to enter your first rows.</p></div>
            <div class="space-y-2">
                <article v-for="question in questions.data" :key="question.id" class="rounded-lg border border-slate-200 border-l-4 border-l-blue-500 bg-white px-3 py-3 transition hover:border-blue-300 sm:px-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p v-if="question.text_en" class="whitespace-pre-wrap break-words text-sm font-semibold leading-6 text-slate-950">{{ question.text_en }}</p>
                            <p v-if="question.text_ur" dir="rtl" lang="ur" class="whitespace-pre-wrap break-words text-right text-sm font-semibold leading-7 text-slate-950">{{ question.text_ur }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs leading-5 text-slate-500">
                                <span class="shrink-0 rounded bg-blue-50 px-2 font-semibold text-blue-700">{{ types[question.type] || question.type }}</span>
                                <span>{{ question.chapter ? chapterLabel(question.chapter) : 'Chapter unavailable' }} · {{ question.source.replaceAll('_', ' ') }}<span v-if="question.submitter"> · {{ question.submitter.name }}</span></span>
                            </div>
                        </div>
                        <div v-if="question.can_manage" class="flex shrink-0 gap-1.5">
                            <button :disabled="busy" @click="edit(question)" class="rounded-md bg-blue-50 px-2.5 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100">Edit</button>
                            <button :disabled="busy" @click="deleting = question; errors = {}" class="rounded-md bg-red-50 px-2.5 py-1.5 text-xs font-bold text-red-700 hover:bg-red-100">Delete</button>
                        </div>
                        <span v-else class="shrink-0 rounded bg-slate-100 px-2 py-1 text-xs text-slate-500">View only</span>
                    </div>
                    <DiagramAttachment :diagram="question.diagram" :image-path="question.image_path" />
                    <div v-if="question.mcq_options" class="mt-2 grid gap-1.5 sm:grid-cols-2 lg:grid-cols-4"><div v-for="option in options" :key="option" class="rounded bg-slate-50 px-2 py-1.5 text-xs"><span class="font-bold uppercase">{{ option }}.</span> {{ question.mcq_options[`option_${option}_en`] }}<span v-if="question.mcq_options.correct_option === option" class="ms-2 text-emerald-700">✓</span><DiagramAttachment :diagram="question.mcq_options.option_diagrams?.[option.toUpperCase()]" /></div></div>
                    <p v-if="question.type === 'truefalse' && question.correct_answer" class="mt-1 text-xs text-emerald-700">Answer: {{ question.correct_answer }}</p>
                    <p v-if="question.parts?.length" class="mt-1 text-xs text-slate-500">{{ question.parts.length }} question parts</p>
                </article>
            </div>
            <nav v-if="questions.last_page > 1" class="mt-6 flex flex-wrap gap-2" aria-label="Question pages"><template v-for="(link, index) in questions.links" :key="index"><Link v-if="link.url" :href="link.url" class="rounded-lg border px-3 py-2 text-sm" :class="link.active ? 'bg-blue-600 text-white' : 'bg-white text-slate-700'" v-html="link.label" /></template></nav>
            </section>
        <section v-if="selected" class="mt-6 rounded-2xl border border-slate-200 bg-white">
            <form v-if="selected" class="p-4 sm:p-5" @submit.prevent="save">
                <h2 class="text-xl font-bold">Edit question</h2><p class="mt-2 text-sm text-slate-500">Existing saved papers keep their original content.</p>
                <div v-if="Object.keys(errors).length" role="alert" class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700"><p v-for="(messages, key) in errors" :key="key">{{ messages.join(' ') }}</p></div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <label class="text-sm font-semibold">Question type<select v-searchable v-model="draft.type" class="mt-1 w-full rounded-lg border-slate-300 py-2 text-sm"><option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option></select></label>
                    <label class="text-sm font-semibold">Source<select v-searchable v-model="draft.source" class="mt-1 w-full rounded-lg border-slate-300 py-2 text-sm"><option value="exercise">Exercise</option><option value="additional">Additional</option><option value="past_paper">Past paper</option></select></label>
                    <CurriculumDropdowns :model-value="draft" :chapters="entryChapters" class="sm:col-span-2 lg:col-span-3" @update:model-value="Object.assign(draft, $event)" />
                    <label v-if="draft.source === 'past_paper'" class="text-sm font-semibold sm:col-span-2">Board<select v-searchable v-model="draft.board_name" required class="mt-1 w-full rounded-lg border-slate-300 py-2 text-sm"><option value="" disabled>Select board</option><option v-for="board in boards" :key="board" :value="board">{{ board }}</option></select></label>
                </div>
                <label class="mt-3 inline-flex items-center gap-2 text-sm font-medium"><input v-model="draft.includeUrdu" type="checkbox" class="rounded border-slate-300 text-blue-600" /> Add Urdu text</label>
                <div class="mt-3 grid gap-3" :class="draft.includeUrdu ? 'md:grid-cols-2' : ''">
                    <label class="text-sm font-semibold">English question<textarea v-model="draft.text_en" rows="2" maxlength="5000" class="mt-1 w-full resize-y rounded-lg border-slate-300 py-2 text-sm leading-6" /></label>
                    <label v-if="draft.includeUrdu" class="text-sm font-semibold">Urdu question<textarea v-model="draft.text_ur" rows="2" maxlength="5000" lang="ur" dir="rtl" class="mt-1 w-full resize-y rounded-lg border-slate-300 py-2 text-sm leading-6" /></label>
                </div>
                <fieldset v-if="draft.type === 'mcq'" class="mt-3">
                    <legend class="text-sm font-bold">MCQ options</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div v-for="option in options" :key="option" class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 p-2">
                            <label :for="`option-${option}`" class="text-xs font-semibold">Option {{ option.toUpperCase() }}</label>
                            <input :id="`option-${option}`" v-model="draft.mcq[`option_${option}_en`]" maxlength="1000" class="mt-1 w-full rounded-lg border-slate-300 py-1.5 text-sm" />
                        </div>
                    </div>
                    <label class="mt-3 inline-flex items-center gap-3 text-sm font-semibold">Correct option<select v-searchable v-model="draft.mcq.correct_option" class="rounded-lg border-slate-300 py-1.5 text-sm"><option v-for="option in options" :key="option" :value="option">{{ option.toUpperCase() }}</option></select></label>
                </fieldset>
                <label v-if="draft.type === 'truefalse'" class="mt-5 block text-sm font-semibold">Correct answer<select v-searchable v-model="draft.correct_answer" class="mt-1 w-full rounded-lg border-slate-300 py-2 text-sm"><option value="">Not specified</option><option value="true">True</option><option value="false">False</option></select></label>
                <fieldset v-for="(part, index) in draft.parts" :key="part.id" class="mt-3 grid gap-3 rounded-xl border p-3" :class="draft.includeUrdu ? 'sm:grid-cols-2' : ''"><legend class="font-bold">Part {{ index + 1 }}</legend><label class="block text-sm font-semibold">English<textarea v-model="part.text_en" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border-slate-300 py-2 text-sm" /></label><label v-if="draft.includeUrdu" class="block text-sm font-semibold">Urdu<textarea v-model="part.text_ur" rows="2" maxlength="5000" lang="ur" dir="rtl" class="mt-1 w-full rounded-lg border-slate-300 py-2 text-sm" /></label></fieldset>
                <div class="sticky bottom-0 z-10 -mx-4 mt-4 flex justify-end gap-2 border-t border-slate-100 bg-white px-4 py-3 sm:-mx-5 sm:px-5"><button type="button" :disabled="busy" @click="selected = null" class="rounded-xl border px-5 py-3">Cancel</button><button :disabled="busy" class="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white disabled:opacity-50">{{ busy ? 'Saving…' : 'Save changes' }}</button></div>
            </form>
        </section>
        </div>
        <Modal :show="!!deleting" :closeable="!busy" max-width="lg" @close="deleting = null">
            <section class="p-6"><h2 class="text-xl font-bold text-red-700">Permanently delete this question?</h2><p class="mt-3 leading-7 text-slate-600">This removes the question and its parts from the question bank. It cannot be restored. Existing saved papers keep their original content.</p><p class="mt-4 max-h-32 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-4 text-sm">{{ deleting?.text_en || deleting?.text_ur }}</p><div v-if="Object.keys(errors).length" role="alert" class="mt-3 text-sm text-red-700"><p v-for="(messages, key) in errors" :key="key">{{ messages.join(' ') }}</p></div><div class="mt-6 flex justify-end gap-3"><button :disabled="busy" @click="deleting = null" class="rounded-xl border px-4 py-3">Cancel</button><button :disabled="busy" @click="remove" class="rounded-xl bg-red-600 px-4 py-3 font-bold text-white disabled:opacity-50">{{ busy ? 'Deleting…' : 'Delete permanently' }}</button></div></section>
        </Modal>
    </AuthenticatedLayout>
</template>
