<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ institution: Object, logoUrl: String, boards: Array });

const form = useForm({
    name: props.institution.name,
    board_id: props.institution.board_id ?? '',
    owner_name: props.institution.owner_name ?? '',
    phone: props.institution.phone ?? '',
    address: props.institution.address ?? '',
    city: props.institution.city ?? '',
    logo: null,
});
</script>

<template>
    <Head title="Institute Profile" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Institute Profile</h2></template>
        <div class="mx-auto max-w-2xl px-4 py-8">
            <form class="space-y-4 rounded-lg bg-white p-6 shadow" @submit.prevent="form.post(route('admin.profile.update'), { forceFormData: true })">
                <img v-if="logoUrl" :src="logoUrl" class="h-20 object-contain" alt="Logo" />
                <input type="file" accept="image/png,image/jpeg,image/webp" aria-label="School logo" @change="form.logo = $event.target.files[0]" />
                <label for="school-board" class="block text-sm font-semibold">Education board</label>
                <select v-searchable id="school-board" v-model="form.board_id" class="w-full rounded border-gray-300"><option value="">Select board</option><option v-for="board in boards" :key="board.id" :value="board.id">{{ board.name }}</option></select>
                <input v-model="form.name" class="w-full rounded border-gray-300" placeholder="Institute name" />
                <input v-model="form.owner_name" class="w-full rounded border-gray-300" placeholder="Owner name" />
                <input v-model="form.phone" class="w-full rounded border-gray-300" placeholder="Phone" />
                <input v-model="form.city" class="w-full rounded border-gray-300" placeholder="City" />
                <textarea v-model="form.address" class="w-full rounded border-gray-300" placeholder="Address" rows="3" />
                <InputError v-for="(error, key) in form.errors" :key="key" :message="error" />
                <button class="rounded bg-indigo-600 px-4 py-2 text-white" :disabled="form.processing">Save</button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
