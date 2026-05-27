<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ logs: Object });
</script>

<template>
    <Head title="Activity Logs" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Activity Logs</h2></template>
        <div class="mx-auto max-w-5xl px-4 py-8">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">User</th>
                            <th class="px-4 py-2 text-left">Action</th>
                            <th class="px-4 py-2 text-left">IP</th>
                            <th class="px-4 py-2 text-left">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="log in logs.data" :key="log.id">
                            <td class="px-4 py-2">{{ log.user?.name }}</td>
                            <td class="px-4 py-2">{{ log.action }}</td>
                            <td class="px-4 py-2">{{ log.ip_address }}</td>
                            <td class="px-4 py-2">{{ new Date(log.created_at).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
