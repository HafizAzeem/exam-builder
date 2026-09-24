<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    grade: { type: Object, required: true },
    subject: { type: Object, required: true },
    chapters: { type: Array, default: () => [] },
    selectedChapterIds: { type: Array, default: () => [] },
    composeQuery: { type: Object, required: true },
    boards: { type: Array, default: () => [] },
    defaultLanguage: { type: String, default: 'english' },
});

const isUrduSubject = computed(() => String(props.subject?.name_en || '').toLowerCase() === 'urdu');

const form = useForm({
    grade_id: props.grade.id,
    subject_id: props.subject.id,
    chapter_ids: [...props.selectedChapterIds],
    content_source: 'exercise',
    board: props.boards?.[0]?.name ?? 'Lahore Board',
    language: props.defaultLanguage || (isUrduSubject.value ? 'urdu' : 'english'),
    counts: {
        mcq: 10,
        short: 5,
        long: 2,
        fill: 0,
        truefalse: 0,
    },
});

const chapterSummary = computed(() =>
    props.chapters.map((c) => `${c.number}. ${c.title_en}`).join(', ')
);

const totalRequested = computed(() =>
    Object.values(form.counts).reduce((sum, n) => sum + (Number(n) || 0), 0)
);

const applyPreset = (name) => {
    if (name === 'unit') {
        form.counts = { mcq: 10, short: 5, long: 2, fill: 0, truefalse: 0 };
    } else if (name === 'board') {
        form.counts = { mcq: 20, short: 10, long: 3, fill: 0, truefalse: 0 };
    } else if (name === 'quick') {
        form.counts = { mcq: 5, short: 3, long: 1, fill: 0, truefalse: 0 };
    }
};

const submit = () => {
    form.post(route('builder.ai.generate.store'));
};
</script>

<template>
    <Head title="AI Generate · Paper" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">AI Generate</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ grade.label_en }} · {{ subject.name_en }} — AI prepares exam-ready questions for your paper.
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
                        <p class="mt-1 text-xs text-slate-500">
                            Choose a question style and counts — AI builds a smart draft for this paper.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="applyPreset('quick')">
                            Quick quiz
                        </button>
                        <button type="button" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="applyPreset('unit')">
                            Unit test
                        </button>
                        <button type="button" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50" @click="applyPreset('board')">
                            Board-style
                        </button>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel value="Question style" />
                            <select v-model="form.content_source" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="exercise">Exercise questions</option>
                                <option value="past_paper">Past papers</option>
                                <option value="online_practice">Additional Questions</option>
                            </select>
                            <InputError :message="form.errors.content_source" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Board" />
                            <select v-model="form.board" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Any / general</option>
                                <option v-for="b in boards" :key="b.id" :value="b.name">{{ b.name }}</option>
                            </select>
                            <InputError :message="form.errors.board" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Language" />
                            <select v-model="form.language" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="english">English</option>
                                <option value="urdu">Urdu</option>
                                <option value="both">Both</option>
                            </select>
                            <p v-if="isUrduSubject" class="mt-1 text-xs text-slate-500">Urdu subject defaults to Urdu text (no forced English).</p>
                            <InputError :message="form.errors.language" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel :value="`Question counts (${totalRequested} total)`" />
                        <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-5">
                            <label class="text-sm">
                                <span class="text-slate-600">MCQ</span>
                                <input v-model.number="form.counts.mcq" type="number" min="0" max="50" class="mt-1 w-full rounded-md border-gray-300" />
                            </label>
                            <label class="text-sm">
                                <span class="text-slate-600">Short</span>
                                <input v-model.number="form.counts.short" type="number" min="0" max="50" class="mt-1 w-full rounded-md border-gray-300" />
                            </label>
                            <label class="text-sm">
                                <span class="text-slate-600">Long</span>
                                <input v-model.number="form.counts.long" type="number" min="0" max="20" class="mt-1 w-full rounded-md border-gray-300" />
                            </label>
                            <label class="text-sm">
                                <span class="text-slate-600">Fill</span>
                                <input v-model.number="form.counts.fill" type="number" min="0" max="30" class="mt-1 w-full rounded-md border-gray-300" />
                            </label>
                            <label class="text-sm">
                                <span class="text-slate-600">T/F</span>
                                <input v-model.number="form.counts.truefalse" type="number" min="0" max="30" class="mt-1 w-full rounded-md border-gray-300" />
                            </label>
                        </div>
                        <InputError :message="form.errors.counts" class="mt-1" />
                        <InputError :message="form.errors.chapter_ids" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link
                            :href="route('builder.create', composeQuery)"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton :disabled="form.processing || totalRequested < 1">
                            {{ form.processing ? 'Starting…' : 'Generate questions' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
