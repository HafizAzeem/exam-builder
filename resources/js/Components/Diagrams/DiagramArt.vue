<script setup>
import { computed } from 'vue';
const props = defineProps({ diagram: Object, interactive: Boolean, selected: { type: Array, default: () => [] } });
const emit = defineEmits(['element-down', 'stage-down']);
const safeImage = computed(() => {
    const url = props.diagram?.image_data || props.diagram?.image_url || '';
    return /^(data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+/=\s]+|\/diagrams\/\d+\/image|\/storage\/[A-Za-z0-9_./-]+)$/.test(url) || (url.startsWith(window.location.origin + '/diagrams/') && /\/\d+\/image$/.test(url)) ? url : '';
});
const color = (value, fallback) => /^(#[0-9a-f]{6}|none)$/i.test(value || '') ? value : fallback;
const num = (value, fallback = 0) => Number.isFinite(Number(value)) ? Math.max(-2000, Math.min(2000, Number(value))) : fallback;
const transform = (e) => {
    const polygon = ['polygon','polyline'].includes(e.kind);
    const cx = polygon ? 200 : e.kind === 'rect' ? num(e.x) + num(e.width)/2 : num(e.x);
    const cy = polygon ? 120 : e.kind === 'rect' ? num(e.y) + num(e.height)/2 : num(e.y);
    return `${polygon ? `translate(${num(e.x)} ${num(e.y)}) ` : ''}rotate(${num(e.rotation)} ${cx} ${cy})`;
};
const arrowHead = (e) => {
    const a = Math.atan2(num(e.y2)-num(e.y), num(e.x2)-num(e.x));
    return `${num(e.x2)},${num(e.y2)} ${num(e.x2)-10*Math.cos(a-.45)},${num(e.y2)-10*Math.sin(a-.45)} ${num(e.x2)-10*Math.cos(a+.45)},${num(e.y2)-10*Math.sin(a+.45)}`;
};
</script>
<template>
    <div class="diagram-art" :aria-label="diagram?.title || 'Diagram'">
        <img v-if="safeImage" :src="safeImage" :alt="diagram?.title || 'Question diagram'" class="diagram-image" />
        <svg v-else viewBox="0 0 400 240" xmlns="http://www.w3.org/2000/svg" role="img" :aria-label="diagram?.title || 'Diagram'" :class="{ drawing: interactive }" @pointerdown.self="emit('stage-down', $event)">
            <g v-for="(e, index) in (diagram?.elements || []).slice(0, 300)" :key="index" :transform="transform(e)" :stroke="selected.includes(index) ? '#0d9488' : color(e.stroke, '#111111')" :fill="color(e.fill, 'none')" :stroke-width="num(e.strokeWidth, 2)" :stroke-dasharray="e.dashed ? '6 4' : undefined" :class="{ movable: interactive }" @pointerdown="interactive && emit('element-down', { index, event: $event })">
                <rect v-if="e.kind === 'rect'" :x="num(e.x)" :y="num(e.y)" :width="Math.max(0,num(e.width))" :height="Math.max(0,num(e.height))" />
                <ellipse v-else-if="e.kind === 'ellipse'" :cx="num(e.x)" :cy="num(e.y)" :rx="Math.max(0,num(e.rx))" :ry="Math.max(0,num(e.ry))" />
                <template v-else-if="e.kind === 'line' || e.kind === 'arrow'">
                    <line :x1="num(e.x)" :y1="num(e.y)" :x2="num(e.x2)" :y2="num(e.y2)" />
                    <polygon v-if="e.kind === 'arrow'" :points="arrowHead(e)" :fill="color(e.stroke, '#111111')" />
                </template>
                <polygon v-else-if="e.kind === 'polygon'" :points="/^[0-9\s,.\-]+$/.test(e.points || '') ? e.points : ''" />
                <polyline v-else-if="e.kind === 'polyline'" :points="/^[0-9\s,.\-]+$/.test(e.points || '') ? e.points : ''" />
                <text v-else-if="e.kind === 'text'" :x="num(e.x)" :y="num(e.y)" :font-size="num(e.fontSize, 18)" :fill="color(e.stroke, '#111111')" stroke="none" font-family="Arial, sans-serif">{{ e.text }}</text>
            </g>
        </svg>
    </div>
</template>
<style scoped>
.diagram-art{width:100%;break-inside:avoid;page-break-inside:avoid}.diagram-art svg{display:block;width:100%;height:auto;max-height:360px}.diagram-image{display:block;max-width:100%;max-height:360px;object-fit:contain;margin:auto}.drawing{touch-action:none;background:white}.movable{cursor:move;pointer-events:all}text{user-select:none}@media print{.diagram-art{break-inside:avoid}.diagram-art svg,.diagram-image{max-height:240px}}
</style>
