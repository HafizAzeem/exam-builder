<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    aiImport: { type: Object, required: true },
    questions: { type: Object, required: true },
    chapters: { type: Array, required: true },
    topics: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const selected = ref([]);
const editingId = ref(null);
const editForm = useForm({
    chapter_id: null,
    topic_id: null,
    type: 'short',
    source: 'exercise',
    text_en: '',
    text_ur: '',
    review_notes: '',
});

const bulkForm = useForm({
    ids: [],
    action: 'approve',
    chapter_id: null,
    topic_id: null,
    type: null,
    source: null,
    status: null,
});

const allSelected = computed(() =>
    props.questions.data.length > 0 && selected.value.length === props.questions.data.length
);

const toggleAll = () => {
    selected.value = allSelected.value ? [] : props.questions.data.map((q) => q.id);
};

const topicsFor = (chapterId) => props.topics.filter((t) => String(t.chapter_id) === String(chapterId));

const startEdit = (q) => {
    editingId.value = q.id;
    editForm.chapter_id = q.chapter_id;
    editForm.topic_id = q.topic_id;
    editForm.type = q.type;
    editForm.source = q.source;
    editForm.text_en = q.text_en || '';
    editForm.text_ur = q.text_ur || '';
    editForm.review_notes = q.review_notes || '';
};

const saveEdit = (q) => {
    editForm.patch(route('super-admin.ai-import.questions.update', [props.aiImport.id, q.id]), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
};

const approve = (q) => {
    router.post(route('super-admin.ai-import.questions.approve', [props.aiImport.id, q.id]), {}, { preserveScroll: true });
};

const reject = (q) => {
    router.post(route('super-admin.ai-import.questions.reject', [props.aiImport.id, q.id]), {}, { preserveScroll: true });
};

const merge = (q) => {
    if (!q.duplicate_of_question_id) return;
    router.post(route('super-admin.ai-import.questions.merge', [props.aiImport.id, q.id]), {
        existing_question_id: q.duplicate_of_question_id,
    }, { preserveScroll: true });
};

const runBulk = (action) => {
    bulkForm.ids = selected.value;
    bulkForm.action = action;
    bulkForm.post(route('super-admin.ai-import.bulk', props.aiImport.id), {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; },
    });
};

const importApproved = () => {
    router.post(route('super-admin.ai-import.import-approved', props.aiImport.id));
};

const applyFilters = (key, value) => {
    router.get(route('super-admin.ai-import.review', props.aiImport.id), {
        ...props.filters,
        [key]: value || undefined,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Review AI Questions" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Review Extracted Questions</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ aiImport.original_filename }} · {{ aiImport.grade?.label_en }} / {{ aiImport.subject?.name_en }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('super-admin.ai-import.show', aiImport.id)">
                        <SecondaryButton>Progress</SecondaryButton>
                    </Link>
                    <PrimaryButton @click="importApproved">Import Approved</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <select
                        class="rounded-md border-gray-300 text-sm"
                        :value="filters.status || ''"
                        @change="applyFilters('status', $event.target.value)"
                    >
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="imported">Imported</option>
                        <option value="duplicate">Duplicate</option>
                    </select>
                    <select
                        class="rounded-md border-gray-300 text-sm"
                        :value="filters.match_status || ''"
                        @change="applyFilters('match_status', $event.target.value)"
                    >
                        <option value="">All match states</option>
                        <option value="matched">Matched</option>
                        <option value="unmatched_chapter">Unmatched chapter</option>
                        <option value="unmatched_topic">Unmatched topic</option>
                        <option value="manual">Manual</option>
                    </select>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input
                            type="checkbox"
                            :checked="!!filters.duplicates_only"
                            @change="applyFilters('duplicates_only', $event.target.checked ? 1 : '')"
                        >
                        Duplicates only
                    </label>
                    <div class="ms-auto flex flex-wrap gap-2">
                        <SecondaryButton :disabled="!selected.length" @click="runBulk('approve')">Bulk Approve</SecondaryButton>
                        <DangerButton :disabled="!selected.length" @click="runBulk('reject')">Bulk Reject</DangerButton>
                        <SecondaryButton
                            :disabled="!selected.length || !bulkForm.chapter_id"
                            @click="runBulk('edit')"
                        >
                            Bulk Map Chapter
                        </SecondaryButton>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm">
                    <div>
                        <label class="text-gray-500">Bulk chapter</label>
                        <select v-model="bulkForm.chapter_id" class="mt-1 block rounded-md border-gray-300">
                            <option :value="null">—</option>
                            <option v-for="c in chapters" :key="c.id" :value="c.id">{{ c.number }}. {{ c.title_en }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-gray-500">Bulk topic</label>
                        <select v-model="bulkForm.topic_id" class="mt-1 block rounded-md border-gray-300">
                            <option :value="null">—</option>
                            <option v-for="t in topicsFor(bulkForm.chapter_id)" :key="t.id" :value="t.id">{{ t.title_en }}</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3">
                                        <input type="checkbox" :checked="allSelected" @change="toggleAll">
                                    </th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-500">Question</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-500">Chapter</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-500">Topic</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-500">Type</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-500">Source</th>
                                    <th class="px-3 py-3 text-left font-medium text-gray-500">Status</th>
                                    <th class="px-3 py-3 text-right font-medium text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="q in questions.data"
                                    :key="q.id"
                                    :class="q.is_duplicate ? 'bg-amber-50' : ''"
                                >
                                    <td class="px-3 py-3 align-top">
                                        <input v-model="selected" type="checkbox" :value="q.id">
                                    </td>
                                    <td class="max-w-md px-3 py-3 align-top">
                                        <template v-if="editingId === q.id">
                                            <textarea v-model="editForm.text_en" rows="3" class="w-full rounded-md border-gray-300 text-sm" placeholder="English" />
                                            <textarea v-model="editForm.text_ur" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm" placeholder="Urdu" dir="rtl" />
                                        </template>
                                        <template v-else>
                                            <p class="text-gray-900">{{ q.text_en || '—' }}</p>
                                            <p v-if="q.text_ur" class="mt-1 text-gray-600" dir="rtl">{{ q.text_ur }}</p>
                                            <p v-if="q.confidence_score != null" class="mt-1 text-xs text-gray-500">Confidence: {{ q.confidence_score }}</p>
                                            <p v-if="q.source_url" class="mt-1 text-xs">
                                                <a :href="q.source_url" target="_blank" rel="noopener" class="text-indigo-600 break-all">{{ q.source_url }}</a>
                                            </p>
                                            <details v-if="q.source_excerpt" class="mt-1 text-xs text-gray-500">
                                                <summary class="cursor-pointer">Extracted excerpt</summary>
                                                <pre class="mt-1 max-h-32 overflow-auto whitespace-pre-wrap">{{ q.source_excerpt }}</pre>
                                            </details>
                                            <details v-if="q.raw_payload" class="mt-1 text-xs text-gray-500">
                                                <summary class="cursor-pointer">AI structured output</summary>
                                                <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap">{{ JSON.stringify(q.raw_payload, null, 2) }}</pre>
                                            </details>
                                            <p v-if="q.is_duplicate" class="mt-1 text-xs font-medium text-amber-700">
                                                Duplicate of #{{ q.duplicate_of_question_id }}
                                                <span v-if="q.duplicate_score != null">(score {{ q.duplicate_score }})</span>
                                            </p>
                                            <details v-if="q.duplicate_diff" class="mt-1 text-xs text-amber-800">
                                                <summary class="cursor-pointer">Differences from existing</summary>
                                                <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap">{{ JSON.stringify(q.duplicate_diff, null, 2) }}</pre>
                                            </details>
                                            <p v-if="q.match_status !== 'matched' && q.match_status !== 'manual'" class="mt-1 text-xs text-rose-600">
                                                Needs mapping ({{ q.match_status }})
                                            </p>
                                        </template>
                                    </td>
                                    <td class="px-3 py-3 align-top">
                                        <template v-if="editingId === q.id">
                                            <select v-model="editForm.chapter_id" class="w-full rounded-md border-gray-300 text-sm" @change="editForm.topic_id = null">
                                                <option :value="null">Select</option>
                                                <option v-for="c in chapters" :key="c.id" :value="c.id">{{ c.number }}. {{ c.title_en }}</option>
                                            </select>
                                        </template>
                                        <template v-else>
                                            <span v-if="q.chapter">{{ q.chapter.number }}. {{ q.chapter.title_en }}</span>
                                            <span v-else class="text-rose-600">{{ q.chapter_title || 'Unmatched' }}</span>
                                        </template>
                                    </td>
                                    <td class="px-3 py-3 align-top">
                                        <template v-if="editingId === q.id">
                                            <select v-model="editForm.topic_id" class="w-full rounded-md border-gray-300 text-sm">
                                                <option :value="null">Optional</option>
                                                <option v-for="t in topicsFor(editForm.chapter_id)" :key="t.id" :value="t.id">{{ t.title_en }}</option>
                                            </select>
                                        </template>
                                        <template v-else>
                                            {{ q.topic?.title_en || q.topic_title || '—' }}
                                        </template>
                                    </td>
                                    <td class="px-3 py-3 align-top capitalize">
                                        <select v-if="editingId === q.id" v-model="editForm.type" class="rounded-md border-gray-300 text-sm">
                                            <option value="mcq">mcq</option>
                                            <option value="short">short</option>
                                            <option value="long">long</option>
                                            <option value="fill">fill</option>
                                            <option value="truefalse">truefalse</option>
                                        </select>
                                        <span v-else>{{ q.type }}</span>
                                    </td>
                                    <td class="px-3 py-3 align-top">{{ q.source }}</td>
                                    <td class="px-3 py-3 align-top">
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs capitalize">{{ q.status }}</span>
                                    </td>
                                    <td class="space-y-1 px-3 py-3 text-right align-top">
                                        <template v-if="editingId === q.id">
                                            <button type="button" class="block w-full text-indigo-600" @click="saveEdit(q)">Save</button>
                                            <button type="button" class="block w-full text-gray-500" @click="editingId = null">Cancel</button>
                                        </template>
                                        <template v-else>
                                            <button type="button" class="block w-full text-indigo-600" @click="startEdit(q)">Edit</button>
                                            <button type="button" class="block w-full text-emerald-600" @click="approve(q)">Approve</button>
                                            <button type="button" class="block w-full text-rose-600" @click="reject(q)">Reject</button>
                                            <button
                                                v-if="q.is_duplicate && q.duplicate_of_question_id"
                                                type="button"
                                                class="block w-full text-amber-700"
                                                @click="merge(q)"
                                            >
                                                Merge
                                            </button>
                                        </template>
                                    </td>
                                </tr>
                                <tr v-if="!questions.data.length">
                                    <td colspan="8" class="px-4 py-10 text-center text-gray-500">No questions to review.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="questions.links?.length > 3" class="flex flex-wrap gap-2">
                    <Link
                        v-for="link in questions.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded border px-3 py-1 text-sm"
                        :class="link.active ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-200 bg-white text-gray-600'"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
