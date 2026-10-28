<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ plans: Array, trialDays: Number });
const form = useForm({ trial_days: props.trialDays, plans: props.plans.map(plan => ({ ...plan })) });
const fields = [
    { key: 'key', label: 'Identifier', type: 'text', max: 50 },
    { key: 'label', label: 'English name', type: 'text', max: 100 },
    { key: 'label_ur', label: 'Urdu name', type: 'text', max: 150 },
    { key: 'amount', label: 'Price (PKR)', type: 'number', min: 1, max: 10000000 },
    { key: 'months', label: 'Duration (calendar months)', type: 'number', min: 1, max: 60 },
    { key: 'sort_order', label: 'Display order', type: 'number', min: 0, max: 9999 },
];
function add() {
    form.plans.push({ key: '', label: '', label_ur: '', amount: 500, months: 1, sort_order: form.plans.length + 1, is_active: true });
}
function save() {
    form.put(route('super-admin.plans.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Subscription plans" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <h1 class="text-3xl font-bold text-slate-950">Subscription plans</h1>
            <p class="mt-2 max-w-3xl leading-7 text-slate-600">Manage prices and durations across public pricing, school renewals and manual activation. Changes apply to future subscriptions; existing expiry dates and recorded payments stay unchanged.</p>
            <form class="mt-8 space-y-6" @submit.prevent="save">
                <section class="rounded-2xl border bg-white p-6">
                    <h2 class="text-lg font-bold">Free trial</h2>
                    <label for="trial-days" class="mt-4 block text-sm font-semibold">Trial duration for new schools (days)</label>
                    <input id="trial-days" v-model.number="form.trial_days" type="number" min="1" max="90" required class="mt-2 w-full max-w-xs rounded-lg border-slate-300" />
                    <InputError :message="form.errors.trial_days" class="mt-2" />
                </section>
                <InputError :message="form.errors.plans" />
                <section v-for="(plan, index) in form.plans" :key="index" class="rounded-2xl border bg-white p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">{{ plan.label || 'New plan' }}</h2>
                        <button v-if="index >= props.plans.length" type="button" @click="form.plans.splice(index, 1)" class="text-sm font-semibold text-red-700">Remove unsaved plan</button>
                    </div>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="field in fields" :key="field.key">
                            <label :for="`plan-${index}-${field.key}`" class="block text-sm font-semibold">{{ field.label }}</label>
                            <input :id="`plan-${index}-${field.key}`" v-model="plan[field.key]" :type="field.type" :readonly="field.key === 'key' && index < props.plans.length" :maxlength="field.type === 'text' ? field.max : undefined" :min="field.min" :max="field.type === 'number' ? field.max : undefined" :step="field.type === 'number' ? 1 : undefined" :dir="field.key === 'label_ur' ? 'rtl' : 'ltr'" required class="mt-2 w-full rounded-lg border-slate-300 read-only:bg-slate-100" />
                            <InputError :message="form.errors[`plans.${index}.${field.key}`]" class="mt-2" />
                        </div>
                    </div>
                    <label class="mt-5 inline-flex items-center gap-2 text-sm font-semibold"><input v-model="plan.is_active" type="checkbox" class="rounded border-slate-300 text-blue-600" /> Available for new subscriptions</label>
                    <InputError :message="form.errors[`plans.${index}.is_active`]" class="mt-2" />
                    <p class="mt-3 text-sm text-slate-500">Existing identifiers are permanent. Disable a plan to stop offering it. Keep at least one plan available.</p>
                </section>
                <div class="flex flex-wrap justify-between gap-3">
                    <button type="button" :disabled="form.processing || form.plans.length >= 30" @click="add" class="rounded-xl border bg-white px-5 py-3 font-semibold disabled:opacity-50">Add plan</button>
                    <button :disabled="form.processing" class="rounded-xl bg-blue-600 px-6 py-3 font-bold text-white disabled:opacity-50">{{ form.processing ? 'Saving…' : 'Save plans and trial' }}</button>
                </div>
                <p v-if="form.recentlySuccessful" role="status" class="font-semibold text-emerald-700">Plans and trial settings saved.</p>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
