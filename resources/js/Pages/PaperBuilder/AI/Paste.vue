<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { examYearOptions } from '@/utils/years';

const props = defineProps({
    grade: { type: Object, required: true },
    subject: { type: Object, required: true },
    chapters: { type: Array, default: () => [] },
    selectedChapterIds: { type: Array, default: () => [] },
    composeQuery: { type: Object, required: true },
    boards: { type: Array, default: () => [] },
});

const yearOptions = examYearOptions();

const form = useForm({
    grade_id: props.grade.id,
    subject_id: props.subject.id,
    chapter_ids: [...props.selectedChapterIds],
    book_type: 'additional_questions',
    board: props.boards?.[0]?.name ?? '',
    year: '',
    session: '',
    language: 'english',
    raw_text: '',
});

const isPastPaper = computed(() => form.book_type === 'past_paper');
const chapterSummary = computed(() =>
    props.chapters.map((c) => `${c.number}. ${c.title_en}`).join(', ')
);

const submit = () => {
    form.post(route('builder.ai.paste.store'));
};
</script>

<template>
    <Head title="Paste text · AI paper" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Paste text</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ grade.label_en }} · {{ subject.name_en }} — paste notes or questions, then pick what goes on the paper.
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

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form class="space-y-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        <p><span class="font-medium text-slate-800">Chapters:</span> {{ chapterSummary }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Source type" />
                            <select v-model="form.book_type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="additional_questions">Notes / additional</option>
                                <option value="text_book">Textbook exercises</option>
                                <option value="past_paper">Past paper</option>
                            </select>
                            <InputError :message="form.errors.book_type" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Language" />
                            <select v-model="form.language" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="english">English</option>
                                <option value="urdu">Urdu</option>
                                <option value="both">Both</option>
                            </select>
                            <InputError :message="form.errors.language" class="mt-1" />
                        </div>
                    </div>

                    <div v-if="isPastPaper" class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel value="Board" />
                            <select v-model="form.board" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option v-for="b in boards" :key="b.id" :value="b.name">{{ b.name }}</option>
                            </select>
                            <InputError :message="form.errors.board" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Year" />
                            <select v-model.number="form.year" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">—</option>
                                <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                            </select>
                            <InputError :message="form.errors.year" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Session" />
                            <select v-model="form.session" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Any</option>
                                <option value="morning">Morning</option>
                                <option value="evening">Evening</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Paste your text" />
                        <textarea
                            v-model="form.raw_text"
                            rows="14"
                            class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm"
                            placeholder="Paste chapter notes, exercises, or a list of questions…"
                        />
                        <InputError :message="form.errors.raw_text" class="mt-1" />
                        <InputError :message="form.errors.chapter_ids" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link
                            :href="route('builder.create', composeQuery)"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton :disabled="form.processing">
                            {{ form.processing ? 'Submitting…' : 'Extract questions' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
