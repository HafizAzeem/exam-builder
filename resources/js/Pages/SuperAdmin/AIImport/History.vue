<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    imports: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const applyStatus = (status) => {
    router.get(route('super-admin.ai-import.history'), { status: status || undefined }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="AI Import History" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Import History</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <select
                        class="rounded-md border-gray-300 text-sm"
                        :value="filters.status || ''"
                        @change="applyStatus($event.target.value)"
                    >
                        <option value="">All statuses</option>
                        <option value="uploaded">Uploaded</option>
                        <option value="processing">Processing</option>
                        <option value="review">Review</option>
                        <option value="importing">Importing</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Upload Date</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">User</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Subject</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Grade</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Found</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Approved</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Rejected</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Imported</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Failed</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                    <th class="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="item in imports.data" :key="item.id">
                                    <td class="px-4 py-3 text-gray-700">{{ new Date(item.created_at).toLocaleString() }}</td>
                                    <td class="px-4 py-3">{{ item.user?.name }}</td>
                                    <td class="px-4 py-3">{{ item.subject?.name_en }}</td>
                                    <td class="px-4 py-3">{{ item.grade?.label_en }}</td>
                                    <td class="px-4 py-3 tabular-nums">{{ item.questions_found }}</td>
                                    <td class="px-4 py-3 tabular-nums">{{ item.approved_count }}</td>
                                    <td class="px-4 py-3 tabular-nums">{{ item.rejected_count }}</td>
                                    <td class="px-4 py-3 tabular-nums">{{ item.imported_count }}</td>
                                    <td class="px-4 py-3 tabular-nums">{{ item.failed_count }}</td>
                                    <td class="px-4 py-3 capitalize">{{ item.status }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            :href="['review', 'completed'].includes(item.status)
                                                ? route('super-admin.ai-import.review', item.id)
                                                : route('super-admin.ai-import.show', item.id)"
                                            class="text-indigo-600 hover:underline"
                                        >
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!imports.data.length">
                                    <td colspan="11" class="px-4 py-8 text-center text-gray-500">No import history.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="imports.links?.length > 3" class="flex flex-wrap gap-2">
                    <Link
                        v-for="link in imports.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded border px-3 py-1 text-sm"
                        :class="link.active ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-white text-gray-600'"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
