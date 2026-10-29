<script setup>
const settings = defineModel({ type: Object, required: true });
defineProps({ counts: { type: Object, default: () => ({}) } });
const sections = [
    { type: 'mcq', label: 'Multiple choice' }, { type: 'short', label: 'Short questions' },
    { type: 'long', label: 'Long questions' }, { type: 'fill', label: 'Fill in the blanks' },
    { type: 'truefalse', label: 'True / False' },
];
function set(type, field, value) {
    settings.value = { ...settings.value, [type]: { ...settings.value[type], [field]: value } };
}
</script>
<template>
    <section aria-label="Section settings" class="space-y-3 border-t border-slate-200 pt-4">
        <h4 class="font-semibold text-slate-900">Section settings</h4>
        <p class="text-xs text-slate-500">Applies to this paper. Adding more questions keeps these settings. Set questions to attempt to 0 to require all questions.</p>
        <details v-for="section in sections" :key="section.type" :open="!!counts[section.type]" class="rounded-lg border border-slate-200 p-3">
            <summary class="cursor-pointer text-sm font-medium text-slate-800">{{ section.label }} <span class="text-slate-500">({{ counts[section.type] || 0 }} selected)</span></summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="text-sm"><span class="block">Marks per question</span>
                    <input :aria-label="`${section.label}: marks per question`" type="number" min="1" max="100" :value="settings[section.type]?.marks ?? 1" class="mt-1 w-full rounded-lg border-slate-300"
                        @input="set(section.type, 'marks', Math.max(1, Math.min(100, Number($event.target.value) || 1)))" />
                </label>
                <template v-if="section.type === 'short' || section.type === 'long'">
                    <label class="text-sm"><span class="block">Questions to attempt</span>
                        <input :aria-label="`${section.label}: questions to attempt`" type="number" min="0" :max="counts[section.type] || undefined" :value="settings[section.type]?.choice_questions ?? 0" class="mt-1 w-full rounded-lg border-slate-300"
                            @input="set(section.type, 'choice_questions', Math.max(0, Math.min(counts[section.type] || Infinity, Number($event.target.value) || 0)))" />
                    </label>
                    <label class="text-sm"><span class="block">Answer lines</span>
                        <input :aria-label="`${section.label}: answer lines`" type="number" min="0" max="30" :value="settings[section.type]?.blank_lines ?? 0" class="mt-1 w-full rounded-lg border-slate-300"
                            @input="set(section.type, 'blank_lines', Math.max(0, Math.min(30, Number($event.target.value) || 0)))" />
                    </label>
                </template>
                <label v-if="section.type === 'short'" class="flex items-center gap-2 text-sm sm:col-span-3">
                    <input type="checkbox" :checked="settings.short?.questions_per_line === 2" @change="set('short', 'questions_per_line', $event.target.checked ? 2 : 1)" />Two questions per line
                </label>
                <label v-if="section.type === 'mcq'" class="flex items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" :checked="settings.mcq?.tabular_mcqs !== false" @change="set('mcq', 'tabular_mcqs', $event.target.checked)" />MCQ options in a table
                </label>
                <label v-if="section.type === 'long'" class="flex items-center gap-2 text-sm sm:col-span-3">
                    <input type="checkbox" :checked="settings.long?.show_parts !== false" @change="set('long', 'show_parts', $event.target.checked)" />Show question parts
                </label>
            </div>
        </details>
    </section>
</template>
