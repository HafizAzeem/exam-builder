<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    grades: { type: Array, required: true },
    subjects: { type: Array, required: true },
    sampleJson: { type: String, required: true },
});

const gradeId = ref('');
const subjectId = ref('');
const jsonText = ref('');
const previewing = ref(false);
const previewError = ref('');
const previewMeta = ref(null);
const rows = ref([]);

const filteredSubjects = computed(() =>
    props.subjects.filter((s) => !gradeId.value || String(s.grade_id) === String(gradeId.value))
);

const validIncludedCount = computed(() =>
    rows.value.filter((r) => r.include && r.valid).length
);

const saveForm = useForm({
    rows: [],
});

const loadSample = () => {
    jsonText.value = props.sampleJson;
};

const runPreview = async () => {
    previewError.value = '';
    previewMeta.value = null;
    rows.value = [];
    previewing.value = true;

    try {
        const { data } = await axios.post(route('super-admin.question-bank.jsonPreview'), {
            json: jsonText.value,
            grade_id: gradeId.value || null,
            subject_id: subjectId.value || null,
        });

        if (! data.ok) {
            previewError.value = data.error || 'Preview failed.';
            return;
        }

        previewMeta.value = data.meta;
        rows.value = (data.rows || []).map((r) => ({
            ...r,
            include: !!r.valid,
        }));
    } catch (e) {
        previewError.value = e.response?.data?.error
            || e.response?.data?.message
            || 'Preview failed. Check JSON format.';
    } finally {
        previewing.value = false;
    }
};

const save = () => {
    saveForm.rows = rows.value;
    saveForm.post(route('super-admin.question-bank.jsonImport'));
};

const answerBadge = (row) => {
    if (row.type === 'mcq' && row.answer_label) return row.answer_label;
    if (row.type === 'truefalse' && row.answer_label) return row.answer_label;
    return '—';
};
</script>

<template>
    <Head title="Import Questions from JSON" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Import Questions from JSON</h2>
                <Link :href="route('super-admin.question-bank.index')" class="rounded bg-gray-200 px-4 py-2 text-sm">
                    Back
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-8">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <p class="text-sm text-gray-600">
                    Paste JSON from ChatGPT / Gemini / Claude, preview, then save.
                    <strong>Answers</strong> are supported only for <code>mcq</code> (correct option A–D)
                    and <code>truefalse</code> (True / False). Short, long, and fill do not need answers.
                </p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Grade (fallback if missing in JSON)" />
                        <select
                            v-model="gradeId"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
                            @change="subjectId = ''"
                        >
                            <option value="">—</option>
                            <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.label_en }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Subject (fallback if missing in JSON)" />
                        <select v-model="subjectId" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">—</option>
                            <option v-for="s in filteredSubjects" :key="s.id" :value="s.id">{{ s.name_en }}</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <InputLabel value="JSON" />
                        <button type="button" class="text-sm text-indigo-600 hover:underline" @click="loadSample">
                            Load sample
                        </button>
                    </div>
                    <textarea
                        v-model="jsonText"
                        rows="14"
                        class="w-full rounded-md border-gray-300 font-mono text-xs shadow-sm"
                        placeholder='{ "grade": 9, "subject_en": "English", "questions": [ ... ] }'
                    />
                    <InputError :message="previewError" class="mt-1" />
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <SecondaryButton type="button" :disabled="previewing || !jsonText.trim()" @click="runPreview">
                        {{ previewing ? 'Previewing…' : 'Preview' }}
                    </SecondaryButton>
                </div>
            </div>

            <div v-if="previewMeta" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm text-gray-700">
                Preview for <strong>{{ previewMeta.grade_label }}</strong> /
                <strong>{{ previewMeta.subject_name }}</strong>
                · {{ rows.length }} question(s)
                · {{ rows.filter(r => r.valid).length }} valid
                · {{ rows.filter(r => !r.valid).length }} with errors
            </div>

            <div v-if="rows.length" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left">Save</th>
                                <th class="px-3 py-3 text-left">#</th>
                                <th class="px-3 py-3 text-left">Type</th>
                                <th class="px-3 py-3 text-left">Chapter</th>
                                <th class="px-3 py-3 text-left">Question</th>
                                <th class="px-3 py-3 text-left">Answer</th>
                                <th class="px-3 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="row in rows"
                                :key="row.index"
                                :class="row.valid ? '' : 'bg-rose-50'"
                            >
                                <td class="px-3 py-3">
                                    <input v-model="row.include" type="checkbox" :disabled="!row.valid">
                                </td>
                                <td class="px-3 py-3 tabular-nums">{{ row.index + 1 }}</td>
                                <td class="px-3 py-3 uppercase">{{ row.type || '—' }}</td>
                                <td class="px-3 py-3">{{ row.chapter_label }}</td>
                                <td class="max-w-md px-3 py-3">
                                    <p class="line-clamp-3">{{ row.text_en || row.text_ur || '—' }}</p>
                                    <ul v-if="row.type === 'mcq' && row.mcq" class="mt-1 space-y-0.5 text-xs text-gray-500">
                                        <li>A. {{ row.mcq.option_a_en }}</li>
                                        <li>B. {{ row.mcq.option_b_en }}</li>
                                        <li>C. {{ row.mcq.option_c_en }}</li>
                                        <li>D. {{ row.mcq.option_d_en }}</li>
                                    </ul>
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        v-if="row.type === 'mcq' || row.type === 'truefalse'"
                                        class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700"
                                    >
                                        {{ answerBadge(row) }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">n/a</span>
                                </td>
                                <td class="px-3 py-3">
                                    <span v-if="row.valid" class="text-xs text-emerald-700">OK</span>
                                    <ul v-else class="list-inside list-disc text-xs text-rose-700">
                                        <li v-for="(err, i) in row.errors" :key="i">{{ err }}</li>
                                    </ul>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
                    <p class="text-sm text-gray-600">
                        {{ validIncludedCount }} selected to save
                    </p>
                    <PrimaryButton
                        type="button"
                        :disabled="saveForm.processing || validIncludedCount === 0"
                        @click="save"
                    >
                        Save verified questions
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
