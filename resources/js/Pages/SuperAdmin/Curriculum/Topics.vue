<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CurriculumNav from '@/Pages/SuperAdmin/Curriculum/CurriculumNav.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    chapter: { type: Object, required: true },
    topics: { type: Array, required: true },
});

const createForm = useForm({
    code: '',
    title_en: '',
    title_ur: '',
    sort_order: '',
    is_active: true,
});

const editForm = useForm({
    code: '',
    title_en: '',
    title_ur: '',
    sort_order: '',
    is_active: true,
});

const editing = ref(null);

const submitCreate = () => {
    createForm.post(route('super-admin.curriculum.topics.store', props.chapter.id), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('code', 'title_en', 'title_ur', 'sort_order'),
    });
};

const openEdit = (topic) => {
    editing.value = topic;
    editForm.defaults({
        code: topic.code,
        title_en: topic.title_en,
        title_ur: topic.title_ur ?? '',
        sort_order: topic.sort_order,
        is_active: !!topic.is_active,
    });
    editForm.reset();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.patch(route('super-admin.curriculum.topics.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
};

const toggle = (topic) => {
    router.patch(
        route('super-admin.curriculum.topics.update', topic.id),
        { is_active: !topic.is_active },
        { preserveScroll: true },
    );
};

const destroy = (topic) => {
    if (!confirm(`Delete topic ${topic.code}? Linked questions will keep the chapter but lose this topic.`)) return;
    router.delete(route('super-admin.curriculum.topics.destroy', topic.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Topics — ${chapter.title_en}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ chapter.title_en }} topics</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <CurriculumNav
                    current="classes"
                    :crumbs="[
                        { label: 'Classes', href: route('super-admin.curriculum.grades.index') },
                        { label: chapter.subject?.grade?.label_en, href: route('super-admin.curriculum.subjects.index', chapter.subject?.grade_id) },
                        { label: chapter.subject?.name_en, href: route('super-admin.curriculum.chapters.index', chapter.subject_id) },
                        { label: `Ch ${chapter.number}` },
                    ]"
                />

                <form class="mb-6 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-6" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel value="Code" />
                        <TextInput v-model="createForm.code" class="mt-1 block w-full" placeholder="1.1" required />
                        <InputError :message="createForm.errors.code" class="mt-1" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel value="Title (EN)" />
                        <TextInput v-model="createForm.title_en" class="mt-1 block w-full" required />
                        <InputError :message="createForm.errors.title_en" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Title (UR)" />
                        <TextInput v-model="createForm.title_ur" class="mt-1 block w-full" dir="rtl" />
                    </div>
                    <div>
                        <InputLabel value="Sort" />
                        <TextInput v-model="createForm.sort_order" type="number" min="0" class="mt-1 block w-full" />
                    </div>
                    <div class="flex items-end gap-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                            Active
                        </label>
                        <PrimaryButton :disabled="createForm.processing">Add</PrimaryButton>
                    </div>
                </form>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Code</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Title</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Questions</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="topic in topics" :key="topic.id">
                                <td class="px-4 py-3 font-mono text-xs">{{ topic.code }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ topic.title_en }}</p>
                                    <p v-if="topic.title_ur" class="text-gray-500" dir="rtl">{{ topic.title_ur }}</p>
                                </td>
                                <td class="px-4 py-3">{{ topic.questions_count }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="topic.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ topic.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" class="text-gray-700 hover:underline" @click="openEdit(topic)">Edit</button>
                                        <button type="button" class="text-amber-700 hover:underline" @click="toggle(topic)">
                                            {{ topic.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button type="button" class="text-red-600 hover:underline" @click="destroy(topic)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!topics.length">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">No topics yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="!!editing" max-width="lg" @close="editing = null">
            <form class="p-6" @submit.prevent="submitEdit">
                <h3 class="text-lg font-medium text-gray-900">Edit topic</h3>
                <div class="mt-4 grid gap-3">
                    <div>
                        <InputLabel value="Code" />
                        <TextInput v-model="editForm.code" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.code" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Title (EN)" />
                        <TextInput v-model="editForm.title_en" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.title_en" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Title (UR)" />
                        <TextInput v-model="editForm.title_ur" class="mt-1 block w-full" dir="rtl" />
                    </div>
                    <div>
                        <InputLabel value="Sort" />
                        <TextInput v-model="editForm.sort_order" type="number" min="0" class="mt-1 block w-full" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        Active
                    </label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton type="button" @click="editing = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="editForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
