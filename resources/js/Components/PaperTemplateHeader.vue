<script setup>
import { computed } from 'vue';
import { TEMPLATE_FIELDS } from '@/utils/paperTemplates';
const props = defineProps({
    template: String, name: String, address: String, logo: String, meta: Object,
    details: { type: Object, default: () => ({}) }, editable: Boolean,
});
const emit = defineEmits(['edit']);
const urdu = computed(() => props.template === 'urdu');
const visibleDetails = computed(() => Object.fromEntries(
    TEMPLATE_FIELDS.filter(field => field.templates.includes(props.template))
        .map(field => [field.key, props.details[field.key]]),
));
const fields = computed(() => [
    ['class', urdu.value ? 'جماعت' : 'Class', props.meta.class],
    ['subject', urdu.value ? 'مضمون' : 'Subject', props.meta.subject],
    ['paper_time', urdu.value ? 'وقت' : 'Time allowed', props.meta.time],
    ['marks', urdu.value ? 'کل نمبر' : 'Maximum marks', props.meta.marks],
]);
function edit(event, key) {
    if (props.editable) emit('edit', key, event.target.innerText.trim());
}
</script>

<template>
    <div class="exam-header" :class="`exam-header--${template}`" :dir="urdu ? 'rtl' : 'ltr'" :lang="urdu ? 'ur' : 'en'">
        <div class="exam-brand">
            <img v-if="logo" :src="logo" alt="Institute logo" />
            <div class="exam-brand-text">
                <h1 :contenteditable="editable" @blur="edit($event, 'institute_name')">{{ name }}</h1>
                <p v-if="address || editable" :contenteditable="editable" @blur="edit($event, 'institute_address')">{{ address }}</p>
                <p v-if="visibleDetails.board || visibleDetails.session">{{ [visibleDetails.board, visibleDetails.session].filter(Boolean).join(' · ') }}</p>
                <p v-if="template === 'academy' && (visibleDetails.campus || visibleDetails.batch)">{{ [visibleDetails.campus, visibleDetails.batch && `Batch: ${visibleDetails.batch}`].filter(Boolean).join(' · ') }}</p>
            </div>
        </div>
        <h2 :contenteditable="editable" @blur="edit($event, 'paper_type')">{{ meta.paperType }}</h2>
        <p v-if="template === 'academy' && (visibleDetails.test_number || visibleDetails.coverage)" class="exam-coverage">{{ [visibleDetails.test_number && `Test: ${visibleDetails.test_number}`, visibleDetails.coverage && `Coverage: ${visibleDetails.coverage}`].filter(Boolean).join(' · ') }}</p>
        <div class="exam-meta">
            <div v-for="[key, label, value] in fields" :key="key" class="exam-field">
                <strong>{{ label }}:</strong>
                <span :contenteditable="editable" @blur="edit($event, key)">{{ value }}</span>
            </div>
            <div v-if="visibleDetails.group" class="exam-field"><strong>Group:</strong> {{ visibleDetails.group }}</div>
            <div v-if="visibleDetails.paper_code" class="exam-field"><strong>Paper code:</strong> {{ visibleDetails.paper_code }}</div>
            <div v-if="template === 'federal' || template === 'entry'" class="exam-field"><strong>Version:</strong> {{ visibleDetails.version || '________' }}</div>
            <div v-if="visibleDetails.date" class="exam-field"><strong>{{ urdu ? 'تاریخ' : 'Date' }}:</strong> {{ visibleDetails.date }}</div>
        </div>
        <div class="exam-candidate">
            <span>{{ urdu ? 'نام' : 'Student name' }}: <i /></span>
            <span>{{ urdu ? 'رول نمبر' : 'Roll number' }}: <i /></span>
        </div>
        <div v-if="template === 'federal'" class="exam-candidate exam-signatures">
            <span>Candidate signature: <i /></span><span>Invigilator signature: <i /></span>
        </div>
    </div>
</template>

<style scoped>
.exam-header { border-bottom: 2px solid currentColor; padding-bottom: 10px; margin-bottom: 12px; overflow-wrap: anywhere; break-inside: avoid; }
.exam-brand { display: flex; align-items: center; justify-content: center; gap: 12px; }
.exam-brand img { width: 55px; height: 55px; object-fit: contain; flex-shrink: 0; }
.exam-brand-text { min-width: 0; text-align: center; }
h1 { font-size: var(--heading-font-size, 14pt); font-weight: 700; line-height: 1.4; margin: 0; }
p { margin: 4px 0; font-size: .85em; }
h2 { text-align: center; font-weight: 700; font-size: var(--heading-font-size, 12pt); margin: 12px 0; }
.exam-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 20px; }
.exam-field { display: flex; align-items: baseline; gap: 7px; min-width: 0; }
.exam-field span { min-width: 24px; }
.exam-candidate { display: flex; gap: 20px; margin-top: 12px; }
.exam-candidate > span { display: flex; align-items: baseline; flex: 1; gap: 6px; min-width: 0; }
i { display: inline-block; flex: 1; border-bottom: 1px solid currentColor; min-width: 20px; }
.exam-signatures { font-size: .8em; margin-top: 18px; }
.exam-header--academy .exam-brand { justify-content: start; }
.exam-header--academy .exam-brand-text { text-align: start; }
.exam-header--academy .exam-meta { border: 1px solid currentColor; padding: 10px; }
.exam-coverage { text-align: center; margin-bottom: 10px; }
.exam-header--urdu { font-family: 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', serif; line-height: 2; }
.exam-header--entry h2 { border-block: 1px solid currentColor; padding: 7px; }
@media print { [contenteditable] { outline: none !important; } }
</style>
