<script setup>
defineProps({
    value: { type: Number, default: 0 },
    label: { type: String, default: '' },
});
</script>

<template>
    <div>
        <div v-if="label" class="mb-1 flex justify-between text-sm text-gray-600">
            <span>{{ label }}</span>
            <span>{{ Math.min(100, Math.max(0, value)) }}%</span>
        </div>
        <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
            <div
                class="h-full rounded-full bg-indigo-600 transition-all duration-500"
                :style="{ width: `${Math.min(100, Math.max(0, value))}%` }"
            />
        </div>
    </div>
</template>
