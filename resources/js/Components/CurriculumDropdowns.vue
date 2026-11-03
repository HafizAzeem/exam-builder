<script setup>
import { computed } from 'vue';
import SearchableSelect from './SearchableSelect.vue';
import { gradeOptions, subjectOptions, chapterOptions, changeGrade, changeSubject } from '@/Support/curriculumSelection.mjs';
const props = defineProps({ modelValue: { type: Object, required: true }, chapters: { type: Array, default: () => [] } });
const emit = defineEmits(['update:modelValue']);
const grades = computed(() => gradeOptions(props.chapters));
const subjects = computed(() => subjectOptions(props.chapters, props.modelValue.grade_id));
const chapters = computed(() => chapterOptions(props.chapters, props.modelValue.subject_id));
</script>
<template>
    <div class="grid min-w-0 gap-3 sm:grid-cols-3">
        <SearchableSelect label="Class" :options="grades" :model-value="modelValue.grade_id" placeholder="Select class" @update:model-value="emit('update:modelValue', changeGrade(modelValue, $event))" />
        <SearchableSelect label="Subject" :options="subjects" :model-value="modelValue.subject_id" :disabled="!modelValue.grade_id" placeholder="Select subject" @update:model-value="emit('update:modelValue', changeSubject(modelValue, $event))" />
        <SearchableSelect label="Chapter" :options="chapters" :model-value="modelValue.chapter_id" :disabled="!modelValue.subject_id" placeholder="Select chapter" @update:model-value="emit('update:modelValue', { ...modelValue, chapter_id: $event })" />
    </div>
</template>
