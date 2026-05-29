<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({
    file: null,
    images_zip: null,
    images: [],
});

const imageFiles = ref([]);

const onImagesChange = (e) => {
    imageFiles.value = Array.from(e.target.files || []);
    form.images = imageFiles.value;
};

const onZipChange = (e) => {
    form.images_zip = e.target.files?.[0] ?? null;
};
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

        <div class="mx-auto max-w-3xl space-y-6 px-4 py-8">
            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-base font-semibold">1. Upload CSV / Excel</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Required columns include grade, subject_en, chapter, type, source, text_en. Use
                    <code>image_filename</code> or <code>image_path</code> to link images (see below).
                </p>
                <input
                    type="file"
                    accept=".csv,.txt,.xlsx,.xls"
                    class="mt-4 block w-full text-sm"
                    @change="form.file = $event.target.files[0]"
                />
            </div>

            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-base font-semibold">2. Images (optional)</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Put the filename in your sheet (e.g. <code>q12.png</code>) and upload matching files via ZIP or multi-select.
                </p>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Images ZIP</label>
                    <input type="file" accept=".zip" class="mt-1 block w-full text-sm" @change="onZipChange" />
                    <p class="mt-1 text-xs text-gray-500">ZIP can contain nested folders; filenames are matched to the CSV column.</p>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Or select multiple image files</label>
                    <input
                        type="file"
                        accept="image/*"
                        multiple
                        class="mt-1 block w-full text-sm"
                        @change="onImagesChange"
                    />
                    <p v-if="imageFiles.length" class="mt-1 text-xs text-green-700">{{ imageFiles.length }} file(s) selected</p>
                </div>
            </div>

            <div class="rounded-lg bg-gray-50 p-4 text-xs text-gray-600">
                <p class="font-medium text-gray-800">Supported headings</p>
                <p class="mt-1">
                    grade, subject_en, subject_ur, chapter, chapter_title_en, chapter_title_ur, type, source, text_en, text_ur,
                    image_filename, image_path, option_a_en, option_b_en, option_c_en, option_d_en, correct_option,
                    board_name, year, session, parent_key, part_label
                </p>
            </div>

            <button
                class="rounded bg-indigo-600 px-4 py-2 text-white disabled:opacity-50"
                :disabled="form.processing || !form.file"
                @click="form.post(route('admin.question-bank.import'), { forceFormData: true })"
            >
                Run Import
            </button>
        </div>
    </AuthenticatedLayout>
</template>
