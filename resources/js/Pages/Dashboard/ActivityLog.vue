<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({ logs: Object, filters: Object, people: Array, actions: Array });
const filters = reactive({ user_id: props.filters?.user_id ?? '', action: props.filters?.action ?? '' });
const apply = () => router.get(route('admin.logs'), filters, { preserveState: true, preserveScroll: true });
function reset() { filters.user_id = ''; filters.action = ''; apply(); }
const time = value => new Date(value).toLocaleString('en-PK', { timeZone: 'Asia/Karachi', dateStyle: 'medium', timeStyle: 'short' });
const initials = name => name.split(/\s+/).slice(0, 2).map(word => word[0]).join('').toUpperCase();
</script>

<template>
    <Head title="Activity Logs" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800">Activity Logs</h2></template>
        <div class="mx-auto max-w-6xl space-y-5 px-4 py-8 sm:px-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><h1 class="text-2xl font-bold text-slate-900">Who changed what</h1><p class="mt-1 text-sm text-slate-600">Successful changes made by your school team. Page views are excluded.</p></div>
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600">{{ logs.total }} {{ logs.total === 1 ? 'activity' : 'activities' }}</span>
            </div>
            <form @submit.prevent="apply" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
                <div class="min-w-48 flex-1"><label for="activity-person" class="mb-1.5 block text-sm font-medium text-slate-700">Person</label><select id="activity-person" v-model="filters.user_id" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Everyone</option><option v-for="person in people" :key="person.id" :value="person.id">{{ person.name }}</option></select></div>
                <div class="min-w-56 flex-1"><label for="activity-action" class="mb-1.5 block text-sm font-medium text-slate-700">Action</label><select id="activity-action" v-model="filters.action" class="w-full rounded-lg border-slate-300 text-sm"><option value="">All actions</option><option v-for="action in actions" :key="action.value" :value="action.value">{{ action.label }}</option></select></div>
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply filters</button>
                <button v-if="filters.user_id || filters.action" type="button" @click="reset" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600">Clear</button>
            </form>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="hidden grid-cols-[1fr_1.6fr_1fr] gap-5 border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 md:grid"><span>Performed by</span><span>What happened</span><span>When · Pakistan time</span></div>
                <div v-for="log in logs.data" :key="log.id" class="grid gap-3 border-b border-slate-100 px-5 py-5 last:border-b-0 md:grid-cols-[1fr_1.6fr_1fr] md:gap-5">
                    <div class="flex items-center gap-3"><span aria-hidden="true" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-bold text-indigo-700">{{ initials(log.actor_name) }}</span><div><p class="font-semibold text-slate-900">{{ log.actor_name }}</p><p class="text-xs text-slate-500">{{ log.actor_role }}</p></div></div>
                    <div><p class="text-sm font-medium leading-6 text-slate-800">{{ log.description }}</p><details class="mt-1 text-xs text-slate-500"><summary class="w-fit cursor-pointer rounded focus-visible:outline-indigo-600">Technical details</summary><dl class="mt-2 space-y-1 break-all"><div v-if="log.method"><dt class="inline font-medium">Method: </dt><dd class="inline">{{ log.method }}</dd></div><div v-if="log.path"><dt class="inline font-medium">Page: </dt><dd class="inline">{{ log.path }}</dd></div><div><dt class="inline font-medium">IP address: </dt><dd class="inline">{{ log.ip_address }}</dd></div><div v-if="log.legacy">Older record: person and action details may be limited.</div></dl></details></div>
                    <time :datetime="log.created_at" class="text-sm text-slate-500">{{ time(log.created_at) }}</time>
                </div>
                <div v-if="!logs.data.length" class="px-6 py-14 text-center"><p class="font-semibold text-slate-800">No activity found</p><p class="mt-2 text-sm text-slate-500">{{ filters.user_id || filters.action ? 'Try clearing your filters to see more activity.' : 'Changes made by your school team will appear here.' }}</p></div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500"><p v-if="logs.total">Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }}</p><nav v-if="logs.links?.length > 3" aria-label="Activity pages" class="flex flex-wrap gap-1"><template v-for="(link, index) in logs.links" :key="index"><Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-lg border px-3 py-2" :class="link.active ? 'border-indigo-600 bg-indigo-50 font-semibold text-indigo-700' : 'border-slate-200 bg-white hover:bg-slate-50'" :aria-current="link.active ? 'page' : undefined"><span v-html="link.label" /></Link><span v-else class="rounded-lg border border-slate-100 px-3 py-2 text-slate-400" v-html="link.label" /></template></nav></div>
        </div>
    </AuthenticatedLayout>
</template>
