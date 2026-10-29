<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    enabled: { type: Boolean, default: true },
    hasKey: { type: Boolean, default: false },
    maskedKey: { type: String, default: null },
    apiKey: { type: String, default: '' },
    clearKey: { type: Boolean, default: false },
    model: { type: String, default: '' },
    modelLabel: { type: String, default: 'Model' },
    modelPresets: { type: Array, default: () => [] },
    extraModel: { type: String, default: null },
    extraModelLabel: { type: String, default: null },
    extraModelPresets: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
    'update:enabled',
    'update:apiKey',
    'update:clearKey',
    'update:model',
    'update:extraModel',
]);

const CUSTOM = '__custom__';

const matchPreset = (value, presets) => {
    const found = (presets || []).find((p) => p.id === value);
    return found ? found.id : CUSTOM;
};

const modelPreset = ref(matchPreset(props.model, props.modelPresets));
const extraPreset = ref(matchPreset(props.extraModel, props.extraModelPresets));

watch(() => props.model, (value) => {
    modelPreset.value = matchPreset(value, props.modelPresets);
});
watch(modelPreset, (value) => {
    if (value !== CUSTOM) {
        emit('update:model', value);
    }
});

watch(() => props.extraModel, (value) => {
    extraPreset.value = matchPreset(value, props.extraModelPresets);
});
watch(extraPreset, (value) => {
    if (value !== CUSTOM && props.extraModelLabel) {
        emit('update:extraModel', value);
    }
});

const status = computed(() => {
    if (! props.enabled) return 'Disabled';
    return props.hasKey ? 'Configured' : 'Missing key';
});

const statusClass = computed(() => {
    if (! props.enabled) return 'border-slate-200 bg-slate-50 text-slate-600';
    return props.hasKey
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : 'border-amber-200 bg-amber-50 text-amber-800';
});
</script>

<template>
    <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ title }}</h3>
                <p class="mt-1 text-xs text-gray-500">Leave the API key blank to keep the saved key after refresh/save.</p>
            </div>
            <div class="rounded-md border px-3 py-1 text-xs font-medium" :class="statusClass">
                {{ status }}
            </div>
        </div>

        <label class="flex items-center gap-2">
            <Checkbox :checked="enabled" @update:checked="emit('update:enabled', $event)" />
            <span class="text-sm text-gray-700">Enable {{ title }}</span>
        </label>
        <InputError :message="errors.enabled" />

        <div v-if="maskedKey" class="font-mono text-xs text-gray-500">Saved key: {{ maskedKey }}</div>

        <div>
            <InputLabel :value="title + ' API Key'" />
            <TextInput
                :model-value="apiKey"
                type="password"
                class="mt-1 block w-full"
                autocomplete="new-password"
                placeholder="••••••••"
                @update:model-value="emit('update:apiKey', $event)"
            />
            <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                <Checkbox :checked="clearKey" @update:checked="emit('update:clearKey', $event)" />
                Clear {{ title }} key
            </label>
            <InputError :message="errors.apiKey" class="mt-1" />
        </div>

        <div v-if="modelPresets.length">
            <InputLabel :value="modelLabel + ' preset'" />
            <select v-searchable v-model="modelPreset" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                <option v-for="preset in modelPresets" :key="preset.id" :value="preset.id">
                    {{ preset.label }}
                </option>
                <option :value="CUSTOM">Custom model ID…</option>
            </select>
            <TextInput
                :model-value="model"
                class="mt-2 block w-full font-mono text-sm"
                @update:model-value="emit('update:model', $event)"
            />
            <InputError :message="errors.model" class="mt-1" />
        </div>

        <div v-if="extraModelLabel">
            <InputLabel :value="extraModelLabel + ' preset'" />
            <select v-searchable v-model="extraPreset" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                <option v-for="preset in extraModelPresets" :key="preset.id" :value="preset.id">
                    {{ preset.label }}
                </option>
                <option :value="CUSTOM">Custom model ID…</option>
            </select>
            <TextInput
                :model-value="extraModel"
                class="mt-2 block w-full font-mono text-sm"
                @update:model-value="emit('update:extraModel', $event)"
            />
            <InputError :message="errors.extraModel" class="mt-1" />
        </div>
    </section>
</template>
