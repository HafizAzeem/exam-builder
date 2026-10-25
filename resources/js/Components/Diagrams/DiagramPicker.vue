<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import DiagramArt from './DiagramArt.vue';
const emit=defineEmits(['select','close']);
const search=ref('');const scope=ref('shared');const data=ref([]);const page=ref(1);const last=ref(1);const busy=ref(false);const error=ref('');
async function load(next=1) {busy.value=true;error.value='';try{const response=await axios.get(route('diagrams.index'),{params:{search:search.value,scope:scope.value,page:next},headers:{Accept:'application/json'}});data.value=response.data.data;page.value=response.data.current_page;last.value=response.data.last_page;}catch(e){error.value='Could not load diagrams.';}finally{busy.value=false;}}
async function select(diagram) {
    busy.value=true;error.value='';
    try {
        const snapshot={title:diagram.title,elements:diagram.elements || [],width:240};
        if(diagram.image_url) {
            const response=await axios.get(diagram.image_url,{responseType:'blob'});
            snapshot.image_data=await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.onerror=reject;reader.readAsDataURL(response.data);});
        }
        emit('select',snapshot);
    }catch(e){error.value='Could not attach this image. Please try again.';}finally{busy.value=false;}
}
onMounted(()=>load());
</script>
<template>
    <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-4 sm:p-10" role="dialog" aria-modal="true" aria-label="Choose diagram" @keydown.esc.stop.prevent="emit('close')">
        <div class="mx-auto max-w-4xl rounded-xl bg-white p-5"><div class="mb-4 flex justify-between"><h2 class="text-lg font-semibold">Choose a diagram</h2><button aria-label="Close diagram picker" @click="emit('close')">✕</button></div>
            <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="load()"><input v-model="search" placeholder="Search diagrams" aria-label="Search diagrams" class="flex-1 rounded border-slate-300" /><select v-model="scope" aria-label="Library" class="rounded border-slate-300" @change="load()"><option value="shared">Shared library</option><option value="mine">My diagrams</option></select><button :disabled="busy" class="rounded bg-teal-700 px-4 text-white">Search</button></form>
            <p v-if="error" role="alert" class="p-3 text-red-700">{{ error }}</p><p v-if="busy" class="py-3 text-slate-500">Loading…</p>
            <div class="grid gap-3 sm:grid-cols-3"><button v-for="diagram in data" :key="diagram.id" :disabled="busy" class="rounded-lg border p-3 text-left hover:border-teal-600 disabled:opacity-50" @click="select(diagram)"><DiagramArt :diagram="diagram" /><span class="mt-2 block text-sm font-medium">{{ diagram.title }}</span></button></div>
            <p v-if="!data.length && !busy" class="py-8 text-center text-slate-500">No diagrams found.</p>
            <div class="mt-4 flex items-center justify-between"><button :disabled="busy || page<=1" class="rounded border px-3 py-2 disabled:opacity-40" @click="load(page-1)">Previous</button><span>{{ page }} / {{ last }}</span><button :disabled="busy || page>=last" class="rounded border px-3 py-2 disabled:opacity-40" @click="load(page+1)">Next</button></div>
            <a :href="route('diagrams.index')" target="_blank" rel="noopener" class="mt-4 inline-block text-sm text-teal-700">Open library to create or upload a diagram ↗</a>
        </div>
    </div>
</template>
