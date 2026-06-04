<script setup>
const layout = defineModel('layout', { type: Object, required: true });
const settings = defineModel('settings', { type: Object, required: true });

defineProps({
    headerTemplates: { type: Array, default: () => [1, 2, 3, 4, 5, 6, 7] },
});

const onWatermarkTypeChange = () => {
    layout.value.enable_watermark = layout.value.watermark_type === 'text';
};
</script>

<template>
    <div class="toolbar space-y-4 rounded-lg bg-white p-4 shadow">
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

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-sm text-gray-700">Heading size (pt)</label>
                <input
                    v-model.number="layout.heading_font_size"
                    type="number"
                    min="8"
                    max="24"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm"
                />
            </div>
            <div>
                <label class="text-sm text-gray-700">Body size (pt)</label>
                <input
                    v-model.number="layout.font_size"
                    type="number"
                    min="8"
                    max="18"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm"
                />
            </div>
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

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-sm text-gray-700">Orientation</label>
                <select v-model="layout.orientation" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    <option value="portrait">Portrait</option>
                    <option value="landscape">Landscape</option>
                </select>
            </div>
            <div>
                <label class="text-sm text-gray-700">Scale (%)</label>
                <input
                    v-model.number="layout.scale"
                    type="number"
                    min="50"
                    max="120"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm"
                />
            </div>
        </div>

        <div>
            <label class="text-sm font-medium text-gray-700">Margins (mm)</label>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <input v-model.number="layout.margins.top" type="number" min="0" class="rounded-md border-gray-300 text-sm" placeholder="Top" />
                <input v-model.number="layout.margins.right" type="number" min="0" class="rounded-md border-gray-300 text-sm" placeholder="Right" />
                <input v-model.number="layout.margins.bottom" type="number" min="0" class="rounded-md border-gray-300 text-sm" placeholder="Bottom" />
                <input v-model.number="layout.margins.left" type="number" min="0" class="rounded-md border-gray-300 text-sm" placeholder="Left" />
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
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-gray-500">Opacity</label>
                        <input
                            v-model.number="layout.watermark_opacity"
                            type="number"
                            min="0.05"
                            max="0.3"
                            step="0.01"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm"
                        />
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Angle</label>
                        <input
                            v-model.number="layout.watermark_angle"
                            type="number"
                            min="0"
                            max="60"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm"
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
