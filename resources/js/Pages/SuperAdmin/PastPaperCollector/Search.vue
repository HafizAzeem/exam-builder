<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { examYearOptions } from '@/utils/years';

const props = defineProps({
    grades: { type: Array, required: true },
    subjects: { type: Array, required: true },
    boards: { type: Array, default: () => [] },
    defaults: { type: Object, required: true },
});

const yearOptions = examYearOptions();

const form = useForm({
    grade_id: '',
    subject_id: '',
    board: props.defaults.board || props.boards?.[0]?.name || '',
    year: props.defaults.year || yearOptions[1] || yearOptions[0] || '',
    session: '',
    paper_type: props.defaults.paper_type || 'complete',
    language: props.defaults.language || 'english',
    max_results: props.defaults.max_results || 10,
    keywords_override: '',
    country: 'Pakistan',
});

const filteredSubjects = computed(() =>
    props.subjects.filter((s) => !form.grade_id || String(s.grade_id) === String(form.grade_id))
);

const submit = () => {
    form.post(route('super-admin.past-paper-collector.store'));
};
</script>

<template>
    <Head title="AI Past Paper Collector" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">AI Past Paper Collector</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Database first: if this Board + Class + Subject + Year + Session already exists, it is reused. Otherwise Gemini searches the web and OCR/vision reads scanned PDFs and images. Manual review is always required.
                    </p>
                </div>
                <Link :href="route('super-admin.past-paper-collector.index')" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                    Collection History
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Education Board *" />
                            <select v-model="form.board" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select board</option>
                                <option v-for="b in boards" :key="b.id" :value="b.name">{{ b.name }}</option>
                            </select>
                            <InputError :message="form.errors.board" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Country" />
                            <TextInput v-model="form.country" class="mt-1 block w-full bg-gray-50" readonly />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Grade / Class *" />
                            <select v-model="form.grade_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" @change="form.subject_id = ''">
                                <option value="">Select grade</option>
                                <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.label_en }}</option>
                            </select>
                            <InputError :message="form.errors.grade_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Subject *" />
                            <select v-model="form.subject_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select subject</option>
                                <option v-for="s in filteredSubjects" :key="s.id" :value="s.id">{{ s.name_en }}</option>
                            </select>
                            <InputError :message="form.errors.subject_id" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
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
                                <option value="">Any</option>
                                <option value="morning">Morning</option>
                                <option value="evening">Evening</option>
                            </select>
                            <InputError :message="form.errors.session" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Paper Type" />
                            <select v-model="form.paper_type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="complete">Complete Paper</option>
                                <option value="objective">Objective</option>
                                <option value="subjective">Subjective</option>
                            </select>
                            <InputError :message="form.errors.paper_type" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Language" />
                            <select v-model="form.language" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="english">English</option>
                                <option value="urdu">Urdu</option>
                                <option value="bilingual">Bilingual</option>
                            </select>
                            <InputError :message="form.errors.language" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Maximum Search Results" />
                            <TextInput v-model="form.max_results" type="number" min="1" max="20" class="mt-1 block w-full" />
                            <InputError :message="form.errors.max_results" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Search Keywords Override (optional)" />
                        <TextInput v-model="form.keywords_override" class="mt-1 block w-full" placeholder="Leave blank to auto-generate queries" />
                        <InputError :message="form.errors.keywords_override" class="mt-1" />
                        <p class="mt-1 text-xs text-gray-500">Example auto queries: “Lahore Board 9th Physics 2020 Morning Paper PDF”</p>
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">
                            {{ form.processing ? 'Starting…' : 'Collect Papers' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
