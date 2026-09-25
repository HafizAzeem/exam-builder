<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { examYearOptions } from '@/utils/years';

const props = defineProps({
    grades: { type: Array, required: true },
    subjects: { type: Array, required: true },
    boards: { type: Array, default: () => [] },
});

const yearOptions = examYearOptions();

const form = useForm({
    grade_id: '',
    subject_id: '',
    book_type: 'additional_questions',
    board: props.boards?.[0]?.name ?? '',
    year: '',
    session: '',
    language: 'english',
    raw_text: '',
});

const isPastPaper = computed(() => form.book_type === 'past_paper');

const filteredSubjects = computed(() =>
    props.subjects.filter((s) => !form.grade_id || String(s.grade_id) === String(form.grade_id))
);

const charCount = computed(() => form.raw_text.length);

watch(() => form.book_type, (type) => {
    if (type !== 'past_paper') {
        form.year = '';
        form.session = '';
    }
});

const submit = () => {
    form.post(route('super-admin.ai-import.paste.store'));
};
</script>

<template>
    <Head title="Paste Text for AI Import" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Paste Questions for AI</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Paste any raw question text — messy OCR, lesson lists, mixed types. AI classifies, then you review before insert.
                    </p>
                </div>
                <Link :href="route('super-admin.ai-import.create')" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                    Or upload a file
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <form class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Grade" />
                            <select v-model="form.grade_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" @change="form.subject_id = ''">
                                <option value="">Select grade</option>
                                <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.label_en }}</option>
                            </select>
                            <InputError :message="form.errors.grade_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Subject" />
                            <select v-model="form.subject_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select subject</option>
                                <option v-for="s in filteredSubjects" :key="s.id" :value="s.id">{{ s.name_en }}</option>
                            </select>
                            <InputError :message="form.errors.subject_id" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Book Type / Source" />
                            <select v-model="form.book_type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="text_book">Text Book (exercise)</option>
                                <option value="additional_questions">Additional Questions</option>
                                <option value="past_paper">Past Paper</option>
                            </select>
                            <InputError :message="form.errors.book_type" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Language" />
                            <select v-model="form.language" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="english">English</option>
                                <option value="urdu">Urdu</option>
                                <option value="bilingual">Bilingual</option>
                            </select>
                            <InputError :message="form.errors.language" class="mt-1" />
                        </div>
                    </div>

                    <div v-if="isPastPaper" class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel value="Board" />
                            <select v-model="form.board" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select board</option>
                                <option v-for="b in boards" :key="b.id" :value="b.name">{{ b.name }}</option>
                            </select>
                            <InputError :message="form.errors.board" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Year (optional)" />
                            <select v-model.number="form.year" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">—</option>
                                <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                            </select>
                            <InputError :message="form.errors.year" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Session" />
                            <select v-model="form.session" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">—</option>
                                <option value="morning">Morning</option>
                                <option value="evening">Evening</option>
                            </select>
                            <InputError :message="form.errors.session" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <InputLabel value="Raw question text" />
                            <span class="text-xs text-gray-400">{{ charCount.toLocaleString() }} chars</span>
                        </div>
                        <textarea
                            v-model="form.raw_text"
                            rows="18"
                            class="w-full rounded-md border-gray-300 font-mono text-sm shadow-sm"
                            placeholder="Paste anything: lesson lists, OCR dumps, MCQs, short/long questions mixed together…"
                        />
                        <p class="mt-2 text-xs text-gray-500">
                            AI will detect types (MCQ, short, long, fill, true/false), chapters, and answers where present.
                            Nothing is inserted until you review and approve.
                        </p>
                        <InputError :message="form.errors.raw_text" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link
                            :href="route('super-admin.ai-import.dashboard')"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton :disabled="form.processing || !form.raw_text.trim()">
                            {{ form.processing ? 'Submitting…' : 'Submit to AI' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
