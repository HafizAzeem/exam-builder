<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
const props = defineProps({ schools: Object, filters: Object, plans: Object });
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const selected = ref(null);
const verifyBusy = ref(false);
const form = useForm({ action: 'renew', plan: 'monthly', weeks: 2, payment_reference: '', note: '', request_id: '', confirmed: true });
const date = value => value ? new Date(value).toLocaleString('en-PK', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
const apply = () => router.get(route('super-admin.schools.index'), { search: search.value, status: status.value }, { preserveState: true });
function open(school) { selected.value = school; form.reset(); form.clearErrors(); form.plan = Object.keys(props.plans)[0]; form.request_id = crypto.randomUUID(); }
function save() {
    const adjustment = form.action === 'renew' ? `${props.plans[form.plan].label} (${props.plans[form.plan].months} calendar months) for Rs ${props.plans[form.plan].amount.toLocaleString()}` : form.weeks + ' additional trial weeks';
    if (!confirm('Apply ' + adjustment + ' to ' + selected.value.name + '? Confirm payment has been checked for a paid renewal.')) return;
    form.post(route('super-admin.schools.adjust', selected.value.id), { preserveScroll: true, onSuccess: () => { selected.value = null; } });
}
function verify(school, owner) {
    if (verifyBusy.value || !confirm('Mark ' + owner.email + ' as verified for ' + school.name + '?')) return;
    verifyBusy.value = true;
    router.post(route('super-admin.schools.verify-owner', school.id), { user_id: owner.id, request_id: crypto.randomUUID(), confirmed: true }, { preserveScroll: true, onFinish: () => { verifyBusy.value = false; } });
}
</script>
<template>
    <Head title="Schools & subscriptions" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <h1 class="text-3xl font-bold text-slate-950">Schools & subscriptions</h1>
            <p class="mt-2 text-slate-600">Review accounts, verify owners, and activate access after checking payment on WhatsApp.</p>
            <form @submit.prevent="apply" class="my-6 flex flex-wrap gap-3"><input v-model="search" aria-label="Search schools" placeholder="School, owner, email or phone" class="min-w-0 flex-1 rounded-xl border-slate-300" /><select v-model="status" aria-label="School status" class="rounded-xl border-slate-300"><option value="">All schools</option><option value="trial">Active trial</option><option value="active">Active access</option><option value="expired">Expired</option><option value="unverified">Unverified owner</option></select><button class="rounded-xl bg-blue-600 px-5 py-2 font-semibold text-white">Search</button></form>
            <div v-if="!schools.data.length" class="rounded-2xl border bg-white p-10 text-center text-slate-500">No schools match these filters.</div>
            <div class="space-y-5">
                <article v-for="school in schools.data" :key="school.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-4"><img v-if="school.logo_url" :src="school.logo_url" alt="School logo" class="h-14 w-14 rounded-xl border object-contain p-1" /><div><h2 class="text-xl font-bold text-slate-950">{{ school.name }}</h2><p class="mt-1 text-sm text-slate-500">EB-{{ school.id }} · {{ school.board ?? 'Board not set' }}</p></div></div>
                        <div class="flex flex-wrap items-center gap-3"><span class="rounded-full px-3 py-1 text-xs font-bold" :class="school.expired ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'">{{ school.expired ? 'Expired' : school.plan === 'trial' ? 'Trial' : 'Active' }}</span><button @click="open(school)" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Manage access</button></div>
                    </div>
                    <p class="mt-4 text-sm text-slate-600">Plan: <strong>{{ school.plan }}</strong> · Access until <strong>{{ date(school.expires_at) }}</strong></p>
                    <div v-for="owner in school.owners" :key="owner.id" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-4 text-sm"><div><p class="font-semibold">{{ owner.name }}</p><p class="break-all text-slate-600">{{ owner.email }} · {{ owner.phone }}</p></div><span v-if="owner.email_verified_at" class="font-medium text-emerald-700">Email verified</span><button v-else :disabled="verifyBusy" @click="verify(school, owner)" class="font-semibold text-blue-700 underline disabled:opacity-50">Verify email manually</button></div>
                    <details class="mt-4 text-sm"><summary class="cursor-pointer font-semibold text-slate-600">Recent account history</summary><p v-if="!school.history.length" class="mt-3 text-slate-500">No manual changes recorded.</p><div v-for="event in school.history" :key="event.id" class="mt-3 rounded-lg border p-3"><p class="font-medium">{{ event.action.replaceAll('_', ' ') }} · {{ event.administrator_name ?? 'Deleted administrator' }}</p><p class="mt-1 text-slate-500">{{ date(event.created_at) }} · Rs {{ event.amount }}<span v-if="event.payment_reference"> · {{ event.payment_reference }}</span></p><p v-if="event.new_expires_at" class="mt-1 text-slate-600">Expiry: {{ date(event.previous_expires_at) }} → {{ date(event.new_expires_at) }}</p><p v-if="event.note" class="mt-1 text-slate-600">{{ event.note }}</p></div></details>
                </article>
            </div>
            <nav class="mt-6 flex flex-wrap gap-2" aria-label="School pages"><template v-for="(link, index) in schools.links" :key="index"><Link v-if="link.url" :href="link.url" class="rounded-lg border px-3 py-2 text-sm" :class="link.active ? 'bg-blue-600 text-white' : 'bg-white text-slate-700'" v-html="link.label" /></template></nav>
        </div>
        <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4" @click.self="!form.processing && (selected = null)">
            <section role="dialog" aria-modal="true" aria-labelledby="access-title" class="my-auto max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6">
                <h2 id="access-title" class="text-xl font-bold">Manage {{ selected.name }}</h2>
                <form @submit.prevent="save" class="mt-5 space-y-4">
                    <div><label for="action" class="block text-sm font-semibold">Action</label><select id="action" v-model="form.action" class="mt-1 w-full rounded-lg border-slate-300"><option value="renew">Activate paid subscription</option><option value="extend_trial">Add trial weeks</option></select></div>
                    <div v-if="form.action === 'renew'"><label for="plan" class="block text-sm font-semibold">Payment received</label><select id="plan" v-model="form.plan" class="mt-1 w-full rounded-lg border-slate-300"><option v-for="(plan, key) in plans" :key="key" :value="key">{{ plan.label }} — Rs {{ plan.amount.toLocaleString() }} / {{ plan.months }} months</option></select></div>
                    <div v-else><label for="weeks" class="block text-sm font-semibold">Additional weeks</label><input id="weeks" v-model="form.weeks" type="number" min="1" max="520" required class="mt-1 w-full rounded-lg border-slate-300" /><p class="mt-1 text-xs text-slate-500">Use 2, 3, 10, or any whole number up to 520.</p></div>
                    <p class="rounded-lg bg-blue-50 p-3 text-sm text-blue-950">Time is added from the later of now or the current expiry. Paid plans use calendar months.</p>
                    <div><label for="reference" class="block text-sm font-semibold">Payment reference (optional)</label><input id="reference" v-model="form.payment_reference" maxlength="255" class="mt-1 w-full rounded-lg border-slate-300" /></div>
                    <div><label for="note" class="block text-sm font-semibold">Note (optional)</label><textarea id="note" v-model="form.note" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-300" /></div>
                    <InputError v-for="(error, key) in form.errors" :key="key" :message="error" />
                    <div class="flex justify-end gap-3"><button type="button" :disabled="form.processing" @click="selected = null" class="rounded-lg border px-4 py-2">Cancel</button><button :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Applying…' : 'Review & apply' }}</button></div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
