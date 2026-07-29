<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    grade: { type: Object, required: true },
    subject: { type: Object, required: true },
    chapters: { type: Array, default: () => [] },
});

const selectedTopicIds = ref([]);
const expanded = ref({});

props.chapters.forEach((chapter) => {
    expanded.value[chapter.id] = true;
});

const allTopicIds = computed(() =>
    props.chapters.flatMap((chapter) => (chapter.topics ?? []).map((topic) => topic.id)),
);

const selectedCount = computed(() => selectedTopicIds.value.length);

const allSelected = computed({
    get() {
        return allTopicIds.value.length > 0
            && allTopicIds.value.every((id) => selectedTopicIds.value.includes(id));
    },
    set(value) {
        selectedTopicIds.value = value ? [...allTopicIds.value] : [];
    },
});

const isChapterFullySelected = (chapter) => {
    const ids = (chapter.topics ?? []).map((t) => t.id);
    return ids.length > 0 && ids.every((id) => selectedTopicIds.value.includes(id));
};

const isChapterPartiallySelected = (chapter) => {
    const ids = (chapter.topics ?? []).map((t) => t.id);
    const count = ids.filter((id) => selectedTopicIds.value.includes(id)).length;
    return count > 0 && count < ids.length;
};

const toggleChapter = (chapter, checked) => {
    const ids = (chapter.topics ?? []).map((t) => t.id);
    if (checked) {
        selectedTopicIds.value = [...new Set([...selectedTopicIds.value, ...ids])];
    } else {
        selectedTopicIds.value = selectedTopicIds.value.filter((id) => !ids.includes(id));
    }
};

const toggleTopic = (topicId, checked) => {
    if (checked) {
        if (!selectedTopicIds.value.includes(topicId)) {
            selectedTopicIds.value = [...selectedTopicIds.value, topicId];
        }
    } else {
        selectedTopicIds.value = selectedTopicIds.value.filter((id) => id !== topicId);
    }
};

const continueToWizard = () => {
    if (!selectedTopicIds.value.length) return;

    const chapters = props.chapters
        .filter((chapter) => (chapter.topics ?? []).some((t) => selectedTopicIds.value.includes(t.id)))
        .map((chapter) => chapter.id);

    if (!chapters.length) return;

    router.visit(route('builder.create', {
        grade: props.grade.id,
        subject: props.subject.id,
        chapters: chapters.join(','),
        topics: selectedTopicIds.value.join(','),
    }));
};

const classLabel = computed(() => {
    const n = props.grade?.number;
    if (n === 11) return '1st Year';
    if (n === 12) return '2nd Year';
    return n ? `${n}th` : props.grade?.label_en;
});
</script>

<template>
    <Head :title="`Chapters · ${subject.name_en}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Select Chapters</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ grade.label_en }}
                        <span class="mx-1 text-gray-300">·</span>
                        {{ subject.name_en }}
                    </p>
                </div>
                <Link
                    :href="route('builder.subjects', { grade: grade.id })"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    ← Change subject
                </Link>
            </div>
        </template>

        <div class="relative overflow-hidden py-8">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_#ecfdf5_0%,_#f8fafc_42%,_#f1f5f9_100%)]" />
            <div class="pointer-events-none absolute -left-16 top-10 h-56 w-56 rounded-full bg-emerald-200/40 blur-3xl" />
            <div class="pointer-events-none absolute -right-10 bottom-0 h-64 w-64 rounded-full bg-teal-200/30 blur-3xl" />

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-emerald-100 bg-white/80 p-4 shadow-sm backdrop-blur">
                    <label class="flex cursor-pointer items-center gap-3">
                        <input
                            v-model="allSelected"
                            type="checkbox"
                            class="h-5 w-5 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">Select all chapters</span>
                            <span class="text-xs text-slate-500">{{ selectedCount }} topic(s) selected</span>
                        </span>
                    </label>

                    <button
                        type="button"
                        class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-700/20 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!selectedCount"
                        @click="continueToWizard"
                    >
                        Continue with selection →
                    </button>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <div
                        v-for="chapter in chapters"
                        :key="chapter.id"
                        class="overflow-hidden rounded-2xl border bg-white shadow-sm transition"
                        :class="isChapterFullySelected(chapter) || isChapterPartiallySelected(chapter)
                            ? 'border-emerald-300 shadow-emerald-900/10 ring-1 ring-emerald-200'
                            : 'border-slate-200'"
                    >
                        <div class="flex items-start gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-900 to-slate-800 px-4 py-3.5 text-white">
                            <input
                                type="checkbox"
                                class="mt-1 h-5 w-5 rounded border-slate-400 text-emerald-500 focus:ring-emerald-400"
                                :checked="isChapterFullySelected(chapter)"
                                :indeterminate.prop="isChapterPartiallySelected(chapter)"
                                @change="toggleChapter(chapter, $event.target.checked)"
                            />
                            <button
                                type="button"
                                class="min-w-0 flex-1 text-left"
                                @click="expanded[chapter.id] = !expanded[chapter.id]"
                            >
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-300">
                                    Chapter {{ chapter.number }}
                                </p>
                                <p class="mt-0.5 text-base font-semibold leading-snug">
                                    {{ chapter.title_en }}
                                </p>
                                <p v-if="chapter.title_ur" class="mt-0.5 text-sm text-white/70" dir="rtl">
                                    {{ chapter.title_ur }}
                                </p>
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-white/70 hover:bg-white/10 hover:text-white"
                                @click="expanded[chapter.id] = !expanded[chapter.id]"
                            >
                                <svg
                                    class="h-5 w-5 transition"
                                    :class="expanded[chapter.id] ? 'rotate-180' : ''"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>

                        <div v-show="expanded[chapter.id]" class="space-y-1 p-3">
                            <label
                                v-for="topic in chapter.topics"
                                :key="topic.id"
                                class="flex cursor-pointer items-start gap-3 rounded-xl px-3 py-2.5 transition hover:bg-emerald-50"
                                :class="selectedTopicIds.includes(topic.id) ? 'bg-emerald-50/80' : ''"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    :checked="selectedTopicIds.includes(topic.id)"
                                    @change="toggleTopic(topic.id, $event.target.checked)"
                                />
                                <span class="min-w-0">
                                    <span class="mr-2 inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-slate-600">
                                        {{ topic.code }}
                                    </span>
                                    <span class="text-sm font-medium text-slate-800">{{ topic.title_en }}</span>
                                    <span v-if="topic.title_ur" class="mt-0.5 block text-xs text-slate-500" dir="rtl">
                                        {{ topic.title_ur }}
                                    </span>
                                </span>
                            </label>

                            <p v-if="!(chapter.topics ?? []).length" class="px-3 py-4 text-sm text-slate-400">
                                No topics listed for this chapter.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-center sm:hidden">
                    <button
                        type="button"
                        class="w-full rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-40"
                        :disabled="!selectedCount"
                        @click="continueToWizard"
                    >
                        Continue with selection →
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
