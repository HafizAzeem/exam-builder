<script setup>
import Modal from '@/Components/Modal.vue';

defineProps({
    show: Boolean,
    form: Object,
    processing: Boolean,
    paperClass: String,
    paperSubject: String,
});

const emit = defineEmits(['close', 'submit']);
</script>

<template>
    <Modal :show="show" max-width="xl" @close="emit('close')">
        <div class="overflow-hidden rounded-2xl bg-white">
            <div class="relative bg-slate-900 px-6 py-6 sm:px-8">
                <div
                    class="pointer-events-none absolute inset-0 opacity-40"
                    style="background:
                        radial-gradient(ellipse 80% 60% at 10% 20%, rgba(16,185,129,0.35), transparent 55%),
                        radial-gradient(ellipse 70% 50% at 90% 80%, rgba(20,184,166,0.25), transparent 50%);"
                />
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300/90">
                            Finalize paper
                        </p>
                        <h3 class="mt-1 text-2xl font-bold tracking-tight text-white">
                            Save Paper
                        </h3>
                        <p class="mt-2 max-w-md text-sm text-slate-300">
                            Confirm the header details before saving. These show on the paper and in your saved list.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-white/10 hover:text-white"
                        aria-label="Close"
                        @click="emit('close')"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div
                    v-if="paperClass || paperSubject"
                    class="relative mt-5 flex flex-wrap gap-2"
                >
                    <span
                        v-if="paperClass"
                        class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white"
                    >
                        Class · {{ paperClass }}
                    </span>
                    <span
                        v-if="paperSubject"
                        class="inline-flex items-center rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold text-white"
                    >
                        Subject · {{ paperSubject }}
                    </span>
                </div>
            </div>

            <form class="px-6 py-6 sm:px-8 sm:py-7" @submit.prevent="emit('submit')">
                <div class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-slate-800">
                            Paper name
                        </label>
                        <input
                            v-model="form.title"
                            type="text"
                            required
                            placeholder="e.g. Mid Term Examination"
                            class="w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 text-base text-slate-900 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-emerald-500"
                        />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-800">
                                Paper type
                            </label>
                            <input
                                v-model="form.paper_type"
                                type="text"
                                placeholder="Mid Term, DTS, Final…"
                                class="w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 text-base text-slate-900 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-emerald-500"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-800">
                                Paper date
                            </label>
                            <input
                                v-model="form.paper_date"
                                type="date"
                                class="w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 text-base text-slate-900 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-emerald-500"
                            />
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-800">
                                Time allowed
                            </label>
                            <input
                                v-model="form.time_allowed"
                                type="text"
                                placeholder="2 Hours"
                                class="w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 text-base text-slate-900 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-emerald-500"
                            />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold text-slate-800">
                                Total marks
                            </label>
                            <input
                                v-model="form.total_marks"
                                type="text"
                                placeholder="50"
                                class="w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 text-base text-slate-900 shadow-sm transition focus:border-emerald-500 focus:bg-white focus:ring-emerald-500"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="processing"
                    >
                        <svg
                            v-if="!processing"
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        {{ processing ? 'Saving…' : 'Save Paper' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
