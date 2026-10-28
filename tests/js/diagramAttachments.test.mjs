import test from 'node:test';
import assert from 'node:assert/strict';
import { diagramItems, appendDiagrams, removeDiagram, moveDiagram } from '../../resources/js/Support/diagramAttachments.mjs';
test('single diagrams and legacy images remain compatible', () => {
    const old = { title: 'Original', width: 180 };
    assert.deepEqual(diagramItems(old), [old]);
    assert.equal(diagramItems(null, 'questions/test.png')[0].image_url, '/storage/questions/test.png');
    assert.deepEqual(diagramItems([], 'questions/test.png'), []);
});
test('five attachments append without replacing existing content and survive JSON saving', () => {
    const first = { title: 'First', width: 180 };
    const items = appendDiagrams([first], Array.from({ length: 4 }, (_, index) => ({ title: `Image ${index}` })));
    assert.equal(items.length, 5);
    assert.deepEqual(JSON.parse(JSON.stringify(items))[0], first);
    assert.deepEqual(moveDiagram(items, 4, 0).map(item => item.title), ['Image 3', 'First', 'Image 0', 'Image 1', 'Image 2']);
    assert.equal(removeDiagram(items, 1).length, 4);
    assert.equal(items.length, 5);
});
test('adding too many diagrams rejects the batch without mutating previous attachments', () => {
    const items = Array.from({ length: 10 }, () => ({ title: 'Image' }));
    assert.throws(() => appendDiagrams(items, [{ title: 'Extra' }]), /10/);
    assert.equal(items.length, 10);
});
