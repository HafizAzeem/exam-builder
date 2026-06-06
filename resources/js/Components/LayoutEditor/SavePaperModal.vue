<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

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
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-start justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Save paper</h3>
                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600"
                    aria-label="Close"
                    @click="emit('close')"
                >
                    ✕
                </button>
            </div>
            <p class="mt-1 text-sm text-gray-500">
                Update how this paper appears on the header and in your saved list.
            </p>
            <p
                v-if="paperClass || paperSubject"
                class="mt-2 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600"
            >
                <span v-if="paperClass"><strong>Class:</strong> {{ paperClass }}</span>
                <span v-if="paperClass && paperSubject"> · </span>
                <span v-if="paperSubject"><strong>Subject:</strong> {{ paperSubject }}</span>
                <span class="mt-1 block text-xs text-gray-500">Set when the paper was created — not editable here.</span>
            </p>

            <form class="mt-5 space-y-4" @submit.prevent="emit('submit')">
                <div>
                    <label class="text-sm font-medium text-gray-700">Paper name</label>
                    <input
                        v-model="form.title"
                        type="text"
                        required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Paper type</label>
                        <input
                            v-model="form.paper_type"
                            type="text"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
                            placeholder="Mid Term, DTS, …"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Paper date</label>
                        <input
                            v-model="form.paper_date"
                            type="date"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
                        />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Time allowed</label>
                        <input
                            v-model="form.time_allowed"
                            type="text"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
                            placeholder="2 Hours"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700">Total marks</label>
                        <input
                            v-model="form.total_marks"
                            type="text"
                            class="mt-1 w-full rounded-md border-gray-300 shadow-sm"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <PrimaryButton :disabled="processing">
                        {{ processing ? 'Saving…' : 'Save' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
