<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    current: { type: String, required: true },
    crumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="mb-6 space-y-3">
        <nav v-if="crumbs.length" class="flex flex-wrap items-center gap-1 text-sm text-gray-500">
            <template v-for="(crumb, index) in crumbs" :key="index">
                <span v-if="index > 0" class="text-gray-300">/</span>
                <Link v-if="crumb.href" :href="crumb.href" class="hover:text-indigo-700">{{ crumb.label }}</Link>
                <span v-else class="font-medium text-gray-700">{{ crumb.label }}</span>
            </template>
        </nav>
        <div class="flex flex-wrap gap-2">
            <Link
                :href="route('super-admin.curriculum.grades.index')"
                class="rounded-md px-3 py-1.5 text-sm font-medium"
                :class="current === 'classes' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'"
            >
                Classes
            </Link>
            <Link
                :href="route('super-admin.curriculum.boards.index')"
                class="rounded-md px-3 py-1.5 text-sm font-medium"
                :class="current === 'boards' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'"
            >
                Boards
            </Link>
        </div>
    </div>
</template>
