<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    grades: { type: Array, default: () => [] },
});

const ordinals = {
    5: 'Fifth',
    6: 'Sixth',
    7: 'Seventh',
    8: 'Eighth',
    9: 'Ninth',
    10: 'Tenth',
    11: '1st Year',
    12: '2nd Year',
};

const themes = {
    12: { accent: '#0f766e', soft: '#ccfbf1', ink: '#134e4a', pattern: 'from-teal-700 to-emerald-900' },
    11: { accent: '#0369a1', soft: '#e0f2fe', ink: '#0c4a6e', pattern: 'from-sky-700 to-blue-900' },
    10: { accent: '#b45309', soft: '#fef3c7', ink: '#78350f', pattern: 'from-amber-600 to-orange-800' },
    9: { accent: '#0d9488', soft: '#f0fdfa', ink: '#115e59', pattern: 'from-teal-500 to-cyan-700' },
    8: { accent: '#be185d', soft: '#fce7f3', ink: '#831843', pattern: 'from-rose-600 to-pink-800' },
    7: { accent: '#4338ca', soft: '#e0e7ff', ink: '#312e81', pattern: 'from-indigo-600 to-violet-800' },
    6: { accent: '#047857', soft: '#d1fae5', ink: '#064e3b', pattern: 'from-emerald-600 to-green-800' },
    5: { accent: '#c2410c', soft: '#ffedd5', ink: '#7c2d12', pattern: 'from-orange-500 to-red-700' },
};

const cards = computed(() =>
    props.grades.map((grade) => {
        const theme = themes[grade.number] ?? themes[9];
        return {
            ...grade,
            ordinal: ordinals[grade.number] ?? grade.label_en,
            displayNumber: grade.number === 11 ? '11' : grade.number === 12 ? '12' : String(grade.number),
            suffix: grade.number <= 10 ? 'th' : '',
            theme,
        };
    }),
);

const selectGrade = (grade) => {
    if (!grade.active) return;
    router.visit(route('builder.subjects', { grade: grade.id }));
};
</script>

<template>
    <Head title="Select Class" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Generate Paper</h2>
                <p class="mt-1 text-sm text-gray-500">Choose a class to start building your exam</p>
            </div>
        </template>

        <div class="relative overflow-hidden py-10">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_#ecfeff_0%,_#f8fafc_45%,_#f1f5f9_100%)]" />
            <div class="pointer-events-none absolute -left-24 top-10 h-64 w-64 rounded-full bg-teal-200/40 blur-3xl" />
            <div class="pointer-events-none absolute -right-16 bottom-0 h-72 w-72 rounded-full bg-cyan-200/30 blur-3xl" />

            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-8 text-center sm:mb-10">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-teal-700">Class Selection</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                        Pick your class
                    </h1>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-600 sm:text-base">
                        Class 9 is ready now. Other classes will unlock as content is added.
                    </p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <button
                        v-for="card in cards"
                        :key="card.id"
                        type="button"
                        class="group relative overflow-hidden rounded-3xl text-left transition-all duration-300 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-300"
                        :class="[
                            card.active
                                ? 'cursor-pointer shadow-xl shadow-teal-900/20 ring-2 ring-teal-400/80 ring-offset-2 ring-offset-slate-50 hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-teal-900/30'
                                : 'cursor-not-allowed opacity-50 grayscale',
                        ]"
                        :disabled="!card.active"
                        :aria-disabled="!card.active"
                        @click="selectGrade(card)"
                    >
                        <div
                            class="absolute inset-0 bg-gradient-to-br"
                            :class="card.theme.pattern"
                        />
                        <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.22)_0%,transparent_45%,rgba(0,0,0,0.15)_100%)]" />
                        <div class="absolute -right-8 -top-10 h-36 w-36 rounded-full bg-white/15 blur-2xl transition duration-500 group-hover:scale-125" />
                        <div class="absolute -bottom-10 -left-6 h-28 w-28 rounded-full bg-black/10" />

                        <div class="absolute bottom-4 right-4 opacity-25">
                            <svg width="72" height="72" viewBox="0 0 72 72" fill="none" aria-hidden="true">
                                <rect x="10" y="38" width="42" height="8" rx="2" fill="white" transform="rotate(-8 10 38)" />
                                <rect x="14" y="28" width="40" height="8" rx="2" fill="white" transform="rotate(-4 14 28)" />
                                <rect x="18" y="18" width="38" height="8" rx="2" fill="white" />
                                <circle cx="52" cy="20" r="8" fill="white" fill-opacity="0.5" />
                            </svg>
                        </div>

                        <div
                            class="relative flex flex-col p-6 text-white"
                            :class="card.active ? 'min-h-[260px] sm:min-h-[280px]' : 'min-h-[200px]'"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div
                                    class="flex items-center justify-center rounded-2xl bg-white/20 font-bold backdrop-blur-sm ring-1 ring-white/30"
                                    :class="card.active ? 'h-14 w-14 text-base' : 'h-11 w-11 text-sm'"
                                >
                                    {{ card.displayNumber }}{{ card.suffix }}
                                </div>
                                <span
                                    v-if="card.active"
                                    class="rounded-full bg-emerald-400/90 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-950 shadow-sm"
                                >
                                    Ready to use
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-black/25 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider"
                                >
                                    Coming soon
                                </span>
                            </div>

                            <div class="mt-auto pt-8">
                                <p
                                    class="font-black tracking-tight drop-shadow-sm"
                                    :class="card.active ? 'text-6xl sm:text-7xl' : 'text-5xl sm:text-6xl'"
                                >
                                    {{ card.displayNumber }}<span v-if="card.suffix" class="align-top text-3xl opacity-80">{{ card.suffix }}</span>
                                </p>
                                <p class="mt-1 text-lg font-semibold uppercase tracking-wide text-white/90">
                                    {{ card.ordinal }}
                                </p>
                                <p class="mt-3 text-sm text-white/80" :class="card.active ? 'font-medium' : ''">
                                    {{ card.active ? 'Tap to generate paper →' : 'Content not available yet' }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="card.active"
                            class="absolute inset-x-0 bottom-0 h-1.5 origin-left scale-x-0 bg-white/90 transition duration-300 group-hover:scale-x-100"
                        />
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
