<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({ teachers: Array });

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    allowed_grades: [9, 10],
    allowed_categories: ['exercise'],
});
</script>

<template>
    <Head title="Teachers" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Manage Teachers</h2></template>
        <div class="mx-auto max-w-5xl px-4 py-8">
            <form class="mb-8 grid gap-4 rounded-lg bg-white p-6 shadow md:grid-cols-2" @submit.prevent="form.post(route('admin.teachers.store'), { onSuccess: () => form.reset() })">
                <input v-model="form.name" placeholder="Name" class="rounded border-gray-300" required />
                <input v-model="form.email" placeholder="Email" class="rounded border-gray-300" />
                <input v-model="form.phone" placeholder="Phone" class="rounded border-gray-300" />
                <input v-model="form.password" type="password" placeholder="Password" class="rounded border-gray-300" required />
                <button class="md:col-span-2 rounded bg-indigo-600 px-4 py-2 text-white">Add Teacher</button>
            </form>
            <ul class="divide-y rounded-lg bg-white shadow">
                <li v-for="t in teachers" :key="t.id" class="flex justify-between px-4 py-3">
                    <div>
                        <p class="font-medium">{{ t.name }}</p>
                        <p class="text-sm text-gray-500">{{ t.email || t.phone }}</p>
                    </div>
                    <span class="text-xs text-green-600">{{ t.is_active ? 'Active' : 'Inactive' }}</span>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
