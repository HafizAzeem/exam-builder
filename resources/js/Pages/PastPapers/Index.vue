<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ tags: Object, boards: Array, years: Array, grades: Array, subjects: Array, filters: Object });

const form = ref({
    board: props.filters?.board ?? '',
    year: props.filters?.year ?? '',
    session: props.filters?.session ?? '',
    grade_id: props.filters?.grade_id ?? '',
    subject_id: props.filters?.subject_id ?? '',
});

const filteredSubjects = computed(() => {
    if (!form.value.grade_id) return props.subjects ?? [];
    return (props.subjects ?? []).filter((s) => String(s.grade_id) === String(form.value.grade_id));
});

const apply = () => {
    router.get(route('past-papers.index'), { ...form.value }, { preserveState: true, preserveScroll: true });
};

const reset = () => {
    form.value = { board: '', year: '', session: '', grade_id: '', subject_id: '' };
    apply();
};
</script>

<template>
    <Head title="Past Papers" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Past Papers Repository</h2></template>
        <div class="mx-auto max-w-5xl px-4 py-8">
            <div class="mb-6 grid gap-3 rounded-lg bg-white p-4 shadow md:grid-cols-6">
                <select v-model="form.board" class="rounded border-gray-300 md:col-span-2">
                    <option value="">All boards</option>
                    <option v-for="b in boards" :key="b" :value="b">{{ b }}</option>
                </select>
                <select v-model="form.year" class="rounded border-gray-300">
                    <option value="">All years</option>
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
                <select v-model="form.session" class="rounded border-gray-300">
                    <option value="">Any session</option>
                    <option value="morning">Morning</option>
                    <option value="evening">Evening</option>
                </select>
                <select v-model="form.grade_id" class="rounded border-gray-300">
                    <option value="">All grades</option>
                    <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.label_en }}</option>
                </select>
                <select v-model="form.subject_id" class="rounded border-gray-300" :disabled="!filteredSubjects.length">
                    <option value="">All subjects</option>
                    <option v-for="s in filteredSubjects" :key="s.id" :value="s.id">{{ s.name_en }}</option>
                </select>

                <button class="rounded bg-indigo-600 px-3 py-2 text-sm text-white" @click="apply">Filter</button>
                <button class="rounded bg-gray-200 px-3 py-2 text-sm" @click="reset">Reset</button>
            </div>

            <div class="space-y-3">
                <div v-for="tag in tags.data" :key="tag.id" class="flex items-center justify-between rounded-lg bg-white p-4 shadow">
                    <div>
                        <p class="font-medium">
                            {{ tag.board_name }} — {{ tag.year }}
                            <span v-if="tag.session" class="ms-2 rounded bg-gray-100 px-2 py-0.5 text-xs uppercase">{{ tag.session }}</span>
                        </p>
                        <p class="text-sm text-gray-600">{{ tag.question?.text_en }}</p>
                        <p class="text-xs text-gray-500">
                            {{ tag.question?.chapter?.subject?.grade?.label_en }} · {{ tag.question?.chapter?.subject?.name_en }}
                        </p>
                    </div>
                    <Link :href="route('past-papers.print', tag.question_id)" class="text-indigo-600 hover:underline">Quick Print</Link>
                </div>
                <p v-if="!tags.data?.length" class="text-center text-gray-500">No past papers in database yet.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
