<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    collections: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const applyStatus = (status) => {
    router.get(route('super-admin.past-paper-collector.index'), { status: status || undefined }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Past Paper Collection History" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">AI Search History</h2>
                <Link :href="route('super-admin.past-paper-collector.create')">
                    <SecondaryButton>New Collection</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <select
                        class="rounded-md border-gray-300 text-sm"
                        :value="filters.status || ''"
                        @change="applyStatus($event.target.value)"
                    >
                        <option value="">All statuses</option>
                        <option value="queued">Queued</option>
                        <option value="searching">Searching</option>
                        <option value="review">Review</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Search</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Sources</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Questions</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-500">Tokens</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-500">Opened</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="c in collections.data" :key="c.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ c.board }} · {{ c.year }}</div>
                                    <div class="text-gray-500">{{ c.grade?.label_en }} / {{ c.subject?.name_en }} <span v-if="c.session">· {{ c.session }}</span></div>
                                </td>
                                <td class="px-4 py-3 capitalize">{{ c.status }}</td>
                                <td class="px-4 py-3">{{ c.successful_sources }}/{{ c.urls_visited }} (fail {{ c.failed_sources }})</td>
                                <td class="px-4 py-3">{{ c.questions_found }} <span class="text-xs text-gray-500">dup {{ c.duplicate_count }}</span></td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ c.input_tokens }}/{{ c.output_tokens }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('super-admin.past-paper-collector.show', c.id)" class="text-indigo-600">Open</Link>
                                </td>
                            </tr>
                            <tr v-if="!collections.data.length">
                                <td colspan="6" class="px-4 py-10 text-center text-gray-500">No collections yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="collections.links?.length > 3" class="flex flex-wrap gap-2">
                    <Link
                        v-for="link in collections.links"
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
