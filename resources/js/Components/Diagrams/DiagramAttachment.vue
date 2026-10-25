<script setup>
import { ref, computed } from 'vue';
import DiagramArt from './DiagramArt.vue';
import DiagramPicker from './DiagramPicker.vue';
const props=defineProps({diagram:Object,imagePath:String,editable:Boolean});
const emit=defineEmits(['update']);
const picking=ref(false);
const pickerHost=ref('body');
function openPicker(event){pickerHost.value=event.currentTarget.closest('dialog') || document.body;picking.value=true;}
const visual=computed(()=>props.diagram || (props.imagePath?{title:'Question illustration',image_url:`/storage/${props.imagePath}`,width:240}:null));
const width=computed(()=>Math.max(60,Math.min(500,Number(visual.value?.width)||240)));
function choose(value){emit('update',value);picking.value=false;}
</script>
<template>
    <div v-if="visual || editable" class="diagram-attachment">
        <div v-if="visual" :style="{width:width+'px',maxWidth:'100%',margin:visual.align==='left'?'0':'0 auto'}"><DiagramArt :diagram="visual" /></div>
        <div v-if="editable" class="diagram-controls my-1 flex flex-wrap items-center gap-2 text-xs" data-no-print>
            <button type="button" class="rounded border border-teal-200 bg-white px-2 py-1 text-teal-800" @click="openPicker">{{ visual?'Replace diagram':'Add diagram' }}</button>
            <template v-if="diagram"><label>Width <input type="number" :value="width" min="60" max="500" class="w-16 rounded border-slate-300 px-1 py-0 text-xs" @change="emit('update',{...diagram,width:Math.max(60,Math.min(500,Number($event.target.value)||240))})" /></label><select :value="diagram.align||'center'" aria-label="Diagram alignment" class="rounded border-slate-300 py-0 text-xs" @change="emit('update',{...diagram,align:$event.target.value})"><option value="center">Center</option><option value="left">Left</option></select><button type="button" class="text-red-700" @click="emit('update',null)">Remove</button></template>
        </div>
        <Teleport :to="pickerHost"><DiagramPicker v-if="picking" @close="picking=false" @select="choose" /></Teleport>
    </div>
</template>
<style scoped>
.diagram-attachment{break-inside:avoid;page-break-inside:avoid;margin-top:4px}@media print{.diagram-controls{display:none!important}}
</style>
