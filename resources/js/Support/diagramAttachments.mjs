export const MAX_DIAGRAMS = 10;
export function diagramItems(value, imagePath = '') {
    if (Array.isArray(value)) return value;
    if (value) return [value];
    return imagePath ? [{ title: 'Question illustration', image_url: `/storage/${imagePath}`, width: 240 }] : [];
}
export function appendDiagrams(items, additions) {
    if (items.length + additions.length > MAX_DIAGRAMS) throw new Error(`You can attach up to ${MAX_DIAGRAMS} diagrams here.`);
    return [...items, ...additions];
}
export const removeDiagram = (items, index) => items.filter((_, itemIndex) => itemIndex !== index);
export function moveDiagram(items, from, to) {
    const next = [...items];
    if (from < 0 || to < 0 || from >= next.length || to >= next.length) return next;
    next.splice(to, 0, next.splice(from, 1)[0]);
    return next;
}
