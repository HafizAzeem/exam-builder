<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DiagramArt from '@/Components/Diagrams/DiagramArt.vue';
import DiagramEditor from '@/Components/Diagrams/DiagramEditor.vue';
const props=defineProps({diagrams:Object,categories:Array,filters:Object,canReview:Boolean});
const search=ref(props.filters?.search || '');const category=ref(props.filters?.category || '');const scope=ref(props.filters?.scope || 'shared');
const editing=ref(null);const studio=ref(false);const error=ref('');const busy=ref(false);const deleting=ref(null);const reviewNotes=ref({});
const filter=()=>router.get(route('diagrams.index'),{search:search.value,category:category.value,scope:scope.value},{preserveState:true,preserveScroll:true});
async function action(diagram,verb,data={}) {
    busy.value=true;error.value='';
    try {
        if(verb==='delete') await axios.delete(route('diagrams.destroy',diagram.id));
        else {
            const response=await axios.post(route(`diagrams.${verb}`,diagram.id),data);
            if(verb==='copy') {editing.value=response.data;studio.value=true;}
        }
        deleting.value=null;router.reload({only:['diagrams','categories']});
    } catch(e) {error.value=e.response?.data?.message || 'Unable to complete action.';} finally {busy.value=false;}
}
function edit(diagram=null) {editing.value=diagram;studio.value=true;}
function saved(){studio.value=false;scope.value='mine';filter();}
</script>
<template>
    <Head title="Diagram Library" />
    <AuthenticatedLayout>
        <template #header><div class="flex flex-wrap items-center justify-between gap-3"><div><h1 class="text-xl font-semibold">Diagram library</h1><p class="mt-1 text-sm text-slate-500">Editable teaching visuals shared across institutes. Your drafts stay private.</p></div><button class="rounded-lg bg-teal-700 px-4 py-2 text-white" @click="edit()">Create / upload diagram</button></div></template>
        <div class="mx-auto max-w-7xl px-4 py-8">
            <p v-if="error" role="alert" class="mb-4 rounded bg-red-50 p-3 text-red-700">{{ error }}</p>
            <form class="mb-6 flex flex-wrap gap-3 rounded-xl border bg-white p-4" @submit.prevent="filter">
                <input v-model="search" aria-label="Search diagrams" placeholder="Search shapes, circuits, clocks…" class="min-w-48 flex-1 rounded-lg border-slate-300" />
                <select v-searchable v-model="category" aria-label="Category" class="rounded-lg border-slate-300" @change="filter"><option value="">All categories</option><option v-for="c in categories" :key="c">{{ c }}</option></select>
                <select v-searchable v-model="scope" aria-label="Library" class="rounded-lg border-slate-300" @change="filter"><option value="shared">Shared library</option><option value="mine">My diagrams</option><option v-if="canReview" value="pending">Awaiting review</option></select>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-white">Search</button>
            </form>
            <div v-if="!diagrams.data.length" class="rounded-xl border border-dashed p-12 text-center text-slate-500">No diagrams found. Try another search or create your own.</div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <article v-for="diagram in diagrams.data" :key="diagram.id" class="flex flex-col overflow-hidden rounded-xl border bg-white shadow-sm">
                    <div class="flex h-44 items-center border-b p-3"><DiagramArt :diagram="diagram" /></div>
                    <div class="flex flex-1 flex-col p-4"><div class="flex justify-between gap-2"><h2 class="font-semibold">{{ diagram.title }}</h2><span class="text-xs text-teal-700">{{ diagram.is_builtin?'Built-in':diagram.status }}</span></div><p class="mt-1 text-xs text-slate-500">{{ diagram.category }}</p><p class="mt-2 text-sm text-slate-600">{{ diagram.description }}</p><p v-if="diagram.source" class="mt-2 break-words text-xs text-slate-500">Source: {{ diagram.source }}</p><p v-if="diagram.review_note" class="mt-2 text-sm text-amber-800">Review: {{ diagram.review_note }}</p>
                        <div class="mt-auto flex flex-wrap gap-2 pt-4 text-sm"><button :disabled="busy" class="rounded border px-2 py-1" @click="action(diagram,'copy')">Make my copy</button><button v-if="diagram.can_edit" class="rounded border px-2 py-1" @click="edit(diagram)">Edit</button><button v-if="diagram.can_edit" :disabled="busy" class="rounded bg-teal-50 px-2 py-1 text-teal-800" @click="action(diagram,'submit')">Submit to share</button><button v-if="diagram.can_edit" class="rounded px-2 py-1 text-red-700" @click="deleting=diagram.id">Delete</button></div>
                        <div v-if="deleting===diagram.id" class="mt-3 rounded bg-red-50 p-2 text-sm">Delete this private diagram?<div class="mt-2 flex gap-3"><button :disabled="busy" class="text-red-700" @click="action(diagram,'delete')">Delete</button><button @click="deleting=null">Cancel</button></div></div>
                        <div v-if="canReview && diagram.status==='pending'" class="mt-3 space-y-2 border-t pt-3"><textarea v-model="reviewNotes[diagram.id]" placeholder="Review note" aria-label="Review note" class="w-full rounded border-slate-300 text-sm" /><div class="flex gap-3 text-sm"><button :disabled="busy" class="text-teal-800" @click="action(diagram,'review',{status:'approved',review_note:reviewNotes[diagram.id]})">Approve for everyone</button><button :disabled="busy" class="text-red-700" @click="action(diagram,'review',{status:'rejected',review_note:reviewNotes[diagram.id]})">Return for changes</button></div></div>
                    </div>
                </article>
            </div>
            <nav v-if="diagrams.last_page>1" aria-label="Diagram pages" class="mt-6 flex flex-wrap gap-2"><template v-for="link in diagrams.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="rounded border px-3 py-2" :class="link.active?'bg-teal-700 text-white':'bg-white'" preserve-scroll>{{ link.label.replace(/&(?:amp;)?laquo;/g,'«').replace(/&(?:amp;)?raquo;/g,'»') }}</Link><span v-else class="px-3 py-2 text-slate-400">{{ link.label.replace(/&(?:amp;)?laquo;/g,'«').replace(/&(?:amp;)?raquo;/g,'»') }}</span></template></nav>
            <p class="mt-6 text-sm text-slate-500">Insert diagrams from the “Add diagram” controls in your paper preview. Shared originals stay unchanged when you edit a copy.</p>
        </div>
        <DiagramEditor v-if="studio" :diagram="editing" @close="studio=false" @saved="saved" />
    </AuthenticatedLayout>
</template>
