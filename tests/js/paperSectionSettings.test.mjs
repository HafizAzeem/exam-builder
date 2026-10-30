import { test } from 'node:test';
import assert from 'node:assert/strict';
import { applySectionOptions, sectionTotalMarks } from '../../resources/js/utils/paperSectionSettings.js';
import { buildPaperContentFromPreview } from '../../resources/js/utils/paperContent.js';

test('adding questions without paper options preserves configured section settings', () => {
    const current = {marks:3, blank_lines:4, choice_questions:5, questions_per_line:2};
    assert.deepEqual(applySectionOptions(current, {}), current);
    assert.deepEqual(applySectionOptions(current, {marks:2}), {...current, marks:2});
});

test('hiding question parts preserves their text and diagrams in paper content', () => {
    const part = {id:11,text_en:'Part text',diagram:{title:'Edited figure',elements:[]}};
    const content=buildPaperContentFromPreview({layout:{section_settings:{long:{show_parts:false}}},sections:[{type:'long',questions:[{id:1,type:'long',parts:[part]}]}]});
    assert.equal(content.sections[0].questions[0].parts[0]?.text_en,'Part text');
    assert.deepEqual(content.sections[0].questions[0].parts[0]?.diagram,part.diagram);
});
test('marks use attempted count, bounded by available questions, with zero meaning all', () => {
    assert.equal(sectionTotalMarks(8, {marks:2, choice_questions:5}), 10);
    assert.equal(sectionTotalMarks(3, {marks:2, choice_questions:5}), 6);
    assert.equal(sectionTotalMarks(8, {marks:2, choice_questions:0}), 16);
    assert.equal(sectionTotalMarks(0, {marks:2, choice_questions:5}), 0);
});
