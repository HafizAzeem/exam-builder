<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, useId, watch } from 'vue';
const props = defineProps({ modelValue: [String, Number], options: { type: Array, default: () => [] }, label: String, placeholder: { type: String, default: 'Select an option' }, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);
const id = useId();
const root = ref(null);
const trigger = ref(null);
const input = ref(null);
const open = ref(false);
const search = ref('');
const active = ref(0);
const selected = computed(() => props.options.find(o => String(o.value) === String(props.modelValue)));
const filtered = computed(() => props.options.filter(o => o.label.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())));
watch(search, () => { active.value = 0; });
watch(() => props.disabled, value => { if (value) open.value = false; });
function close(restoreFocus = false) { open.value = false; if (restoreFocus) trigger.value?.focus(); }
async function toggle() {
    if (props.disabled) return;
    open.value = !open.value;
    search.value = '';
    active.value = 0;
    if (open.value) { await nextTick(); input.value?.focus(); }
}
function choose(value) { emit('update:modelValue', value); close(true); }
function key(event) {
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(true); }
    else if (event.target !== input.value && event.target !== trigger.value) return;
    else if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault();
        if (!open.value) { toggle(); return; }
        active.value = Math.max(0, Math.min(filtered.value.length - 1, active.value + (event.key === 'ArrowDown' ? 1 : -1)));
        nextTick(() => root.value?.querySelector('[data-highlighted="true"]')?.scrollIntoView({ block: 'nearest' }));
    } else if (event.key === 'Enter' && open.value) {
        event.preventDefault();
        if (filtered.value[active.value]) choose(filtered.value[active.value].value);
    }
}
function outside(event) { if (!root.value?.contains(event.target)) close(); }
onMounted(() => document.addEventListener('pointerdown', outside));
onUnmounted(() => document.removeEventListener('pointerdown', outside));
</script>
<template>
    <div ref="root" class="relative min-w-0" @keydown="key" @focusout="event => { if (!root?.contains(event.relatedTarget)) close(); }">
        <label :for="id" class="mb-1 block text-sm font-medium text-slate-700">{{ label }}</label>
        <button :id="id" ref="trigger" type="button" :disabled="disabled" :aria-expanded="open" :aria-controls="`${id}-options`" aria-haspopup="listbox" class="flex min-h-10 w-full items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" @click="toggle">
            <span class="truncate" :class="selected ? 'text-slate-900' : 'text-slate-500'">{{ selected?.label || placeholder }}</span><span aria-hidden="true" class="text-slate-400">▾</span>
        </button>
        <div v-if="open" class="absolute inset-x-0 top-full z-40 mt-1 min-w-48 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
            <input ref="input" v-model="search" :aria-label="`Search ${label}`" :aria-controls="`${id}-options`" :aria-activedescendant="filtered[active] ? `${id}-option-${active}` : undefined" role="combobox" aria-autocomplete="list" :aria-expanded="open" placeholder="Type to search…" class="mb-2 w-full rounded-lg border-slate-300 px-3 py-2 text-sm" />
            <button v-if="modelValue !== '' && modelValue != null" type="button" class="mb-1 w-full rounded-lg px-3 py-2 text-left text-xs text-slate-500 hover:bg-slate-50" @click="choose('')">Clear selection</button>
            <ul :id="`${id}-options`" role="listbox" :aria-label="label" class="max-h-56 overflow-y-auto">
                <li v-for="(option, index) in filtered" :id="`${id}-option-${index}`" :key="option.value" role="option" :aria-selected="String(option.value) === String(modelValue)" :data-highlighted="index === active" class="cursor-pointer rounded-lg px-3 py-2 text-sm" :class="index === active ? 'bg-blue-50 text-blue-800' : 'text-slate-700 hover:bg-slate-50'" @pointerdown.prevent @click="choose(option.value)" @pointermove="active = index">{{ option.label }}</li>
                <li v-if="!filtered.length" class="px-3 py-3 text-sm text-slate-500">No matching options</li>
            </ul>
        </div>
    </div>
</template>
