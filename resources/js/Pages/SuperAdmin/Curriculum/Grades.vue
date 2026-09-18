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
    grades: { type: Array, required: true },
});

const createForm = useForm({
    number: '',
    label_en: '',
    label_ur: '',
    is_active: true,
});

const editForm = useForm({
    number: '',
    label_en: '',
    label_ur: '',
    is_active: false,
});

const editing = ref(null);

const submitCreate = () => {
    createForm.post(route('super-admin.curriculum.grades.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('number', 'label_en', 'label_ur'),
    });
};

const openEdit = (grade) => {
    editing.value = grade;
    editForm.defaults({
        number: grade.number,
        label_en: grade.label_en,
        label_ur: grade.label_ur ?? '',
        is_active: !!grade.is_active,
    });
    editForm.reset();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.patch(route('super-admin.curriculum.grades.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
};

const toggle = (grade) => {
    router.patch(
        route('super-admin.curriculum.grades.update', grade.id),
        { is_active: !grade.is_active },
        { preserveScroll: true },
    );
};

const destroy = (grade) => {
    if (!confirm(`Delete ${grade.label_en}? This cannot be undone.`)) return;
    router.delete(route('super-admin.curriculum.grades.destroy', grade.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Curriculum — Classes" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Curriculum</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <CurriculumNav current="classes" />

                <p class="mb-4 text-sm text-gray-600">
                    Inactive classes stay in this admin list but are hidden from paper builder, AI import, and other app dropdowns.
                </p>

                <form class="mb-6 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-5" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel value="Number" />
                        <TextInput v-model="createForm.number" type="number" min="1" max="12" class="mt-1 block w-full" required />
                        <InputError :message="createForm.errors.number" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Label (EN)" />
                        <TextInput v-model="createForm.label_en" class="mt-1 block w-full" required />
                        <InputError :message="createForm.errors.label_en" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Label (UR)" />
                        <TextInput v-model="createForm.label_ur" class="mt-1 block w-full" dir="rtl" />
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm text-gray-700">
                        <input v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        Active
                    </label>
                    <div class="flex items-end">
                        <PrimaryButton :disabled="createForm.processing">Add Class</PrimaryButton>
                    </div>
                </form>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Class</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Urdu</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Subjects</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="grade in grades" :key="grade.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ grade.label_en }}</td>
                                <td class="px-4 py-3 text-gray-600" dir="rtl">{{ grade.label_ur || '—' }}</td>
                                <td class="px-4 py-3">{{ grade.subjects_count }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="grade.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ grade.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Link
                                            :href="route('super-admin.curriculum.subjects.index', grade.id)"
                                            class="text-indigo-600 hover:text-indigo-800"
                                        >
                                            Subjects
                                        </Link>
                                        <button type="button" class="text-gray-700 hover:underline" @click="openEdit(grade)">Edit</button>
                                        <button type="button" class="text-amber-700 hover:underline" @click="toggle(grade)">
                                            {{ grade.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button type="button" class="text-red-600 hover:underline" @click="destroy(grade)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="!!editing" max-width="lg" @close="editing = null">
            <form class="p-6" @submit.prevent="submitEdit">
                <h3 class="text-lg font-medium text-gray-900">Edit class</h3>
                <div class="mt-4 grid gap-3">
                    <div>
                        <InputLabel value="Number" />
                        <TextInput v-model="editForm.number" type="number" min="1" max="12" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.number" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Label (EN)" />
                        <TextInput v-model="editForm.label_en" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.label_en" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Label (UR)" />
                        <TextInput v-model="editForm.label_ur" class="mt-1 block w-full" dir="rtl" />
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
