import test from 'node:test';
import assert from 'node:assert/strict';
import { selectionForChapter, changeGrade, changeSubject, copySelection, subjectOptions, chapterOptions } from '../../resources/js/Support/curriculumSelection.mjs';

const chapters = [
    { id: 11, subject_id: 2, subject: { id: 2, grade_id: 1, name_en: 'English', grade: { id: 1, number: 9 } } },
    { id: 12, subject_id: 3, subject: { id: 3, grade_id: 1, name_en: 'Maths', grade: { id: 1, number: 9 } } },
    { id: 21, subject_id: 4, subject: { id: 4, grade_id: 5, name_en: 'English', grade: { id: 5, number: 10 } } },
];
test('dependent options stay within the selected class and subject', () => {
    assert.deepEqual(subjectOptions(chapters, 1).map(s => s.value), [2, 3]);
    assert.deepEqual(chapterOptions(chapters, 4).map(c => c.value), [21]);
    assert.deepEqual(subjectOptions(chapters, ''), []);
});
test('changing class or subject clears dependent selections', () => {
    const selected = selectionForChapter(chapters, 11);
    assert.deepEqual(selected, { grade_id: 1, subject_id: 2, chapter_id: 11 });
    assert.deepEqual(changeGrade(selected, 5), { grade_id: 5, subject_id: '', chapter_id: '' });
    assert.deepEqual(changeSubject(selected, 3), { grade_id: 1, subject_id: 3, chapter_id: '' });
});
test('new rows inherit complete and partially selected dropdowns without sharing state', () => {
    const previous = { ...selectionForChapter(chapters, 11), text_en: 'Do not copy' };
    const next = copySelection(previous);
    assert.deepEqual(next, { grade_id: 1, subject_id: 2, chapter_id: 11 });
    next.chapter_id = 12;
    assert.equal(previous.chapter_id, 11);
    assert.deepEqual(copySelection(changeGrade(previous, 5)), { grade_id: 5, subject_id: '', chapter_id: '' });
});
test('reselecting the current class or subject keeps completed child selections', () => {
    const selected = selectionForChapter(chapters, 11);
    assert.deepEqual(changeGrade(selected, '1'), selected);
    assert.deepEqual(changeSubject(selected, '2'), selected);
});
