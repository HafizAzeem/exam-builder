<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    grade: { type: Object, required: true },
    subjects: { type: Array, default: () => [] },
});

const subjectThemes = {
    mathematics: { pattern: 'from-sky-600 to-blue-800', icon: 'math' },
    english: { pattern: 'from-emerald-600 to-teal-800', icon: 'book' },
    urdu: { pattern: 'from-amber-600 to-orange-800', icon: 'script' },
    islamiyat: { pattern: 'from-green-700 to-emerald-900', icon: 'moon' },
    'pakistan studies': { pattern: 'from-green-600 to-emerald-800', icon: 'flag' },
    physics: { pattern: 'from-violet-600 to-fuchsia-800', icon: 'atom' },
    chemistry: { pattern: 'from-rose-600 to-red-800', icon: 'flask' },
    biology: { pattern: 'from-lime-600 to-green-800', icon: 'leaf' },
    'computer science': { pattern: 'from-slate-700 to-cyan-900', icon: 'chip' },
    'tarjuma-tul-quran': { pattern: 'from-teal-700 to-emerald-900', icon: 'moon' },
    'general science': { pattern: 'from-cyan-600 to-sky-800', icon: 'atom' },
};

const resolveTheme = (name) => {
    const key = (name || '').toLowerCase().trim();
    return subjectThemes[key] ?? { pattern: 'from-slate-600 to-slate-800', icon: 'book' };
};

const classLabel = computed(() => {
    const n = props.grade?.number;
    if (!n) return props.grade?.label_en ?? '';
    if (n === 11) return '1st Year';
    if (n === 12) return '2nd Year';
    return `${n}th`;
});

const cards = computed(() =>
    props.subjects.map((subject) => ({
        ...subject,
        theme: resolveTheme(subject.name_en),
    })),
);

const selectSubject = (subject) => {
    router.visit(route('builder.chapters', {
        grade: props.grade.id,
        subject: subject.id,
    }));
};
</script>

<template>
    <Head :title="`Subjects · ${grade.label_en}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Select Subject</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ grade.label_en }}
                        <span class="mx-1 text-gray-300">·</span>
                        Choose a subject to continue
                    </p>
                </div>
                <Link
                    :href="route('builder')"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    ← Change class
                </Link>
            </div>
        </template>

        <div class="relative overflow-hidden py-10">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_#fff7ed_0%,_#f8fafc_40%,_#f1f5f9_100%)]" />
            <div class="pointer-events-none absolute -left-20 top-16 h-64 w-64 rounded-full bg-amber-200/35 blur-3xl" />
            <div class="pointer-events-none absolute -right-10 bottom-10 h-72 w-72 rounded-full bg-teal-200/30 blur-3xl" />

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-8 text-center sm:mb-10">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-700">
                        Class {{ classLabel }}
                    </p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                        Pick your subject
                    </h1>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-600 sm:text-base">
                        Select a subject to configure chapters and generate your paper.
                    </p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <button
                        v-for="card in cards"
                        :key="card.id"
                        type="button"
                        class="group relative overflow-hidden rounded-3xl text-left shadow-lg shadow-slate-900/10 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-slate-900/20 focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-300"
                        @click="selectSubject(card)"
                    >
                        <div class="absolute inset-0 bg-gradient-to-br" :class="card.theme.pattern" />
                        <div class="absolute inset-0 bg-[linear-gradient(160deg,rgba(255,255,255,0.28)_0%,transparent_42%,rgba(0,0,0,0.2)_100%)]" />
                        <div class="absolute -right-10 -top-12 h-40 w-40 rounded-full bg-white/15 blur-2xl transition duration-500 group-hover:scale-125" />
                        <div class="absolute -bottom-12 -left-8 h-32 w-32 rounded-full bg-black/10" />

                        <div class="relative flex min-h-[210px] flex-col p-6 text-white">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 ring-1 ring-white/30 backdrop-blur-sm">
                                <!-- math -->
                                <svg v-if="card.theme.icon === 'math'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h6M7 4v6M14 6l6 6M20 6l-6 6M4 17h7M14 15h6v2H14z" />
                                </svg>
                                <!-- book -->
                                <svg v-else-if="card.theme.icon === 'book'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5.5A2.5 2.5 0 016.5 3H20v16H6.5A2.5 2.5 0 004 16.5v-11zM4 16.5A2.5 2.5 0 016.5 19H20" />
                                </svg>
                                <!-- script / urdu -->
                                <svg v-else-if="card.theme.icon === 'script'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 4h8a3 3 0 013 3v13H8a3 3 0 01-3-3V7a3 3 0 013-3zm0 5h10M7 13h7" />
                                </svg>
                                <!-- moon -->
                                <svg v-else-if="card.theme.icon === 'moon'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 14.5A8.5 8.5 0 119.5 3a7 7 0 0011.5 11.5z" />
                                </svg>
                                <!-- flag -->
                                <svg v-else-if="card.theme.icon === 'flag'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 21V4m0 0h9l-1.5 3L14 10H5" />
                                </svg>
                                <!-- atom -->
                                <svg v-else-if="card.theme.icon === 'atom'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="2" stroke-width="1.8" />
                                    <ellipse cx="12" cy="12" rx="9" ry="4" stroke-width="1.8" />
                                    <ellipse cx="12" cy="12" rx="9" ry="4" stroke-width="1.8" transform="rotate(60 12 12)" />
                                    <ellipse cx="12" cy="12" rx="9" ry="4" stroke-width="1.8" transform="rotate(120 12 12)" />
                                </svg>
                                <!-- flask -->
                                <svg v-else-if="card.theme.icon === 'flask'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 3h6M10 3v6l-5 9a2 2 0 001.7 3h10.6a2 2 0 001.7-3l-5-9V3" />
                                </svg>
                                <!-- leaf -->
                                <svg v-else-if="card.theme.icon === 'leaf'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 19c8 0 14-6 14-14-8 0-14 6-14 14zm0 0c3-3 6-6 9-8" />
                                </svg>
                                <!-- chip -->
                                <svg v-else-if="card.theme.icon === 'chip'" class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <rect x="7" y="7" width="10" height="10" rx="2" stroke-width="1.8" />
                                    <path stroke-linecap="round" stroke-width="1.8" d="M9 3v4M12 3v4M15 3v4M9 17v4M12 17v4M15 17v4M3 9h4M3 12h4M3 15h4M17 9h4M17 12h4M17 15h4" />
                                </svg>
                            </div>

                            <div class="mt-auto pt-10">
                                <h3 class="text-2xl font-bold tracking-tight drop-shadow-sm">
                                    {{ card.name_en }}
                                </h3>
                                <p v-if="card.name_ur" class="mt-1 text-base text-white/85" dir="rtl">
                                    {{ card.name_ur }}
                                </p>
                                <div class="mt-4 flex items-center justify-between gap-2">
                                    <span class="rounded-full bg-white/20 px-2.5 py-1 text-xs font-semibold uppercase tracking-wider ring-1 ring-white/25">
                                        Class {{ classLabel }}
                                    </span>
                                    <span class="text-sm font-medium text-white/85 opacity-0 transition group-hover:opacity-100">
                                        Continue →
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="absolute inset-x-0 bottom-0 h-1.5 origin-left scale-x-0 bg-white/90 transition duration-300 group-hover:scale-x-100" />
                    </button>
                </div>

                <p v-if="!cards.length" class="rounded-2xl border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center text-slate-500">
                    No subjects available for this class yet.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
