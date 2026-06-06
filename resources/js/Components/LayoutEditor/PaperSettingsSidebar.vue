<script setup>
const layout = defineModel('layout', { type: Object, required: true });
const settings = defineModel('settings', { type: Object, required: true });

defineProps({
    headerTemplates: { type: Array, default: () => [1, 2, 3, 4, 5, 6, 7] },
});

const onWatermarkTypeChange = () => {
    const wantsWatermark = layout.value.watermark_type === 'text';
    if (layout.value.enable_watermark !== wantsWatermark) {
        layout.value.enable_watermark = wantsWatermark;
    }
    if (settings.value.enable_watermark !== wantsWatermark) {
        settings.value.enable_watermark = wantsWatermark;
    }
};
</script>

<template>
    <div class="toolbar space-y-3 rounded-lg bg-white p-3 shadow">
        <h3 class="font-semibold text-gray-800">Paper settings</h3>

        <div>
            <label class="text-sm text-gray-700">Header template</label>
            <select v-model.number="layout.header_template" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                <option v-for="t in headerTemplates" :key="t" :value="t">Template {{ t }}</option>
            </select>
        </div>

        <div>
            <label class="text-sm text-gray-700">Font family</label>
            <select v-model="layout.font_family" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                <option value="Arial">Arial</option>
                <option value="Times New Roman">Times New Roman</option>
                <option value="Jameel Noori Nastaleeq">Jameel Noori Nastaleeq</option>
                <option value="Noto Nastaliq Urdu">Noto Nastaliq Urdu</option>
            </select>
        </div>

        <div class="pair-field-grid">
            <label class="pair-field-label">Heading pt</label>
            <label class="pair-field-label">Body pt</label>
            <input
                v-model.number="layout.heading_font_size"
                type="number"
                min="8"
                max="24"
                class="pair-field-input"
            />
            <input
                v-model.number="layout.font_size"
                type="number"
                min="8"
                max="18"
                class="pair-field-input"
            />
        </div>

        <div>
            <label class="text-sm text-gray-700">Line height</label>
            <input
                v-model.number="layout.line_height"
                type="number"
                step="0.1"
                min="1"
                max="3"
                class="mt-1 w-full rounded-md border-gray-300 text-sm"
            />
        </div>

        <div>
            <label class="text-sm text-gray-700">Text weight</label>
            <select v-model="layout.font_weight" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                <option value="normal">Normal</option>
                <option value="bold">Bold</option>
            </select>
        </div>

        <div>
            <label class="text-sm text-gray-700">Font colour</label>
            <input v-model="layout.font_color" type="color" class="mt-1 h-10 w-full rounded-md border-gray-300" />
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="layout.dual_column" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
            Two columns (landscape)
        </label>

        <div class="pair-field-grid">
            <label class="pair-field-label">Orientation</label>
            <label class="pair-field-label">Scale %</label>
            <select v-model="layout.orientation" class="pair-field-input">
                <option value="portrait">Portrait</option>
                <option value="landscape">Landscape</option>
            </select>
            <input
                v-model.number="layout.scale"
                type="number"
                min="50"
                max="120"
                class="pair-field-input"
            />
        </div>

        <div>
            <label class="text-sm font-medium text-gray-700">Page margins (mm)</label>
            <div class="pair-field-grid mt-1">
                <label class="pair-field-label">Top</label>
                <label class="pair-field-label">Right</label>
                <input v-model.number="layout.margins.top" type="number" min="0" max="50" class="pair-field-input" />
                <input v-model.number="layout.margins.right" type="number" min="0" max="50" class="pair-field-input" />
                <label class="pair-field-label">Bottom</label>
                <label class="pair-field-label">Left</label>
                <input v-model.number="layout.margins.bottom" type="number" min="0" max="50" class="pair-field-input" />
                <input v-model.number="layout.margins.left" type="number" min="0" max="50" class="pair-field-input" />
            </div>
        </div>

        <div>
            <label class="text-sm text-gray-700">Watermark</label>
            <select
                v-model="layout.watermark_type"
                class="mt-1 w-full rounded-md border-gray-300 text-sm"
                @change="onWatermarkTypeChange"
            >
                <option value="none">Off</option>
                <option value="text">Text watermark</option>
            </select>
            <template v-if="layout.watermark_type === 'text'">
                <input
                    v-model="layout.watermark_text"
                    class="mt-2 w-full rounded-md border-gray-300 text-sm"
                    placeholder="Watermark text"
                />
                <div class="mt-2 space-y-2">
                    <div>
                        <label class="text-xs text-gray-500">Size (pt)</label>
                        <input
                            v-model.number="layout.watermark_size"
                            type="number"
                            min="10"
                            max="72"
                            class="mt-0.5 w-full rounded-md border-gray-300 px-2 py-1.5 text-sm"
                        />
                    </div>
                    <div class="pair-field-grid">
                        <label class="pair-field-label">Opacity</label>
                        <label class="pair-field-label">Angle</label>
                        <input
                            v-model.number="layout.watermark_opacity"
                            type="number"
                            min="0.05"
                            max="0.3"
                            step="0.01"
                            class="pair-field-input"
                        />
                        <input
                            v-model.number="layout.watermark_angle"
                            type="number"
                            min="0"
                            max="60"
                            class="pair-field-input"
                        />
                    </div>
                </div>
            </template>
        </div>

        <div class="space-y-2 border-t border-gray-200 pt-3">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Includes</p>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="settings.enable_omr" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                OMR bubble sheet
            </label>
            <div v-if="settings.enable_omr" class="ms-6">
                <label class="text-xs text-gray-600">OMR layout</label>
                <select v-model.number="layout.omr_columns" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    <option :value="1">1 column</option>
                    <option :value="2">2 columns</option>
                    <option :value="3">3 columns</option>
                    <option :value="4">4 columns</option>
                    <option :value="5">5 columns</option>
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="settings.enable_answer_key" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                Teacher answer key
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="settings.show_past_paper_tags" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                Past paper board &amp; year
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="layout.show_note" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                Section notes (e.g. Answer any 5)
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="layout.dual_medium" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                Dual medium (English + Urdu)
            </label>
        </div>
    </div>
</template>

<style scoped>
.pair-field-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.25rem 0.5rem;
    align-items: center;
}

.pair-field-label {
    font-size: 0.75rem;
    line-height: 1.2;
    color: #4b5563;
    white-space: nowrap;
}

.pair-field-input {
    width: 100%;
    min-width: 0;
    border-radius: 0.375rem;
    border: 1px solid #d1d5db;
    padding: 0.375rem 0.5rem;
    font-size: 0.875rem;
}
</style>
