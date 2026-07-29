<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ sessions: Object });

const parseUserAgent = (ua) => {
    if (!ua) return 'Unknown';
    if (/Mobile|Android|iPhone/i.test(ua)) return 'Mobile';
    if (/Windows/i.test(ua)) return 'Windows PC';
    if (/Mac/i.test(ua)) return 'Mac';
    if (/Linux/i.test(ua)) return 'Linux';
    return 'Browser';
};
</script>

<template>
    <Head title="Login History" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Login History</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">User</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Device</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">IP Address</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Logged In</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Logged Out</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="session in sessions.data" :key="session.id">
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium text-gray-900">{{ session.user?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ session.user?.email }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="session.success ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                    >
                                        {{ session.success ? 'Success' : 'Failed' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ parseUserAgent(session.user_agent) }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ session.ip_address }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ new Date(session.logged_in_at).toLocaleString() }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ session.logged_out_at ? new Date(session.logged_out_at).toLocaleString() : '—' }}
                                </td>
                            </tr>
                            <tr v-if="!sessions.data.length">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    No login records yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="sessions.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
                    <Link
                        v-for="link in sessions.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        class="rounded border px-3 py-1 text-sm"
                        :class="link.active ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600'"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
