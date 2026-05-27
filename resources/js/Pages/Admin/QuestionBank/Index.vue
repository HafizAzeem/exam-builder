<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    grades: Array,
    subjects: Array,
    chapters: Array,
    questions: Object,
    filters: Object,
});

const filters = ref({
    grade_id: props.filters?.grade_id ?? '',
    subject_id: props.filters?.subject_id ?? '',
    chapter_id: props.filters?.chapter_id ?? '',
    type: props.filters?.type ?? '',
    source: props.filters?.source ?? '',
    search: props.filters?.search ?? '',
});

const filteredSubjects = computed(() => {
    if (!filters.value.grade_id) return props.subjects ?? [];
    return (props.subjects ?? []).filter((s) => String(s.grade_id) === String(filters.value.grade_id));
});

const filteredChapters = computed(() => {
    if (!filters.value.subject_id) return [];
    return (props.chapters ?? []).filter((c) => String(c.subject_id) === String(filters.value.subject_id));
});

const apply = () => {
    router.get(route('admin.question-bank.index'), { ...filters.value }, { preserveState: true, preserveScroll: true });
};

const reset = () => {
    filters.value = { grade_id: '', subject_id: '', chapter_id: '', type: '', source: '', search: '' };
    apply();
};

const createForm = useForm({
    chapter_id: '',
    type: 'mcq',
    source: 'exercise',
    text_en: '',
    text_ur: '',
    image: null,
    is_active: true,
    mcq: { option_a_en: '', option_b_en: '', option_c_en: '', option_d_en: '', correct_option: 'a' },
    past: { board_name: '', year: '', session: '' },
    parts: [],
});

const editingId = ref(null);
const editForm = useForm({});

const startEdit = (q) => {
    editingId.value = q.id;
    editForm.defaults({
        chapter_id: q.chapter_id,
        type: q.type,
        source: q.source,
        text_en: q.text_en,
        text_ur: q.text_ur,
        image: null,
        remove_image: false,
        is_active: q.is_active,
        mcq: q.mcq_options
            ? {
                  option_a_en: q.mcq_options.option_a_en,
                  option_b_en: q.mcq_options.option_b_en,
                  option_c_en: q.mcq_options.option_c_en,
                  option_d_en: q.mcq_options.option_d_en,
                  correct_option: q.mcq_options.correct_option,
              }
            : { option_a_en: '', option_b_en: '', option_c_en: '', option_d_en: '', correct_option: 'a' },
        past: q.past_paper_tag
            ? { board_name: q.past_paper_tag.board_name, year: q.past_paper_tag.year, session: q.past_paper_tag.session }
            : { board_name: '', year: '', session: '' },
    });
    editForm.reset();
};

const stopEdit = () => {
    editingId.value = null;
};
</script>

<template>
    <Head title="Question Bank" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Question Bank</h2>
                <div class="flex gap-2">
                    <Link :href="route('admin.question-bank.importForm')" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white"
                        >Import</Link
                    >
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6">
            <div class="mb-6 grid gap-3 rounded-lg bg-white p-4 shadow md:grid-cols-7">
                <select v-model="filters.grade_id" class="rounded border-gray-300">
                    <option value="">All grades</option>
                    <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.label_en }}</option>
                </select>
                <select v-model="filters.subject_id" class="rounded border-gray-300" :disabled="!filteredSubjects.length">
                    <option value="">All subjects</option>
                    <option v-for="s in filteredSubjects" :key="s.id" :value="s.id">{{ s.name_en }}</option>
                </select>
                <select v-model="filters.chapter_id" class="rounded border-gray-300" :disabled="!filteredChapters.length">
                    <option value="">All chapters</option>
                    <option v-for="c in filteredChapters" :key="c.id" :value="c.id">Ch {{ c.number }}</option>
                </select>
                <select v-model="filters.type" class="rounded border-gray-300">
                    <option value="">All types</option>
                    <option value="mcq">MCQ</option>
                    <option value="short">Short</option>
                    <option value="long">Long</option>
                    <option value="fill">Fill</option>
                    <option value="truefalse">True/False</option>
                </select>
                <select v-model="filters.source" class="rounded border-gray-300">
                    <option value="">All sources</option>
                    <option value="exercise">Exercise</option>
                    <option value="additional">Additional</option>
                    <option value="past_paper">Past Paper</option>
                </select>
                <input v-model="filters.search" class="rounded border-gray-300 md:col-span-2" placeholder="Search..." @keyup.enter="apply" />
                <button class="rounded bg-gray-900 px-3 py-2 text-sm text-white" @click="apply">Filter</button>
                <button class="rounded bg-gray-200 px-3 py-2 text-sm" @click="reset">Reset</button>
            </div>

            <div class="mb-8 rounded-lg bg-white p-4 shadow">
                <h3 class="font-semibold">Create Question</h3>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <select v-model="createForm.chapter_id" class="rounded border-gray-300" required>
                        <option value="">Select chapter</option>
                        <option v-for="c in chapters" :key="c.id" :value="c.id">Ch {{ c.number }}: {{ c.title_en }}</option>
                    </select>
                    <div class="grid grid-cols-2 gap-3">
                        <select v-model="createForm.type" class="rounded border-gray-300">
                            <option value="mcq">MCQ</option>
                            <option value="short">Short</option>
                            <option value="long">Long</option>
                            <option value="fill">Fill</option>
                            <option value="truefalse">True/False</option>
                        </select>
                        <select v-model="createForm.source" class="rounded border-gray-300">
                            <option value="exercise">Exercise</option>
                            <option value="additional">Additional</option>
                            <option value="past_paper">Past Paper</option>
                        </select>
                    </div>
                    <textarea v-model="createForm.text_en" class="rounded border-gray-300" rows="3" placeholder="Question (EN)" />
                    <textarea v-model="createForm.text_ur" class="rounded border-gray-300" rows="3" placeholder="Question (UR)" />

                    <div class="md:col-span-2">
                        <input type="file" accept="image/*" @change="createForm.image = $event.target.files[0]" />
                    </div>

                    <div v-if="createForm.type === 'mcq'" class="md:col-span-2 grid gap-2 md:grid-cols-2">
                        <input v-model="createForm.mcq.option_a_en" class="rounded border-gray-300" placeholder="Option A" />
                        <input v-model="createForm.mcq.option_b_en" class="rounded border-gray-300" placeholder="Option B" />
                        <input v-model="createForm.mcq.option_c_en" class="rounded border-gray-300" placeholder="Option C" />
                        <input v-model="createForm.mcq.option_d_en" class="rounded border-gray-300" placeholder="Option D" />
                        <select v-model="createForm.mcq.correct_option" class="rounded border-gray-300">
                            <option value="a">Correct: A</option>
                            <option value="b">Correct: B</option>
                            <option value="c">Correct: C</option>
                            <option value="d">Correct: D</option>
                        </select>
                    </div>

                    <div v-if="createForm.source === 'past_paper'" class="md:col-span-2 grid gap-2 md:grid-cols-3">
                        <input v-model="createForm.past.board_name" class="rounded border-gray-300" placeholder="Board name" />
                        <input v-model="createForm.past.year" class="rounded border-gray-300" placeholder="Year" />
                        <select v-model="createForm.past.session" class="rounded border-gray-300">
                            <option value="">Session</option>
                            <option value="morning">Morning</option>
                            <option value="evening">Evening</option>
                        </select>
                    </div>
                </div>

                <button
                    class="mt-4 rounded bg-indigo-600 px-4 py-2 text-white"
                    :disabled="createForm.processing"
                    @click="createForm.post(route('admin.question-bank.store'), { forceFormData: true, onSuccess: () => createForm.reset() })"
                >
                    Create
                </button>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left">ID</th>
                            <th class="px-4 py-3 text-left">Curriculum</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Source</th>
                            <th class="px-4 py-3 text-left">Text</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="q in questions.data" :key="q.id">
                            <td class="px-4 py-3">{{ q.id }}</td>
                            <td class="px-4 py-3">
                                {{ q.chapter?.subject?.grade?.label_en }} · {{ q.chapter?.subject?.name_en }} · Ch {{ q.chapter?.number }}
                            </td>
                            <td class="px-4 py-3">{{ q.type }}</td>
                            <td class="px-4 py-3">{{ q.source }}</td>
                            <td class="px-4 py-3">
                                <div class="line-clamp-2 max-w-xl">{{ q.text_en || q.text_ur }}</div>
                                <img v-if="q.image_path" :src="`/storage/${q.image_path}`" class="mt-2 h-10 w-10 rounded object-cover" />
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button class="text-indigo-600 hover:underline" @click="startEdit(q)">Edit</button>
                                <Link
                                    :href="route('admin.question-bank.destroy', q.id)"
                                    method="delete"
                                    as="button"
                                    class="text-red-600 hover:underline"
                                >
                                    Delete
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

