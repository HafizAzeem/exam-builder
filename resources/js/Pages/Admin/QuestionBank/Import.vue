<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    file: null,
});
</script>

<template>
    <Head title="Question Bank Import" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Import Question Bank</h2>
                <Link :href="route('admin.question-bank.index')" class="rounded bg-gray-200 px-4 py-2 text-sm">Back</Link>
            </div>
        </template>

        <div class="mx-auto max-w-3xl px-4 py-8">
            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-base font-semibold">Upload CSV/XLSX</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Headings supported: <code>grade, subject_en, subject_ur, chapter, chapter_title_en, chapter_title_ur, type, source, text_en, text_ur, image_path,
                    option_a_en, option_b_en, option_c_en, option_d_en, correct_option, board_name, year, session, parent_key, part_label</code>
                </p>

                <div class="mt-4">
                    <input
                        type="file"
                        accept=".csv,.txt,.xlsx,.xls"
                        @change="form.file = $event.target.files[0]"
                    />
                </div>

                <button
                    class="mt-6 rounded bg-indigo-600 px-4 py-2 text-white"
                    :disabled="form.processing || !form.file"
                    @click="form.post(route('admin.question-bank.import'), { forceFormData: true })"
                >
                    Import
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

