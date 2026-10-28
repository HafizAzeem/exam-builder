<script setup>
import { ref, computed, inject } from 'vue';
import DiagramArt from './DiagramArt.vue';
import DiagramPicker from './DiagramPicker.vue';
import { diagramItems, appendDiagrams, removeDiagram, moveDiagram, MAX_DIAGRAMS } from '@/Support/diagramAttachments.mjs';
const props = defineProps({ diagram: [Object, Array], imagePath: String, editable: Boolean });
const emit = defineEmits(['update']);
const picking = ref(false);
const pickerHost = ref('body');
const upload = ref(null);
const uploading = ref(false);
const error = ref('');
const reportUpload = inject('paperDiagramUpload', () => {});
const items = computed(() => diagramItems(props.diagram, props.imagePath));
const width = diagram => Math.max(60, Math.min(500, Number(diagram.width) || 240));
function openPicker(event) {
    pickerHost.value = event.currentTarget.closest('dialog') || document.body;
    error.value = '';
    picking.value = true;
}
function choose(value) {
    try { emit('update', appendDiagrams(items.value, [value])); picking.value = false; }
    catch (failure) { error.value = failure.message; picking.value = false; }
}
function change(index, fields) {
    emit('update', items.value.map((item, position) => position === index ? { ...item, ...fields } : item));
}
async function imageSnapshot(file) {
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) throw new Error('Choose PNG, JPG or WebP images.');
    if (file.size > 5 * 1024 * 1024) throw new Error(`${file.name} exceeds the 5 MB upload limit.`);
    const url = URL.createObjectURL(file);
    try {
        const image = new Image();
        await new Promise((resolve, reject) => { image.onload = resolve; image.onerror = () => reject(new Error(`Could not read ${file.name}.`)); image.src = url; });
        if (image.width > 6000 || image.height > 6000) throw new Error(`${file.name} exceeds 6000 pixels. Please resize it first.`);
        const scale = Math.min(1, 1600 / Math.max(image.width, image.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.width * scale));
        canvas.height = Math.max(1, Math.round(image.height * scale));
        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
        return { title: file.name, image_data: canvas.toDataURL('image/webp', 0.9), width: 240 };
    } finally { URL.revokeObjectURL(url); }
}
async function uploadImages(event) {
    const files = [...(event.target.files || [])];
    if (!files.length) return;
    uploading.value = true;
    reportUpload(true);
    error.value = '';
    try {
        if (items.value.length + files.length > MAX_DIAGRAMS) throw new Error(`Attach up to ${MAX_DIAGRAMS} diagrams here.`);
        const additions = [];
        for (const file of files) additions.push(await imageSnapshot(file));
        const next = appendDiagrams(items.value, additions);
        if (JSON.stringify(next).length > 6000000) throw new Error('These images are too large together. Please use smaller images.');
        emit('update', next);
    } catch (failure) { error.value = failure.message || 'Could not upload images.'; }
    finally { uploading.value = false; reportUpload(false); event.target.value = ''; }
}
</script>
<template>
    <div v-if="items.length || editable" class="diagram-attachment">
        <div class="flex flex-wrap items-start gap-3">
            <div v-for="(diagram, index) in items" :key="index" class="diagram-item" :style="{ width: width(diagram) + 'px', maxWidth: '100%', margin: diagram.align === 'left' ? '0' : '0 auto' }">
                <DiagramArt :diagram="diagram" />
                <div v-if="editable" class="diagram-controls my-2 flex flex-wrap items-center gap-2 text-xs" data-no-print>
                    <span class="font-semibold">{{ index + 1 }}</span>
                    <label>Width <input type="number" :value="width(diagram)" min="60" max="500" :disabled="uploading" class="w-16 rounded border-slate-300 px-1 py-0 text-xs" @change="change(index, { width: Math.max(60, Math.min(500, Number($event.target.value) || 240)) })" /></label>
                    <select :value="diagram.align || 'center'" :aria-label="`Diagram ${index + 1} alignment`" :disabled="uploading" class="rounded border-slate-300 py-0 text-xs" @change="change(index, { align: $event.target.value })"><option value="center">Center</option><option value="left">Left</option></select>
                    <button type="button" :disabled="uploading || index === 0" :aria-label="`Move diagram ${index + 1} earlier`" class="disabled:opacity-30" @click="emit('update', moveDiagram(items, index, index - 1))">←</button>
                    <button type="button" :disabled="uploading || index === items.length - 1" :aria-label="`Move diagram ${index + 1} later`" class="disabled:opacity-30" @click="emit('update', moveDiagram(items, index, index + 1))">→</button>
                    <button type="button" :disabled="uploading" class="text-red-700" @click="emit('update', removeDiagram(items, index))">Remove</button>
                </div>
            </div>
        </div>
        <div v-if="editable" class="diagram-controls my-2 flex flex-wrap items-center gap-2 text-xs" data-no-print>
            <button type="button" :disabled="uploading || items.length >= MAX_DIAGRAMS" class="rounded border border-teal-200 bg-white px-2 py-1 text-teal-800 disabled:opacity-50" @click="openPicker">{{ items.length ? 'Add another diagram' : 'Add diagram' }}</button>
            <button type="button" :disabled="uploading || items.length >= MAX_DIAGRAMS" class="rounded border border-teal-200 bg-white px-2 py-1 text-teal-800 disabled:opacity-50" @click="upload.click()">{{ uploading ? 'Preparing images…' : 'Upload images' }}</button>
            <input ref="upload" type="file" accept="image/png,image/jpeg,image/webp" multiple class="hidden" aria-label="Upload diagram images" @change="uploadImages" />
            <span class="text-slate-500">{{ items.length }}/{{ MAX_DIAGRAMS }} · Select several images together</span>
            <p v-if="error" role="alert" class="w-full text-red-700">{{ error }}</p>
        </div>
        <Teleport :to="pickerHost"><DiagramPicker v-if="picking" @close="picking = false" @select="choose" /></Teleport>
    </div>
</template>
<style scoped>
.diagram-attachment{margin-top:4px}.diagram-item{break-inside:avoid;page-break-inside:avoid}@media print{.diagram-controls{display:none!important}}
</style>
