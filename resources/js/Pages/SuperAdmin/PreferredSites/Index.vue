<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    sites: { type: Array, required: true },
    sourceTypeOptions: { type: Array, default: () => [] },
});

const sourceLabels = {
    exercise: 'Exercise',
    past_paper: 'Past papers',
    online_practice: 'Additional Questions',
};

const createForm = useForm({
    name: '',
    domain: '',
    priority: '',
    is_active: true,
    notes: '',
    source_types: [...props.sourceTypeOptions],
});

const editForm = useForm({
    name: '',
    domain: '',
    priority: '',
    is_active: true,
    notes: '',
    source_types: [],
});

const editing = ref(null);

const submitCreate = () => {
    createForm.post(route('super-admin.preferred-sites.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset('name', 'domain', 'priority', 'notes');
            createForm.source_types = [...props.sourceTypeOptions];
            createForm.is_active = true;
        },
    });
};

const openEdit = (site) => {
    editing.value = site;
    editForm.defaults({
        name: site.name,
        domain: site.domain,
        priority: site.priority,
        is_active: !!site.is_active,
        notes: site.notes ?? '',
        source_types: Array.isArray(site.source_types) && site.source_types.length
            ? [...site.source_types]
            : [...props.sourceTypeOptions],
    });
    editForm.reset();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.patch(route('super-admin.preferred-sites.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
};

const toggle = (site) => {
    router.patch(
        route('super-admin.preferred-sites.update', site.id),
        { is_active: !site.is_active },
        { preserveScroll: true },
    );
};

const destroy = (site) => {
    if (!confirm(`Delete ${site.name}?`)) return;
    router.delete(route('super-admin.preferred-sites.destroy', site.id), { preserveScroll: true });
};

const formatTypes = (types) => {
    if (!Array.isArray(types) || !types.length) return 'All';
    return types.map((t) => sourceLabels[t] || t).join(', ');
};
</script>

<template>
    <Head title="Preferred AI Sites" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Preferred AI websites</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Teacher AI Generate tries these sites first (waterfall). If none are active, AI invents questions on its own.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <form class="mb-6 space-y-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm" @submit.prevent="submitCreate">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div>
                            <InputLabel value="Name" />
                            <TextInput v-model="createForm.name" class="mt-1 block w-full" required />
                            <InputError :message="createForm.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Domain" />
                            <TextInput v-model="createForm.domain" class="mt-1 block w-full" placeholder="freeilm.com" required />
                            <InputError :message="createForm.errors.domain" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Priority (lower = first)" />
                            <TextInput v-model="createForm.priority" type="number" min="0" class="mt-1 block w-full" />
                        </div>
                        <label class="flex items-end gap-2 pb-2 text-sm text-gray-700">
                            <input v-model="createForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                            Active
                        </label>
                    </div>
                    <div>
                        <InputLabel value="Used for" />
                        <div class="mt-2 flex flex-wrap gap-3 text-sm text-gray-700">
                            <label v-for="opt in sourceTypeOptions" :key="opt" class="inline-flex items-center gap-2">
                                <input v-model="createForm.source_types" type="checkbox" :value="opt" class="rounded border-gray-300 text-indigo-600" />
                                {{ sourceLabels[opt] || opt }}
                            </label>
                        </div>
                        <InputError :message="createForm.errors.source_types" class="mt-1" />
                    </div>
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <InputLabel value="Notes" />
                            <TextInput v-model="createForm.notes" class="mt-1 block w-full" />
                        </div>
                        <PrimaryButton :disabled="createForm.processing">Add site</PrimaryButton>
                    </div>
                </form>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Site</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Domain</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Sources</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Priority</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="site in sites" :key="site.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ site.name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ site.domain }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ formatTypes(site.source_types) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ site.priority }}</td>
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="site.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                                        @click="toggle(site)"
                                    >
                                        {{ site.is_active ? 'Active' : 'Off' }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button type="button" class="text-indigo-600 hover:underline" @click="openEdit(site)">Edit</button>
                                    <button type="button" class="text-rose-600 hover:underline" @click="destroy(site)">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="!sites.length">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    No preferred sites yet. AI will invent questions without a website list.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="!!editing" max-width="lg" @close="editing = null">
            <form class="space-y-4 p-6" @submit.prevent="submitEdit">
                <h3 class="text-lg font-semibold text-gray-900">Edit preferred site</h3>
                <div>
                    <InputLabel value="Name" />
                    <TextInput v-model="editForm.name" class="mt-1 block w-full" required />
                    <InputError :message="editForm.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Domain" />
                    <TextInput v-model="editForm.domain" class="mt-1 block w-full" required />
                    <InputError :message="editForm.errors.domain" class="mt-1" />
                </div>
                <div>
                    <InputLabel value="Priority" />
                    <TextInput v-model="editForm.priority" type="number" min="0" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel value="Used for" />
                    <div class="mt-2 flex flex-wrap gap-3 text-sm text-gray-700">
                        <label v-for="opt in sourceTypeOptions" :key="opt" class="inline-flex items-center gap-2">
                            <input v-model="editForm.source_types" type="checkbox" :value="opt" class="rounded border-gray-300 text-indigo-600" />
                            {{ sourceLabels[opt] || opt }}
                        </label>
                    </div>
                </div>
                <div>
                    <InputLabel value="Notes" />
                    <TextInput v-model="editForm.notes" class="mt-1 block w-full" />
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                    Active
                </label>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="editing = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="editForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
