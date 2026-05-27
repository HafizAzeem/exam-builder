<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ tags: Object, boards: Array });
</script>

<template>
    <Head title="Past Papers" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Past Papers Repository</h2></template>
        <div class="mx-auto max-w-5xl px-4 py-8">
            <div class="space-y-3">
                <div v-for="tag in tags.data" :key="tag.id" class="flex items-center justify-between rounded-lg bg-white p-4 shadow">
                    <div>
                        <p class="font-medium">{{ tag.board_name }} — {{ tag.year }}</p>
                        <p class="text-sm text-gray-600">{{ tag.question?.text_en }}</p>
                    </div>
                    <Link :href="route('past-papers.print', tag.question_id)" class="text-indigo-600 hover:underline">Quick Print</Link>
                </div>
                <p v-if="!tags.data?.length" class="text-center text-gray-500">No past papers in database yet.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
