<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    papers: Object,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');

const applySearch = () => {
    router.get(route('dashboard'), { search: search.value }, { preserveState: true });
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Paper History</h2>
                <Link
                    :href="route('builder')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    + Create Paper
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 flex gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search papers..."
                        class="rounded-md border-gray-300 shadow-sm"
                        @keyup.enter="applySearch"
                    />
                    <button
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white"
                        @click="applySearch"
                    >
                        Search
                    </button>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Created By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="paper in papers.data" :key="paper.id">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ paper.title }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ paper.user?.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ new Date(paper.created_at).toLocaleString() }}
                                </td>
                                <td class="space-x-2 px-4 py-3 text-right text-sm">
                                    <Link :href="route('editor.show', paper.id)" class="text-indigo-600 hover:underline">Edit</Link>
                                    <Link :href="route('editor.print', paper.id)" class="text-green-600 hover:underline" target="_blank">Print</Link>
                                    <Link
                                        :href="route('papers.duplicate', paper.id)"
                                        method="post"
                                        as="button"
                                        class="text-gray-600 hover:underline"
                                    >
                                        Duplicate
                                    </Link>
                                    <Link
                                        :href="route('papers.destroy', paper.id)"
                                        method="delete"
                                        as="button"
                                        class="text-red-600 hover:underline"
                                    >
                                        Delete
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!papers.data.length">
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                    No papers yet. Create your first exam paper.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
