<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PaperPreview from '@/Components/PaperPreview.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps({ grades: Array, teacherPermissions: Object });

const step = ref(1);
const gradeId = ref(null);
const subjectId = ref(null);
const chapterIds = ref([]);
const sources = ref(['exercise']);
const dualMedium = ref(false);
const subjects = ref([]);
const chapters = ref([]);
const questions = ref([]);
const manualPaginator = ref(null);
const selectedIds = ref([]);
const mode = ref('manual');
const randomConfig = ref({ mcq: 5, short: 2, long: 0 });
const manualType = ref('');
const manualSearch = ref('');
const perPage = ref(15);
const paperTitle = ref('Mid Term Examination');
const examMeta = ref({ class: '', subject: '', time: '2 Hours', marks: '50' });
const settings = ref({
    blank_lines: 3,
    questions_per_line: 1,
    enable_omr: true,
    enable_answer_key: true,
    enable_watermark: false,
    show_past_paper_tags: false,
});

const allowedCategories = computed(() => props.teacherPermissions?.allowed_categories ?? null);
const allowedSources = computed(() => {
    // If no explicit category restriction, allow all.
    if (!Array.isArray(allowedCategories.value) || !allowedCategories.value.length) {
        return ['exercise', 'additional', 'past_paper'];
    }
    return allowedCategories.value;
});

watch(
    allowedSources,
    (allowed) => {
        // Ensure currently selected sources are permitted.
        sources.value = sources.value.filter((s) => allowed.includes(s));
        if (!sources.value.length) {
            sources.value = [allowed[0]];
        }
    },
    { immediate: true },
);

const loadSubjects = async () => {
    if (!gradeId.value) return;
    const { data } = await axios.get(`/api/builder/grades/${gradeId.value}/subjects`);
    subjects.value = data;
    subjectId.value = null;
    chapters.value = [];
};

const loadChapters = async () => {
    if (!subjectId.value) return;
    const { data } = await axios.get(`/api/builder/subjects/${subjectId.value}/chapters`);
    chapters.value = data;
};

const loadQuestions = async () => {
    if (!chapterIds.value.length) return;
    const { data } = await axios.get('/api/builder/questions', {
        params: {
            chapter_ids: chapterIds.value,
            sources: sources.value,
            type: manualType.value || undefined,
            search: manualSearch.value || undefined,
            per_page: perPage.value,
        },
    });
    manualPaginator.value = data;
    questions.value = data.data ?? data;
};

const loadPage = async (url) => {
    if (!url) return;
    const { data } = await axios.get(url, {
        params: {
            chapter_ids: chapterIds.value,
            sources: sources.value,
            type: manualType.value || undefined,
            search: manualSearch.value || undefined,
            per_page: perPage.value,
        },
    });
    manualPaginator.value = data;
    questions.value = data.data ?? data;
};

const randomize = async (refresh = false) => {
    const cacheKey = `random-${gradeId.value}-${subjectId.value}-${Date.now()}`;
    const { data } = await axios.post('/api/builder/questions/random', {
        chapter_ids: chapterIds.value,
        config: randomConfig.value,
        cache_key: cacheKey,
        refresh,
    });
    selectedIds.value = data.map((q) => q.id);
    questions.value = data;
};

watch(gradeId, loadSubjects);
watch(subjectId, loadChapters);

const toggleQuestion = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) selectedIds.value.splice(idx, 1);
    else selectedIds.value.push(id);
};

const selectedQuestions = computed(() =>
    questions.value.filter((q) => selectedIds.value.includes(q.id)),
);

const savePaper = () => {
    router.post(route('builder.store'), {
        title: paperTitle.value,
        config: {
            grade_id: gradeId.value,
            subject_id: subjectId.value,
            chapter_ids: chapterIds.value,
            sources: sources.value,
            dual_medium: dualMedium.value,
            question_ids: selectedIds.value,
            exam_meta: examMeta.value,
            settings: settings.value,
            layout: {
                dual_medium: dualMedium.value,
                enable_omr: settings.value.enable_omr,
                enable_answer_key: settings.value.enable_answer_key,
                enable_watermark: settings.value.enable_watermark,
                show_past_paper_tags: settings.value.show_past_paper_tags,
            },
        },
    });
};
</script>

<template>
    <Head title="Paper Builder" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Paper Generation Wizard</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6">
            <div class="mb-6 flex gap-2">
                <span
                    v-for="n in 4"
                    :key="n"
                    class="rounded-full px-3 py-1 text-sm"
                    :class="step === n ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'"
                >
                    Step {{ n }}
                </span>
            </div>

            <div v-show="step === 1" class="space-y-4 rounded-lg bg-white p-6 shadow">
                <div>
                    <label class="block text-sm font-medium">Grade</label>
                    <select v-model="gradeId" class="mt-1 w-full rounded-md border-gray-300">
                        <option :value="null">Select grade</option>
                        <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.label_en }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Subject</label>
                    <select v-model="subjectId" class="mt-1 w-full rounded-md border-gray-300" :disabled="!subjects.length">
                        <option :value="null">Select subject</option>
                        <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name_en }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Chapters</label>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <label v-for="c in chapters" :key="c.id" class="flex items-center gap-2 text-sm">
                            <input v-model="chapterIds" type="checkbox" :value="c.id" />
                            Ch {{ c.number }}: {{ c.title_en }}
                        </label>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4">
                    <label v-for="src in allowedSources" :key="src" class="flex items-center gap-2 text-sm">
                        <input v-model="sources" type="checkbox" :value="src" />
                        {{ src }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="dualMedium" type="checkbox" />
                        Dual Medium (EN + UR)
                    </label>
                    <label v-if="sources.includes('past_paper')" class="flex items-center gap-2 text-sm">
                        <input v-model="settings.show_past_paper_tags" type="checkbox" />
                        Show Board Name &amp; Year
                    </label>
                </div>
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-white" @click="step = 2">Next</button>
            </div>

            <div v-show="step === 2" class="rounded-lg bg-white p-6 shadow">
                <div class="mb-4 flex gap-4">
                    <button :class="mode === 'manual' ? 'bg-indigo-600 text-white' : 'bg-gray-200'" class="rounded px-3 py-1" @click="mode = 'manual'; loadQuestions()">Manual</button>
                    <button :class="mode === 'random' ? 'bg-indigo-600 text-white' : 'bg-gray-200'" class="rounded px-3 py-1" @click="mode = 'random'; randomize()">Random</button>
                </div>

                <div v-if="mode === 'random'" class="mb-4 flex gap-4">
                    <label class="text-sm">MCQs <input v-model.number="randomConfig.mcq" type="number" class="w-16 rounded border" /></label>
                    <label class="text-sm">Short <input v-model.number="randomConfig.short" type="number" class="w-16 rounded border" /></label>
                    <label class="text-sm">Long <input v-model.number="randomConfig.long" type="number" class="w-16 rounded border" /></label>
                    <button class="rounded bg-gray-800 px-3 py-1 text-sm text-white" @click="randomize(true)">Re-randomise</button>
                </div>

                <div v-else class="mb-4">
                    <div class="grid gap-3 md:grid-cols-4">
                        <input
                            v-model="manualSearch"
                            class="rounded border-gray-300"
                            placeholder="Search questions..."
                            @keyup.enter="loadQuestions"
                        />
                        <select v-model="manualType" class="rounded border-gray-300">
                            <option value="">All types</option>
                            <option value="mcq">MCQ</option>
                            <option value="short">Short</option>
                            <option value="long">Long</option>
                            <option value="fill">Fill</option>
                            <option value="truefalse">True/False</option>
                        </select>
                        <select v-model.number="perPage" class="rounded border-gray-300">
                            <option :value="10">10 / page</option>
                            <option :value="15">15 / page</option>
                            <option :value="20">20 / page</option>
                            <option :value="30">30 / page</option>
                        </select>
                        <button class="rounded bg-gray-800 px-3 py-2 text-sm text-white" @click="loadQuestions">Search</button>
                    </div>
                </div>

                <p class="mb-2 text-sm text-gray-600">Selected: {{ selectedIds.length }} questions</p>

                <div class="max-h-96 space-y-2 overflow-y-auto">
                    <div
                        v-for="q in questions"
                        :key="q.id"
                        class="cursor-pointer rounded border p-3 text-sm"
                        :class="selectedIds.includes(q.id) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
                        @click="toggleQuestion(q.id)"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <span class="rounded bg-gray-200 px-2 py-0.5 text-xs uppercase">{{ q.type }}</span>
                            <img
                                v-if="q.image_path"
                                :src="`/storage/${q.image_path}`"
                                alt="Question image"
                                class="h-12 w-12 rounded object-cover ring-1 ring-gray-200"
                            />
                        </div>
                        <p class="mt-1">{{ q.text_en }}</p>
                        <p v-if="dualMedium && q.text_ur" class="question-ur mt-1">{{ q.text_ur }}</p>
                    </div>
                </div>

                <div v-if="manualPaginator?.links && mode === 'manual'" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="(lnk, idx) in manualPaginator.links"
                        :key="idx"
                        class="rounded border px-3 py-1 text-sm"
                        :class="lnk.active ? 'bg-indigo-600 text-white' : 'bg-white'"
                        :disabled="!lnk.url"
                        v-html="lnk.label"
                        @click="loadPage(lnk.url)"
                    />
                </div>

                <div class="mt-4 flex gap-2">
                    <button class="rounded bg-gray-300 px-4 py-2" @click="step = 1">Back</button>
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white" :disabled="!selectedIds.length" @click="step = 3">Next</button>
                </div>
            </div>

            <div v-show="step === 3" class="space-y-4 rounded-lg bg-white p-6 shadow">
                <input v-model="paperTitle" class="w-full rounded border-gray-300" placeholder="Paper title" />
                <div class="grid grid-cols-2 gap-4">
                    <input v-model="examMeta.class" placeholder="Class" class="rounded border-gray-300" />
                    <input v-model="examMeta.subject" placeholder="Subject" class="rounded border-gray-300" />
                    <input v-model="examMeta.time" placeholder="Time" class="rounded border-gray-300" />
                    <input v-model="examMeta.marks" placeholder="Total marks" class="rounded border-gray-300" />
                </div>
                <label class="flex items-center gap-2 text-sm"><input v-model="settings.enable_omr" type="checkbox" /> OMR Bubble Sheet</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="settings.enable_answer_key" type="checkbox" /> Teacher Answer Key</label>
                <div class="flex gap-2">
                    <button class="rounded bg-gray-300 px-4 py-2" @click="step = 2">Back</button>
                    <button class="rounded bg-indigo-600 px-4 py-2 text-white" @click="step = 4">Preview</button>
                </div>
            </div>

            <div v-show="step === 4" class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-4">
                    <PaperPreview
                        :title="paperTitle"
                        :questions="selectedQuestions"
                        :dual-medium="dualMedium"
                        :settings="settings"
                    />
                    <div class="flex gap-2">
                        <button class="rounded bg-gray-300 px-4 py-2" @click="step = 3">Back</button>
                        <button class="rounded bg-green-600 px-4 py-2 text-white" @click="savePaper">Save Paper</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
