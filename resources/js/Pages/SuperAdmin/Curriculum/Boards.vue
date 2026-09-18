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

defineProps({
    boards: { type: Array, required: true },
});

const createForm = useForm({
    name: '',
    region: '',
    sort_order: '',
    is_active: true,
});

const editForm = useForm({
    name: '',
    region: '',
    sort_order: '',
    is_active: true,
});

const editing = ref(null);

const submitCreate = () => {
    createForm.post(route('super-admin.curriculum.boards.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name', 'region', 'sort_order'),
    });
};

const openEdit = (board) => {
    editing.value = board;
    editForm.defaults({
        name: board.name,
        region: board.region ?? '',
        sort_order: board.sort_order,
        is_active: !!board.is_active,
    });
    editForm.reset();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.patch(route('super-admin.curriculum.boards.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
};

const toggle = (board) => {
    router.patch(
        route('super-admin.curriculum.boards.update', board.id),
        { is_active: !board.is_active },
        { preserveScroll: true },
    );
};

const destroy = (board) => {
    if (!confirm(`Delete ${board.name}?`)) return;
    router.delete(route('super-admin.curriculum.boards.destroy', board.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Curriculum — Boards" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Education boards</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <CurriculumNav current="boards" />

                <p class="mb-4 text-sm text-gray-600">
                    Inactive boards are hidden from AI import, past-paper collector, and paper-builder filters.
                </p>

                <form class="mb-6 grid gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:grid-cols-5" @submit.prevent="submitCreate">
                    <div>
                        <InputLabel value="Board name" />
                        <TextInput v-model="createForm.name" class="mt-1 block w-full" required />
                        <InputError :message="createForm.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Region" />
                        <TextInput v-model="createForm.region" class="mt-1 block w-full" />
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
                        <PrimaryButton :disabled="createForm.processing">Add Board</PrimaryButton>
                    </div>
                </form>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Board</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Region</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="board in boards" :key="board.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ board.name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ board.region || '—' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="board.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ board.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" class="text-gray-700 hover:underline" @click="openEdit(board)">Edit</button>
                                        <button type="button" class="text-amber-700 hover:underline" @click="toggle(board)">
                                            {{ board.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button type="button" class="text-red-600 hover:underline" @click="destroy(board)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!boards.length">
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">No boards yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="!!editing" max-width="lg" @close="editing = null">
            <form class="p-6" @submit.prevent="submitEdit">
                <h3 class="text-lg font-medium text-gray-900">Edit board</h3>
                <div class="mt-4 grid gap-3">
                    <div>
                        <InputLabel value="Name" />
                        <TextInput v-model="editForm.name" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Region" />
                        <TextInput v-model="editForm.region" class="mt-1 block w-full" />
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
