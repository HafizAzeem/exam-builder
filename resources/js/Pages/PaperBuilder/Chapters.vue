<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    grade: { type: Object, required: true },
    subject: { type: Object, required: true },
    chapters: { type: Array, default: () => [] },
});

/** Chapter-only selection — topics stay hidden until real topic data exists. */
const selectedChapterIds = ref([]);

const selectedCount = computed(() => selectedChapterIds.value.length);

const allChapterIds = computed(() => props.chapters.map((c) => c.id));

const allSelected = computed({
    get() {
        return allChapterIds.value.length > 0
            && allChapterIds.value.every((id) => selectedChapterIds.value.includes(id));
    },
    set(value) {
        selectedChapterIds.value = value ? [...allChapterIds.value] : [];
    },
});

const isChapterSelected = (chapter) => selectedChapterIds.value.includes(chapter.id);

const toggleChapter = (chapter, checked) => {
    if (checked) {
        if (!selectedChapterIds.value.includes(chapter.id)) {
            selectedChapterIds.value = [...selectedChapterIds.value, chapter.id];
        }
    } else {
        selectedChapterIds.value = selectedChapterIds.value.filter((id) => id !== chapter.id);
    }
};

const topicIdsForChapters = (chapterIds) => props.chapters
    .filter((chapter) => chapterIds.includes(chapter.id))
    .flatMap((chapter) => (chapter.topics ?? []).map((topic) => topic.id));

const continueToWizard = () => {
    if (!selectedChapterIds.value.length) return;

    const chapters = selectedChapterIds.value;
    const topics = topicIdsForChapters(chapters);

    router.visit(route('builder.create', {
        grade: props.grade.id,
        subject: props.subject.id,
        chapters: chapters.join(','),
        // Pass all topic IDs silently so question search still scopes correctly.
        ...(topics.length ? { topics: topics.join(',') } : {}),
    }));
};
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
                            <span class="text-xs text-slate-500">{{ selectedCount }} chapter(s) selected</span>
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

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="chapter in chapters"
                        :key="chapter.id"
                        class="flex cursor-pointer items-start gap-3 overflow-hidden rounded-2xl border bg-white p-4 shadow-sm transition"
                        :class="isChapterSelected(chapter)
                            ? 'border-emerald-300 shadow-emerald-900/10 ring-1 ring-emerald-200'
                            : 'border-slate-200 hover:border-slate-300'"
                    >
                        <input
                            type="checkbox"
                            class="mt-1 h-5 w-5 rounded border-slate-400 text-emerald-600 focus:ring-emerald-500"
                            :checked="isChapterSelected(chapter)"
                            @change="toggleChapter(chapter, $event.target.checked)"
                        />
                        <span class="min-w-0">
                            <span class="block text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700">
                                Chapter {{ chapter.number }}
                            </span>
                            <span class="mt-0.5 block text-base font-semibold leading-snug text-slate-900">
                                {{ chapter.title_en }}
                            </span>
                            <span
                                v-if="chapter.title_ur"
                                class="mt-0.5 block text-sm text-slate-500"
                                dir="rtl"
                            >
                                {{ chapter.title_ur }}
                            </span>
                        </span>
                    </label>
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
