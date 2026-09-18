<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CurriculumNav from '@/Pages/SuperAdmin/Curriculum/CurriculumNav.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    subject: { type: Object, required: true },
    chapters: { type: Array, required: true },
});

const createForm = useForm({
    number: '',
    title_en: '',
    title_ur: '',
    is_active: true,
});

const editForm = useForm({
    number: '',
    title_en: '',
    title_ur: '',
    is_active: true,
});

const editing = ref(null);

const submitCreate = () => {
    createForm.post(route('super-admin.curriculum.chapters.store', props.subject.id), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('number', 'title_en', 'title_ur'),
    });
};

const openEdit = (chapter) => {
    editing.value = chapter;
    editForm.defaults({
        number: chapter.number,
        title_en: chapter.title_en,
        title_ur: chapter.title_ur ?? '',
        is_active: !!chapter.is_active,
    });
    editForm.reset();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.patch(route('super-admin.curriculum.chapters.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
};

const toggle = (chapter) => {
    router.patch(
        route('super-admin.curriculum.chapters.update', chapter.id),
        { is_active: !chapter.is_active },
        { preserveScroll: true },
    );
};

const destroy = (chapter) => {
    if (!confirm(`Delete chapter ${chapter.number}?`)) return;
    router.delete(route('super-admin.curriculum.chapters.destroy', chapter.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Chapters — ${subject.name_en}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ subject.name_en }} chapters</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <CurriculumNav
                    current="classes"
                    :crumbs="[
                        { label: 'Classes', href: route('super-admin.curriculum.grades.index') },
                        { label: subject.grade?.label_en, href: route('super-admin.curriculum.subjects.index', subject.grade_id) },
                        { label: subject.name_en },
                    ]"
                />

                <form class="mb-6 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-5" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel value="No." />
                        <TextInput v-model="createForm.number" type="number" min="1" class="mt-1 block w-full" required />
                        <InputError :message="createForm.errors.number" class="mt-1" />
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
                                <th class="px-4 py-3 text-left font-medium text-gray-500">No.</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Title</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Topics</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Questions</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="chapter in chapters" :key="chapter.id">
                                <td class="px-4 py-3">{{ chapter.number }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ chapter.title_en }}</p>
                                    <p v-if="chapter.title_ur" class="text-gray-500" dir="rtl">{{ chapter.title_ur }}</p>
                                </td>
                                <td class="px-4 py-3">{{ chapter.topics_count }}</td>
                                <td class="px-4 py-3">{{ chapter.questions_count }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="chapter.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ chapter.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Link
                                            :href="route('super-admin.curriculum.topics.index', chapter.id)"
                                            class="text-indigo-600 hover:text-indigo-800"
                                        >
                                            Topics
                                        </Link>
                                        <button type="button" class="text-gray-700 hover:underline" @click="openEdit(chapter)">Edit</button>
                                        <button type="button" class="text-amber-700 hover:underline" @click="toggle(chapter)">
                                            {{ chapter.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button type="button" class="text-red-600 hover:underline" @click="destroy(chapter)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!chapters.length">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No chapters yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="!!editing" max-width="lg" @close="editing = null">
            <form class="p-6" @submit.prevent="submitEdit">
                <h3 class="text-lg font-medium text-gray-900">Edit chapter</h3>
                <div class="mt-4 grid gap-3">
                    <div>
                        <InputLabel value="Number" />
                        <TextInput v-model="editForm.number" type="number" min="1" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.number" class="mt-1" />
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
