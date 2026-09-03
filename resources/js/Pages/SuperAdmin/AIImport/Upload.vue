<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    grades: { type: Array, required: true },
    subjects: { type: Array, required: true },
});

const boards = [
    'Lahore Board',
    'Federal Board',
    'Karachi Board',
    'Rawalpindi Board',
    'Multan Board',
    'Faisalabad Board',
    'Gujranwala Board',
    'Bahawalpur Board',
    'Sargodha Board',
    'DG Khan Board',
    'Sahiwal Board',
    'Hyderabad Board',
    'Sukkur Board',
    'Larkana Board',
    'Mirpurkhas Board',
    'Quetta Board',
    'Peshawar Board',
    'Abbottabad Board',
    'Swat Board',
    'Malakand Board',
    'DI Khan Board',
    'Bannu Board',
    'Kohat Board',
    'AJK Board',
];

const form = useForm({
    grade_id: '',
    subject_id: '',
    book_type: 'text_book',
    board: 'Lahore Board',
    year: '',
    session: '',
    language: 'english',
    file: null,
});

const isPastPaper = computed(() => form.book_type === 'past_paper');

const filteredSubjects = computed(() =>
    props.subjects.filter((s) => !form.grade_id || String(s.grade_id) === String(form.grade_id))
);

const onBookTypeChange = () => {
    if (!isPastPaper.value) {
        form.year = '';
        form.session = '';
    }
};

const submit = () => {
    form.post(route('super-admin.ai-import.store'), { forceFormData: true });
};
</script>

<template>
    <Head title="Upload for AI Import" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Upload Document</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Grade" />
                            <select v-model="form.grade_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" @change="form.subject_id = ''">
                                <option value="">Select grade</option>
                                <option v-for="g in grades" :key="g.id" :value="g.id">{{ g.label_en }}</option>
                            </select>
                            <InputError :message="form.errors.grade_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Subject" />
                            <select v-model="form.subject_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select subject</option>
                                <option v-for="s in filteredSubjects" :key="s.id" :value="s.id">{{ s.name_en }}</option>
                            </select>
                            <InputError :message="form.errors.subject_id" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Book Type" />
                            <select
                                v-model="form.book_type"
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
                                @change="onBookTypeChange"
                            >
                                <option value="text_book">Text Book</option>
                                <option value="past_paper">Past Paper</option>
                                <option value="additional_questions">Additional Questions</option>
                            </select>
                            <InputError :message="form.errors.book_type" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Language" />
                            <select v-model="form.language" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="english">English</option>
                                <option value="urdu">Urdu</option>
                                <option value="bilingual">Bilingual</option>
                            </select>
                            <InputError :message="form.errors.language" class="mt-1" />
                        </div>
                    </div>

                    <div v-if="isPastPaper" class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel value="Board" />
                            <select v-model="form.board" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option v-for="b in boards" :key="b" :value="b">{{ b }}</option>
                            </select>
                            <InputError :message="form.errors.board" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Year (optional)" />
                            <TextInput v-model="form.year" type="number" class="mt-1 block w-full" />
                            <InputError :message="form.errors.year" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Session" />
                            <select v-model="form.session" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">—</option>
                                <option value="morning">Morning</option>
                                <option value="evening">Evening</option>
                            </select>
                            <InputError :message="form.errors.session" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="File (PDF, DOCX, TXT — max 20MB)" />
                        <input
                            type="file"
                            accept=".pdf,.docx,.txt,application/pdf,text/plain,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            class="mt-1 block w-full text-sm text-gray-600"
                            @input="form.file = $event.target.files[0]"
                        >
                        <InputError :message="form.errors.file" class="mt-1" />
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">
                            Upload & Process
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
