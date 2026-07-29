<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');

const applySearch = () => {
    router.get(route('paper-history.index'), { search: search.value }, { preserveState: true });
};
</script>

<template>
    <Head title="Paper History" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Paper Generation History</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search by teacher name..."
                        class="rounded-md border-gray-300 shadow-sm"
                        @keyup.enter="applySearch"
                    />
                    <button class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white" @click="applySearch">
                        Search
                    </button>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Date & Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Class</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Teacher</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="log in logs.data" :key="log.id">
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ new Date(log.created_at).toLocaleString() }}
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ log.title }}
                                    <span v-if="!log.exists" class="ml-1 text-xs text-gray-400">(removed)</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ log.class }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ log.subject }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ log.user?.name }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <Link
                                        v-if="log.exists"
                                        :href="route('editor.show', log.paper_id)"
                                        class="text-indigo-600 hover:underline"
                                    >
                                        Open
                                    </Link>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="!logs.data.length">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    No paper generation history yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="logs.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
                    <Link
                        v-for="link in logs.links"
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
