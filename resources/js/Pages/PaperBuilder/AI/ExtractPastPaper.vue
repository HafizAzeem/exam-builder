<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { examYearOptions } from '@/utils/years';

const props = defineProps({
    grade: { type: Object, required: true },
    subject: { type: Object, required: true },
    subjects: { type: Array, default: () => [] },
    chapters: { type: Array, default: () => [] },
    selectedChapterIds: { type: Array, default: () => [] },
    composeQuery: { type: Object, required: true },
    boards: { type: Array, default: () => [] },
    defaults: { type: Object, required: true },
});

const yearOptions = examYearOptions();

const form = useForm({
    grade_id: props.grade.id,
    subject_id: props.subject.id,
    chapter_ids: [...props.selectedChapterIds],
    board: props.defaults.board || props.boards?.[0]?.name || '',
    year: props.defaults.year || yearOptions[1] || yearOptions[0],
    session: props.defaults.session || '',
    paper_type: props.defaults.paper_type || 'complete',
    language: props.defaults.language || 'english',
    max_results: 8,
});

const selectedSubjectName = computed(() => {
    const match = (props.subjects || []).find((s) => String(s.id) === String(form.subject_id));
    return match?.name_en || props.subject.name_en;
});

watch(
    () => form.subject_id,
    (id) => {
        const match = (props.subjects || []).find((s) => String(s.id) === String(id));
        if (!match) return;

        // Chapters belong to the paper's subject; clear when extracting a different subject.
        if (String(id) !== String(props.subject.id)) {
            form.chapter_ids = [];
        } else {
            form.chapter_ids = [...props.selectedChapterIds];
        }

        form.language =
            String(match.name_en || '').toLowerCase() === 'urdu' ? 'urdu' : form.language === 'urdu' ? 'english' : form.language;
    }
);

const submit = () => {
    form.post(route('builder.ai.extract-past-paper.store'));
};
</script>

<template>
    <Head title="Extract past paper" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Extract past paper</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ grade.label_en }} · {{ selectedSubjectName }} — questions go into the shared bank.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('builder.ai.generate', composeQuery)"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        AI Generate
                    </Link>
                    <Link
                        :href="route('builder.create', composeQuery)"
                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        ← Back to paper
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form class="space-y-5 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Class" />
                            <input
                                type="text"
                                :value="grade.label_en"
                                class="mt-1 w-full rounded-md border-gray-300 bg-gray-50 shadow-sm"
                                readonly
                            />
                        </div>
                        <div>
                            <InputLabel value="Subject *" />
                            <select v-model.number="form.subject_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" required>
                                <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name_en }}</option>
                            </select>
                            <InputError :message="form.errors.subject_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Board *" />
                            <select v-model="form.board" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" required>
                                <option value="">Select board</option>
                                <option v-for="b in boards" :key="b.id" :value="b.name">{{ b.name }}</option>
                            </select>
                            <InputError :message="form.errors.board" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Year *" />
                            <select v-model.number="form.year" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" required>
                                <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                            </select>
                            <InputError :message="form.errors.year" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Session" />
                            <select v-model="form.session" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Any / not specified</option>
                                <option value="morning">Morning</option>
                                <option value="evening">Evening</option>
                            </select>
                            <InputError :message="form.errors.session" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Paper type" />
                            <select v-model="form.paper_type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="complete">Complete</option>
                                <option value="objective">Objective</option>
                                <option value="subjective">Subjective</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Language" />
                            <select v-model="form.language" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="english">English</option>
                                <option value="urdu">Urdu</option>
                                <option value="both">Both</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link
                            :href="route('builder.create', composeQuery)"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton :disabled="form.processing || !form.board || !form.year || !form.subject_id">
                            {{ form.processing ? 'Starting…' : 'Find / extract past paper' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
