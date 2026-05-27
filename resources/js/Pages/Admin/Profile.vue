<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ institution: Object, logoUrl: String });

const form = useForm({
    name: props.institution.name,
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
                <input type="file" accept="image/*" @change="form.logo = $event.target.files[0]" />
                <input v-model="form.name" class="w-full rounded border-gray-300" placeholder="Institute name" />
                <input v-model="form.owner_name" class="w-full rounded border-gray-300" placeholder="Owner name" />
                <input v-model="form.phone" class="w-full rounded border-gray-300" placeholder="Phone" />
                <input v-model="form.city" class="w-full rounded border-gray-300" placeholder="City" />
                <textarea v-model="form.address" class="w-full rounded border-gray-300" placeholder="Address" rows="3" />
                <button class="rounded bg-indigo-600 px-4 py-2 text-white" :disabled="form.processing">Save</button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
