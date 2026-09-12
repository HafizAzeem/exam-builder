<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import { Head, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    stats: { type: Object, required: true },
    recent: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="AI Question Import" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">AI Question Import</h2>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('super-admin.ai-import.paste')" class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-indigo-700 shadow-sm hover:bg-indigo-50">
                        Paste Text
                    </Link>
                    <Link :href="route('super-admin.ai-import.create')">
                        <PrimaryButton>Upload Document</PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <StatCard title="Uploaded Files" :count="stats.total_uploaded" :href="route('super-admin.ai-import.history')" gradient="from-slate-600 to-slate-800" icon="folder" />
                    <StatCard title="Pending Reviews" :count="stats.pending_reviews" :href="route('super-admin.ai-import.history')" gradient="from-amber-500 to-orange-600" icon="clock" />
                    <StatCard title="Approved Questions" :count="stats.approved_questions" :href="route('super-admin.ai-import.history')" gradient="from-emerald-500 to-teal-600" icon="shield" />
                    <StatCard title="Rejected Questions" :count="stats.rejected_questions" :href="route('super-admin.ai-import.history')" gradient="from-rose-500 to-red-600" icon="document" />
                    <StatCard title="Imported Questions" :count="stats.imported_questions" :href="route('super-admin.ai-import.history')" gradient="from-indigo-500 to-blue-600" icon="archive" />
                    <StatCard title="AI Processing Jobs" :count="stats.processing_jobs" :href="route('super-admin.ai-import.history')" gradient="from-violet-500 to-purple-700" icon="settings" />
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent Imports</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">File</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Grade / Subject</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Found</th>
                                    <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                                    <th class="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="item in recent" :key="item.id">
                                    <td class="px-4 py-3 text-gray-900">{{ item.original_filename }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ item.grade?.label_en }} / {{ item.subject?.name_en }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium capitalize text-gray-700">
                                            {{ item.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 tabular-nums">{{ item.questions_found }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ new Date(item.created_at).toLocaleString() }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            :href="item.status === 'review' || item.status === 'completed'
                                                ? route('super-admin.ai-import.review', item.id)
                                                : route('super-admin.ai-import.show', item.id)"
                                            class="text-indigo-600 hover:text-indigo-800"
                                        >
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!recent.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No imports yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
