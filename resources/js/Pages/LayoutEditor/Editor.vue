<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperPreview from '@/Components/PaperPreview.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    savedPaper: Object,
    preview: Object,
    headerTemplates: Array,
});

const layout = ref({ ...props.preview.layout });

const form = useForm({ layout_snapshot: layout.value });

watch(
    layout,
    (val) => {
        form.layout_snapshot = val;
        form.patch(route('editor.update', props.savedPaper.id), { preserveScroll: true, preserveState: true });
    },
    { deep: true },
);

const printPaper = () => window.open(route('editor.print', props.savedPaper.id), '_blank');
</script>

<template>
    <Head :title="`Edit: ${savedPaper.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">{{ savedPaper.title }}</h2>
                <div class="flex gap-2">
                    <button class="rounded bg-green-600 px-4 py-2 text-sm text-white" @click="printPaper">Print</button>
                    <Link :href="route('dashboard')" class="rounded bg-gray-200 px-4 py-2 text-sm">Back</Link>
                </div>
            </div>
        </template>

        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 lg:grid-cols-3">
            <div class="toolbar space-y-4 rounded-lg bg-white p-4 shadow lg:col-span-1">
                <h3 class="font-semibold">Layout Controls</h3>

                <div>
                    <label class="text-sm">Header Template</label>
                    <select v-model="layout.header_template" class="mt-1 w-full rounded border-gray-300">
                        <option v-for="t in headerTemplates" :key="t" :value="t">Template {{ t }}</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm">Font Family</label>
                    <select v-model="layout.font_family" class="mt-1 w-full rounded border-gray-300">
                        <option>Jameel Noori Nastaleeq</option>
                        <option>Noto Nastaliq Urdu</option>
                        <option>Arial</option>
                        <option>Times New Roman</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm">Font Size (pt)</label>
                    <input v-model.number="layout.font_size" type="range" min="10" max="18" class="w-full" />
                    <input v-model.number="layout.font_size" type="number" class="mt-1 w-20 rounded border" />
                </div>

                <div>
                    <label class="text-sm">Line Height</label>
                    <input v-model.number="layout.line_height" type="number" step="0.1" min="1" max="3" class="w-full rounded border" />
                </div>

                <div>
                    <label class="text-sm">Font Colour</label>
                    <input v-model="layout.font_color" type="color" class="h-10 w-full" />
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="layout.dual_column" type="checkbox" />
                    Dual Column (use Landscape)
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="layout.enable_omr" type="checkbox" />
                    OMR Sheet
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="layout.enable_answer_key" type="checkbox" />
                    Answer Key
                </label>

                <div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="layout.enable_watermark" type="checkbox" />
                        Watermark
                    </label>
                    <input v-if="layout.enable_watermark" v-model="layout.watermark_text" class="mt-2 w-full rounded border" placeholder="CONFIDENTIAL" />
                </div>
            </div>

            <div class="lg:col-span-2">
                <PaperPreview
                    :title="savedPaper.title"
                    :layout="layout"
                    :institution="preview.institution"
                    :sections="preview.sections"
                    :dual-medium="layout.dual_medium"
                    :omr-rows="preview.omr_rows"
                    :answer-key="preview.answer_key"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
