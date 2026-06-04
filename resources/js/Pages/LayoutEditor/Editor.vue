<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperPreview from '@/Components/PaperPreview.vue';
import { buildPaperContentFromPreview, clonePaperContent } from '@/utils/paperContent';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    savedPaper: Object,
    preview: Object,
    headerTemplates: Array,
    pdfUrl: String,
});

const buildWatermarkText = (inst) => {
    if (!inst) return '';
    const name = (inst.name || '').toUpperCase();
    const location = [inst.address, inst.city].filter(Boolean).join(', ');
    const lines = [
        name,
        location ? location.toUpperCase() : null,
        inst.phone ? `PH: ${inst.phone}` : null,
    ].filter(Boolean);
    return lines.join('\n');
};

const layout = ref({ ...props.preview.layout });

if (layout.value.enable_watermark && !layout.value.watermark_text) {
    layout.value.watermark_text = props.preview.layout?.watermark_text
        || buildWatermarkText(props.preview.institution);
}

const initialContent = () =>
    clonePaperContent(
        layout.value.paper_content
            ?? buildPaperContentFromPreview(props.preview, props.savedPaper.title),
    );

const paperContent = ref(initialContent());
const editDraft = ref(null);
const editingPaper = ref(false);
const savingPaper = ref(false);

const activePaperContent = computed(() => (editingPaper.value ? editDraft.value : paperContent.value));

const form = useForm({ layout_snapshot: layout.value });

let layoutSaveTimer = null;

watch(
    layout,
    (val) => {
        form.layout_snapshot = { ...val, paper_content: paperContent.value };
        clearTimeout(layoutSaveTimer);
        layoutSaveTimer = setTimeout(() => {
            if (!editingPaper.value && !savingPaper.value) {
                form.patch(route('editor.update', props.savedPaper.id), { preserveScroll: true, preserveState: true });
            }
        }, 600);
    },
    { deep: true },
);

watch(
    () => layout.value.enable_watermark,
    (enabled) => {
        if (enabled && !layout.value.watermark_text) {
            layout.value.watermark_text = buildWatermarkText(props.preview.institution);
        }
    },
);

const onContentUpdate = (content) => {
    if (editingPaper.value) {
        editDraft.value = content;
    }
};

const startEditPaper = () => {
    editDraft.value = clonePaperContent(paperContent.value);
    editingPaper.value = true;
};

const cancelEditPaper = () => {
    editDraft.value = null;
    editingPaper.value = false;
};

const savePaperContent = () => {
    if (!editDraft.value) return;

    savingPaper.value = true;
    paperContent.value = clonePaperContent(editDraft.value);
    layout.value.paper_content = paperContent.value;
    form.layout_snapshot = { ...layout.value, paper_content: paperContent.value };

    form.patch(route('editor.update', props.savedPaper.id), {
        preserveScroll: true,
        onFinish: () => {
            savingPaper.value = false;
            editingPaper.value = false;
            editDraft.value = null;
        },
    });
};

const printPaper = () => window.open(route('editor.print', props.savedPaper.id), '_blank');
const requestPdf = () => form.post(route('editor.pdf', props.savedPaper.id), { preserveScroll: true });
</script>

<template>
    <Head :title="`Edit: ${savedPaper.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold text-gray-800">{{ savedPaper.title }}</h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="!editingPaper"
                        type="button"
                        class="rounded bg-amber-600 px-4 py-2 text-sm text-white hover:bg-amber-700"
                        @click="startEditPaper"
                    >
                        Edit Paper
                    </button>
                    <template v-else>
                        <button
                            type="button"
                            class="rounded bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700 disabled:opacity-60"
                            :disabled="savingPaper"
                            @click="savePaperContent"
                        >
                            {{ savingPaper ? 'Saving…' : 'Save Paper' }}
                        </button>
                        <button
                            type="button"
                            class="rounded border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-50"
                            :disabled="savingPaper"
                            @click="cancelEditPaper"
                        >
                            Cancel
                        </button>
                    </template>
                    <button type="button" class="rounded bg-green-600 px-4 py-2 text-sm text-white" @click="printPaper">
                        Print
                    </button>
                    <button type="button" class="rounded bg-indigo-600 px-4 py-2 text-sm text-white" @click="requestPdf">
                        Generate PDF
                    </button>
                    <a
                        v-if="pdfUrl"
                        :href="pdfUrl"
                        class="rounded bg-gray-900 px-4 py-2 text-sm text-white"
                        target="_blank"
                        rel="noopener"
                    >Download PDF</a>
                    <Link :href="route('dashboard')" class="rounded bg-gray-200 px-4 py-2 text-sm">Back</Link>
                </div>
            </div>
        </template>

        <p
            v-if="editingPaper"
            class="mx-auto mb-2 max-w-7xl px-4 text-sm text-green-700"
        >
            Edit mode: click any English or Urdu text on the paper. Green dashed border marks the question area. Save when finished.
        </p>

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
                    <input v-model="layout.dual_medium" type="checkbox" />
                    Dual Medium (English + Urdu)
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="layout.dual_column" type="checkbox" />
                    Dual Column (use Landscape)
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm">Orientation</label>
                        <select v-model="layout.orientation" class="mt-1 w-full rounded border-gray-300">
                            <option value="portrait">Portrait</option>
                            <option value="landscape">Landscape</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm">Scale (%)</label>
                        <input v-model.number="layout.scale" type="number" min="50" max="120" class="mt-1 w-full rounded border" />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium">Page margins (mm)</label>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <input v-model.number="layout.margins.top" type="number" min="0" class="rounded border" placeholder="Top" />
                        <input v-model.number="layout.margins.right" type="number" min="0" class="rounded border" placeholder="Right" />
                        <input v-model.number="layout.margins.bottom" type="number" min="0" class="rounded border" placeholder="Bottom" />
                        <input v-model.number="layout.margins.left" type="number" min="0" class="rounded border" placeholder="Left" />
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="layout.show_past_paper_tags" type="checkbox" />
                    Show past paper references
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
                    <div v-if="layout.enable_watermark" class="mt-2 grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-gray-600">Opacity (0.05–0.30)</label>
                            <input v-model.number="layout.watermark_opacity" type="number" min="0.05" max="0.3" step="0.01" class="mt-1 w-full rounded border" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Angle (0–60)</label>
                            <input v-model.number="layout.watermark_angle" type="number" min="0" max="60" class="mt-1 w-full rounded border" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <PaperPreview
                    :title="savedPaper.title"
                    :layout="layout"
                    :institution="preview.institution"
                    :exam-meta="preview.exam_meta"
                    :settings="preview.settings"
                    :sections="preview.sections"
                    :paper-content="activePaperContent"
                    :editable="editingPaper"
                    :dual-medium="layout.dual_medium"
                    :omr-rows="preview.omr_rows"
                    :answer-key="preview.answer_key"
                    @update:paper-content="onContentUpdate"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
