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
    grade: { type: Object, required: true },
    subjects: { type: Array, required: true },
});

const createForm = useForm({
    name_en: '',
    name_ur: '',
    sort_order: '',
    is_active: true,
});

const editForm = useForm({
    name_en: '',
    name_ur: '',
    sort_order: '',
    is_active: true,
});

const editing = ref(null);

const submitCreate = () => {
    createForm.post(route('super-admin.curriculum.subjects.store', props.grade.id), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name_en', 'name_ur', 'sort_order'),
    });
};

const openEdit = (subject) => {
    editing.value = subject;
    editForm.defaults({
        name_en: subject.name_en,
        name_ur: subject.name_ur ?? '',
        sort_order: subject.sort_order,
        is_active: !!subject.is_active,
    });
    editForm.reset();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.patch(route('super-admin.curriculum.subjects.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
};

const toggle = (subject) => {
    router.patch(
        route('super-admin.curriculum.subjects.update', subject.id),
        { is_active: !subject.is_active },
        { preserveScroll: true },
    );
};

const destroy = (subject) => {
    if (!confirm(`Delete ${subject.name_en}?`)) return;
    router.delete(route('super-admin.curriculum.subjects.destroy', subject.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Subjects — ${grade.label_en}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ grade.label_en }} subjects</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <CurriculumNav
                    current="classes"
                    :crumbs="[
                        { label: 'Classes', href: route('super-admin.curriculum.grades.index') },
                        { label: grade.label_en },
                    ]"
                />

                <form class="mb-6 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-5" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel value="Name (EN)" />
                        <TextInput v-model="createForm.name_en" class="mt-1 block w-full" required />
                        <InputError :message="createForm.errors.name_en" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Name (UR)" />
                        <TextInput v-model="createForm.name_ur" class="mt-1 block w-full" dir="rtl" />
                    </div>
                    <div>
                        <InputLabel value="Sort" />
                        <TextInput v-model="createForm.sort_order" type="number" min="0" class="mt-1 block w-full" />
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm text-gray-700">
                        <input v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        Active
                    </label>
                    <div class="flex items-end">
                        <PrimaryButton :disabled="createForm.processing">Add Subject</PrimaryButton>
                    </div>
                </form>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Subject</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Urdu</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Chapters</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="subject in subjects" :key="subject.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ subject.name_en }}</td>
                                <td class="px-4 py-3 text-gray-600" dir="rtl">{{ subject.name_ur || '—' }}</td>
                                <td class="px-4 py-3">{{ subject.chapters_count }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="subject.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ subject.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Link
                                            :href="route('super-admin.curriculum.chapters.index', subject.id)"
                                            class="text-indigo-600 hover:text-indigo-800"
                                        >
                                            Chapters
                                        </Link>
                                        <button type="button" class="text-gray-700 hover:underline" @click="openEdit(subject)">Edit</button>
                                        <button type="button" class="text-amber-700 hover:underline" @click="toggle(subject)">
                                            {{ subject.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button type="button" class="text-red-600 hover:underline" @click="destroy(subject)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!subjects.length">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">No subjects yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="!!editing" max-width="lg" @close="editing = null">
            <form class="p-6" @submit.prevent="submitEdit">
                <h3 class="text-lg font-medium text-gray-900">Edit subject</h3>
                <div class="mt-4 grid gap-3">
                    <div>
                        <InputLabel value="Name (EN)" />
                        <TextInput v-model="editForm.name_en" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.name_en" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Name (UR)" />
                        <TextInput v-model="editForm.name_ur" class="mt-1 block w-full" dir="rtl" />
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
