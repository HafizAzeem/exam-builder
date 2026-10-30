<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps({
    grade: { type: Object, required: true }, subject: { type: Object, required: true },
    chapters: { type: Array, default: () => [] }, selectedChapterIds: { type: Array, default: () => [] },
    composeQuery: { type: Object, required: true }, boards: { type: Array, default: () => [] },
    assistantUsage: { type: Object, default: () => ({}) }, defaultBoard: { type: String, default: '' },
    curriculumYear: { type: [Number, String], default: null }, contextConfirmation: { type: Object, default: null },
});
const action = ref('format');
const fileInput = ref(null);
const fileError = ref('');
const showContext = ref(false);
const form = useForm({
    grade_id: props.grade.id, subject_id: props.subject.id, chapter_ids: [...props.selectedChapterIds],
    book_type: props.composeQuery.book_type || 'additional_questions', board: props.defaultBoard || '',
    year: '', session: props.composeQuery.session || '', language: props.composeQuery.language || 'english',
    mode: 'format', request_key: crypto.randomUUID(), raw_text: '', file: null, question_count: 10, confirm_context: false,
});
const selectedChapters = computed(() => props.chapters.filter(c => form.chapter_ids.some(id => Number(id) === Number(c.id))));
const exhausted = computed(() => props.assistantUsage.remaining != null && props.assistantUsage.remaining <= 0);
const dailyExhausted = computed(() => props.assistantUsage.daily_limit != null && props.assistantUsage.daily_used >= props.assistantUsage.daily_limit);
const canSubmit = computed(() => !form.processing && !exhausted.value && !dailyExhausted.value && !fileError.value && (form.file || form.raw_text.trim()) && form.raw_text.length <= 20000 && form.question_count >= 1 && form.question_count <= 30 && form.chapter_ids.length);
function attach(event) {
    fileError.value = '';
    const file = event.target.files?.[0];
    form.file = null;
    if (!file) return;
    const pdf = file.type === 'application/pdf';
    if (!pdf && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) fileError.value = 'Choose a PDF, JPG, PNG or WebP image.';
    else if (file.size > (pdf ? 5 : 3) * 1024 * 1024) fileError.value = pdf ? 'PDFs must be 5 MB or smaller.' : 'Images must be 3 MB or smaller.';
    else form.file = file;
    if (fileError.value) event.target.value = '';
}
function removeFile() { form.file = null; fileError.value = ''; if (fileInput.value) fileInput.value.value = ''; }
function submit() {
    if (!canSubmit.value) return;
    form.mode = form.file ? 'upload' : action.value;
    form.post(route('builder.ai.paste.store'), { preserveScroll: true, forceFormData: true });
}
</script>
<template>
    <Head title="Question Assistant" />
    <AuthenticatedLayout>
        <template #header><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold text-slate-800">Question Assistant</h2><Link :href="route('builder.create', composeQuery)" class="text-sm font-medium text-slate-600 hover:text-slate-900">← Back to paper</Link></div></template>
        <main class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
            <div class="mb-7 text-center"><div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100 text-2xl text-emerald-800" aria-hidden="true">✦</div><h1 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">Prepare your next set of questions</h1><p class="mt-2 text-sm text-slate-500">Format your material or find questions online, then review what goes into your paper.</p></div>
            <form class="space-y-5" @submit.prevent="submit">
                <section class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Paper context</p><button type="button" class="text-sm font-medium text-emerald-700" :aria-expanded="showContext" aria-controls="assistant-context" @click="showContext = !showContext">{{ showContext ? 'Done' : 'Edit context' }}</button></div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs"><span v-for="chip in [grade.label_en, subject.name_en, form.board || 'Board not selected', `Syllabus ${curriculumYear || new Date().getFullYear()}${curriculumYear ? '' : ' (current year default)'}`, ...selectedChapters.map(c => `${c.number}. ${c.title_en}`)]" :key="chip" class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-slate-700">{{ chip }}</span></div>
                    <div v-if="showContext" id="assistant-context" class="mt-4 space-y-4 border-t pt-4">
                        <Link :href="route('builder.chapters', { grade: grade.id, subject: subject.id })" class="text-sm font-medium text-emerald-700 underline">Change class or subject</Link>
                        <fieldset><legend class="mb-2 text-sm font-medium text-slate-700">Chapters</legend><div class="grid gap-2 sm:grid-cols-2"><label v-for="chapter in chapters" :key="chapter.id" class="flex items-start gap-2 text-sm"><input v-model="form.chapter_ids" type="checkbox" :value="chapter.id" class="mt-1 rounded border-slate-300 text-emerald-600" />{{ chapter.number }}. {{ chapter.title_en }}</label></div></fieldset>
                        <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm">Board<select v-model="form.board" class="mt-1 w-full rounded-lg border-slate-300"><option value="">Select board</option><option v-if="defaultBoard && !boards.some(b => b.name === defaultBoard)" :value="defaultBoard">{{ defaultBoard }}</option><option v-for="board in boards" :key="board.id" :value="board.name">{{ board.name }}</option></select></label><label class="text-sm">Language<select v-model="form.language" class="mt-1 w-full rounded-lg border-slate-300"><option value="english">English</option><option value="urdu">Urdu</option><option value="both">Both</option></select></label><label class="text-sm">Source type<select v-model="form.book_type" class="mt-1 w-full rounded-lg border-slate-300"><option value="additional_questions">Additional questions</option><option value="text_book">Textbook exercises</option><option value="past_paper">Past paper</option></select></label><label class="text-sm">Past paper year (if known)<input v-model="form.year" type="number" min="1900" max="2100" class="mt-1 w-full rounded-lg border-slate-300" /></label><label class="text-sm">Session<select v-model="form.session" class="mt-1 w-full rounded-lg border-slate-300"><option value="">Any session</option><option value="morning">Morning</option><option value="evening">Evening</option></select></label></div>
                    </div>
                </section>
                <fieldset class="grid grid-cols-2 gap-2"><legend class="sr-only">Assistant action</legend><label v-for="mode in ['format', 'search']" :key="mode" class="cursor-pointer rounded-xl border p-3 text-sm font-medium" :class="action === mode ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-slate-200 bg-white text-slate-600'"><input v-model="action" type="radio" name="assistant-action" :value="mode" :disabled="!!form.file" class="mr-2 text-emerald-600" />{{ mode === 'format' ? 'Format my questions' : 'Find questions online' }}</label></fieldset>
                <div class="overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-sm focus-within:border-emerald-600 focus-within:ring-2 focus-within:ring-emerald-100">
                    <label for="assistant-text" class="sr-only">{{ action === 'search' ? 'Describe the questions to find' : 'Paste your questions' }}</label>
                    <textarea id="assistant-text" v-model="form.raw_text" rows="9" maxlength="20000" :placeholder="action === 'search' ? 'Find 10 short questions on photosynthesis from the selected chapters…' : 'Paste your questions, choices and parts here…'" class="block w-full resize-y border-0 bg-transparent p-5 text-sm leading-7 focus:ring-0" aria-describedby="assistant-hint assistant-character-count" />
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3"><div><label for="assistant-file" class="cursor-pointer rounded-lg px-2 py-1 text-sm font-medium text-slate-600 hover:bg-slate-100">＋ Attach file</label><input id="assistant-file" ref="fileInput" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="sr-only" @change="attach" /></div><span id="assistant-character-count" class="text-xs tabular-nums text-slate-400">{{ form.raw_text.length.toLocaleString() }} / 20,000</span></div>
                </div>
                <div v-if="form.file" class="flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm"><span class="min-w-0 truncate">{{ form.file.name }} · {{ (form.file.size / 1024 / 1024).toFixed(1) }} MB</span><button type="button" class="font-medium text-emerald-800" @click="removeFile">Remove</button></div>
                <p id="assistant-hint" class="text-xs leading-5 text-slate-500">{{ form.file ? 'The attachment will be read and formatted into questions.' : action === 'format' ? 'Preserves your questions and choices. Missing questions are not invented.' : 'Finds source material for your selected curriculum. Check sources and syllabus match in review.' }} Questions only, without solutions. One PDF up to 5 MB and 5 pages, or one image up to 3 MB.</p>
                <p v-if="fileError" role="alert" class="text-sm text-rose-700">{{ fileError }}</p>
                <div v-if="contextConfirmation" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm"><p>{{ contextConfirmation.message || 'Your instructions suggest a different curriculum context. Review the proposed change before continuing.' }}</p><pre v-if="contextConfirmation.description" class="mt-2 whitespace-pre-wrap font-sans">{{ contextConfirmation.description }}</pre><label class="mt-3 flex gap-2"><input v-model="form.confirm_context" type="checkbox" class="rounded text-emerald-600" />Confirm the detected context</label></div>
                <div v-if="Object.keys(form.errors).length" role="alert" class="space-y-1 rounded-xl bg-rose-50 p-4"><InputError v-for="(message, key) in form.errors" :key="key" :message="message" /></div>
                <div class="flex flex-wrap items-end justify-between gap-4"><div><label for="assistant-count" class="block text-xs font-medium text-slate-600">Maximum questions</label><input id="assistant-count" v-model.number="form.question_count" type="number" min="1" max="30" required class="mt-1 w-24 rounded-lg border-slate-300 text-sm" /></div><button :disabled="!canSubmit || (!!contextConfirmation && !form.confirm_context)" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40">{{ form.processing ? 'Submitting…' : 'Prepare questions →' }}</button></div>
                <p class="text-center text-xs text-slate-500" aria-live="polite"><template v-if="assistantUsage.remaining != null"><strong class="text-slate-700">{{ assistantUsage.remaining }}</strong> school requests remaining · Shared by your school.</template><template v-else>Request availability is checked before processing.</template> Failed requests are refunded.</p>
                <p v-if="exhausted || dailyExhausted" role="status" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ exhausted ? 'Your school has used its request budget. Ask your school administrator about access.' : 'Your school has reached its daily processing limit. Please try again after the daily reset.' }}</p>
            </form>
        </main>
    </AuthenticatedLayout>
</template>
