<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import axios from 'axios';
import DiagramArt from './DiagramArt.vue';
const props = defineProps({ diagram: Object });
const emit = defineEmits(['close','saved']);
const clone = (v) => JSON.parse(JSON.stringify(v));
const elements = ref(clone(props.diagram?.elements || []));
const title = ref(props.diagram?.title || '');
const category = ref(props.diagram?.category || 'Geometry');
const description = ref(props.diagram?.description || '');
const source = ref(props.diagram?.source || '');
const selected = ref([]);
const current = computed(() => elements.value[selected.value[0]]);
const upload = ref(null);
const imageUrl = ref(props.diagram?.image_url || '');
const imagePreview = ref(null);
const saving = ref(false);
const error = ref('');
const history = ref([clone(elements.value)]);
const position = ref(0);
const freehand = ref(false);
const crop = ref({ x:0, y:0, width:100, height:100 });
let drag = null;
const checkpoint = () => { history.value = history.value.slice(0,position.value+1); history.value.push(clone(elements.value)); if (history.value.length > 60) history.value.shift(); position.value = history.value.length-1; };
const undo = () => { if (position.value) elements.value = clone(history.value[--position.value]); selected.value=[]; };
const redo = () => { if (position.value < history.value.length-1) elements.value=clone(history.value[++position.value]); selected.value=[]; };
function add(kind) {
    imageUrl.value=''; upload.value=null;
    const base = { kind, stroke:'#111111', fill:'none', strokeWidth:2, rotation:0 };
    const geometry = { rect:{x:130,y:70,width:140,height:100}, ellipse:{x:200,y:120,rx:65,ry:65}, line:{x:80,y:120,x2:320,y2:120}, arrow:{x:80,y:120,x2:320,y2:120}, polygon:{points:'200,35 90,200 310,200'}, text:{x:140,y:120,text:'Label',fontSize:20} };
    elements.value.push({...base,...geometry[kind]}); selected.value=[elements.value.length-1]; checkpoint();
}
function point(event) {
    const svg = event.target.closest('svg');
    const rect = svg.getBoundingClientRect();
    return {x:(event.clientX-rect.left)*400/rect.width,y:(event.clientY-rect.top)*240/rect.height};
}
function begin({index,event}) {
    if (freehand.value) return stage(event);
    event.preventDefault(); event.stopPropagation();
    if (event.shiftKey) { selected.value=selected.value.includes(index)?selected.value.filter(i=>i!==index):[...selected.value,index]; return; }
    if (!selected.value.includes(index)) selected.value=[index];
    drag={ start:point(event), original:clone(elements.value), indices:[...selected.value] };
    window.addEventListener('pointermove', move); window.addEventListener('pointerup', end, {once:true});
}
function stage(event) {
    if (!freehand.value) { selected.value=[]; return; }
    event.preventDefault();
    const at=point(event);
    elements.value.push({kind:'polyline',points:`${at.x.toFixed(1)},${at.y.toFixed(1)}`,stroke:'#111111',fill:'none',strokeWidth:2});
    selected.value=[elements.value.length-1];
    drag={ drawing:true, svg:event.target.closest('svg') };
    window.addEventListener('pointermove', move); window.addEventListener('pointerup', end, {once:true});
}
function move(event) {
    if (!drag) return;
    const svg=drag.svg || document.querySelector('[data-diagram-editor] svg');
    if (!svg) return;
    const r=svg.getBoundingClientRect(); const at={x:(event.clientX-r.left)*400/r.width,y:(event.clientY-r.top)*240/r.height};
    if(drag.drawing) { if(current.value.points.length<7800) current.value.points+=` ${at.x.toFixed(1)},${at.y.toFixed(1)}`; return; }
    const dx=at.x-drag.start.x, dy=at.y-drag.start.y;
    for(const i of drag.indices) {
        const original=drag.original[i], next=clone(original);
        next.x=Math.round((original.x || 0)+dx); next.y=Math.round((original.y || 0)+dy);
        if(['line','arrow'].includes(original.kind)) { next.x2=Math.round(original.x2+dx); next.y2=Math.round(original.y2+dy); }
        elements.value[i]=next;
    }
}
function end() { if(drag) checkpoint(); drag=null; window.removeEventListener('pointermove',move); }
onBeforeUnmount(()=>{ window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',end); });
function remove() { elements.value=elements.value.filter((_,i)=>!selected.value.includes(i));selected.value=[];checkpoint(); }
function duplicate() { const copies=selected.value.map(i=>({...clone(elements.value[i]),x:(elements.value[i].x||0)+10,y:(elements.value[i].y||0)+10}));elements.value.push(...copies);selected.value=[];checkpoint(); }
function reorder(front) { const chosen=elements.value.filter((_,i)=>selected.value.includes(i));const rest=elements.value.filter((_,i)=>!selected.value.includes(i));elements.value=front?[...rest,...chosen]:[...chosen,...rest];selected.value=[];checkpoint(); }
function chooseImage(event) {
    const file=event.target.files[0]; if(!file) return;
    if(!['image/png','image/jpeg','image/webp'].includes(file.type) || file.size>5*1024*1024) { error.value='Choose a PNG, JPEG or WebP image smaller than 5 MB.';return; }
    error.value='';upload.value=file;
    const reader=new FileReader();reader.onload=()=>{imageUrl.value=reader.result;elements.value=[];};reader.readAsDataURL(file);
}
async function cropImage() {
    const img=imagePreview.value; if(!img?.naturalWidth) return;
    const {x,y,width,height}=crop.value;
    if(x<0||y<0||width<=0||height<=0||x+width>100||y+height>100) {error.value='Crop percentages must fit within the image.';return;}
    const canvas=document.createElement('canvas');canvas.width=Math.round(img.naturalWidth*width/100);canvas.height=Math.round(img.naturalHeight*height/100);
    canvas.getContext('2d').drawImage(img,img.naturalWidth*x/100,img.naturalHeight*y/100,canvas.width,canvas.height,0,0,canvas.width,canvas.height);
    const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
    upload.value=new File([blob],'diagram.png',{type:'image/png'});imageUrl.value=canvas.toDataURL('image/png');crop.value={x:0,y:0,width:100,height:100};
}
async function save() {
    error.value='';saving.value=true;
    try {
        const form=new FormData();form.append('title',title.value);form.append('category',category.value);form.append('description',description.value);form.append('source',source.value);
        if(upload.value) form.append('image',upload.value);
        else if(imageUrl.value && props.diagram?.id) {
            const response=await axios.get(imageUrl.value,{responseType:'blob'});form.append('image',response.data,'diagram.png');
        } else elements.value.forEach((e,i)=>Object.entries(e).forEach(([key,value])=>form.append(`elements[${i}][${key}]`,typeof value==='boolean'?(value?'1':'0'):value)));
        if(props.diagram?.id) form.append('_method','PATCH');
        await axios.post(props.diagram?.id?route('diagrams.update',props.diagram.id):route('diagrams.store'),form);
        emit('saved');
    } catch(e) { error.value=Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Unable to save diagram.'; }
    finally { saving.value=false; }
}
</script>
<template>
    <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-3 sm:p-8" role="dialog" aria-modal="true" aria-label="Diagram editor" @keydown.esc="emit('close')">
        <div class="mx-auto max-w-6xl rounded-2xl bg-white p-5 shadow-xl" data-diagram-editor>
            <div class="mb-4 flex justify-between"><div><h2 class="text-xl font-semibold">Diagram studio</h2><p class="text-sm text-slate-500">Drag objects to move. Shift-click to select and move several together.</p></div><button @click="emit('close')" aria-label="Close editor">✕</button></div>
            <p v-if="error" role="alert" class="mb-3 rounded bg-red-50 p-3 text-red-700">{{ error }}</p>
            <div class="grid gap-5 lg:grid-cols-[1fr_280px]">
                <div>
                    <div class="mb-3 flex flex-wrap gap-2">
                        <button v-for="kind in ['rect','ellipse','polygon','line','arrow','text']" :key="kind" class="rounded border px-3 py-2 text-sm" @click="add(kind)">{{ {rect:'Rectangle',ellipse:'Circle / ellipse',polygon:'Triangle',text:'Label'}[kind] || kind }}</button>
                        <button class="rounded border px-3 py-2 text-sm" :class="freehand?'bg-teal-100':''" @click="freehand=!freehand">Freehand</button>
                        <label class="cursor-pointer rounded border px-3 py-2 text-sm">Upload image<input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="chooseImage" /></label>
                    </div>
                    <div class="overflow-hidden rounded-xl border-2 border-slate-200 bg-white">
                        <img v-if="imageUrl" ref="imagePreview" :src="imageUrl" class="mx-auto max-h-96 max-w-full" alt="Upload preview" />
                        <DiagramArt v-else :diagram="{elements}" interactive :selected="selected" @element-down="begin" @stage-down="stage" />
                    </div>
                    <div v-if="imageUrl" class="mt-3 flex flex-wrap items-end gap-2"><label v-for="key in ['x','y','width','height']" :key="key" class="text-xs">{{ key }} %<input v-model.number="crop[key]" type="number" min="0" max="100" class="block w-20 rounded border-slate-300" /></label><button class="rounded border px-3 py-2" @click="cropImage">Crop image</button></div>
                    <div v-else class="mt-3 flex flex-wrap gap-2 text-sm">
                        <button :disabled="!position" class="rounded border px-3 py-2 disabled:opacity-40" @click="undo">Undo</button><button :disabled="position>=history.length-1" class="rounded border px-3 py-2 disabled:opacity-40" @click="redo">Redo</button>
                        <template v-if="selected.length"><button class="rounded border px-3 py-2" @click="duplicate">Duplicate</button><button class="rounded border px-3 py-2" @click="reorder(true)">Bring forward</button><button class="rounded border px-3 py-2" @click="reorder(false)">Send backward</button><button class="rounded border px-3 py-2 text-red-700" @click="remove">Delete selected</button></template>
                    </div>
                    <div v-if="current && !imageUrl" class="mt-4 grid grid-cols-3 gap-3 rounded bg-slate-50 p-3 sm:grid-cols-5">
                        <label v-for="key in Object.keys(current).filter(k=>!['kind','stroke','fill','dashed','text','points'].includes(k))" :key="key" class="text-xs">{{ key }}<input v-model.number="current[key]" type="number" class="mt-1 w-full rounded border-slate-300 text-sm" @change="checkpoint" /></label>
                        <label class="text-xs">Stroke<input v-model="current.stroke" type="color" class="block" @change="checkpoint" /></label>
                        <label class="text-xs">Fill<input :value="current.fill==='none'?'#ffffff':current.fill" type="color" class="block" @input="current.fill=$event.target.value" @change="checkpoint" /><button class="text-teal-700" @click="current.fill='none';checkpoint()">No fill</button></label>
                        <label class="text-xs"><input v-model="current.dashed" type="checkbox" @change="checkpoint" /> Dashed</label>
                        <label v-if="current.kind==='text'" class="col-span-3 text-xs">Text / measurement<input v-model="current.text" class="mt-1 w-full rounded border-slate-300" @change="checkpoint" /></label>
                        <label v-if="['polygon','polyline'].includes(current.kind)" class="col-span-3 text-xs">Vertices (x,y pairs)<textarea v-model="current.points" class="mt-1 w-full rounded border-slate-300" @change="checkpoint" /></label>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Canvas: 400 × 240. Shapes are illustrative; add “not to scale” where appropriate. Use the library’s grids, clocks and solids as editable starting points.</p>
                </div>
                <form class="space-y-3" @submit.prevent="save">
                    <label class="block text-sm">Title<input v-model="title" required maxlength="150" class="mt-1 w-full rounded border-slate-300" /></label>
                    <label class="block text-sm">Category<input v-model="category" list="diagram-categories" required maxlength="80" class="mt-1 w-full rounded border-slate-300" /><datalist id="diagram-categories"><option v-for="c in ['Basic shapes','Solid shapes','Geometry','Fractions','Graphs','Measurement','Science','Maps','Counting']" :key="c">{{ c }}</option></datalist></label>
                    <label class="block text-sm">Description<textarea v-model="description" maxlength="2000" class="mt-1 w-full rounded border-slate-300" /></label>
                    <label class="block text-sm">Source / attribution<input v-model="source" maxlength="255" placeholder="Your own work or source and licence" class="mt-1 w-full rounded border-slate-300" /></label>
                    <p class="text-xs text-slate-500">Saved privately. You can submit it for review to share with every logged-in institute and teacher.</p>
                    <button :disabled="saving" class="w-full rounded-lg bg-teal-700 px-4 py-3 font-medium text-white disabled:opacity-50">{{ saving?'Saving…':'Save private diagram' }}</button>
                </form>
            </div>
        </div>
    </div>
</template>
