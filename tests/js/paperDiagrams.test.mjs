import {test} from 'node:test';
import assert from 'node:assert/strict';
import {buildPaperContentFromPreview,hydratePaperContentUrdu} from '../../resources/js/utils/paperContent.js';
test('question and part images survive conversion to printable paper content',()=>{
    const preview={sections:[{type:'long',questions:[{id:1,type:'long',text_en:'Label this',image_path:'questions/images/plant.png',parts:[{id:2,text_en:'Identify',image_path:'questions/images/leaf.png'}]}]}]};
    const result=buildPaperContentFromPreview(preview);
    assert.equal(result.sections[0].questions[0].image_path,'questions/images/plant.png');
    assert.equal(result.sections[0].questions[0].parts[0].image_path,'questions/images/leaf.png');
});
test('saved diagrams remain unchanged when bilingual content is hydrated',()=>{
    const diagram={title:'Triangle',elements:[{kind:'polygon',points:'0,0 100,100 0,100'}]};
    const content={header:{},sections:[{type:'mcq',questions:[{id:1,type:'mcq',diagram,options:{A:{en:'',diagram}}}]}]};
    const preview={sections:[{type:'mcq',questions:[{id:1,type:'mcq',text_ur:'Urdu',mcq_options:{option_a_en:'One'}}]}]};
    const result=hydratePaperContentUrdu(content,preview);
    assert.deepEqual(result.sections[0].questions[0].diagram,diagram);
    assert.deepEqual(result.sections[0].questions[0].options.A.diagram,diagram);
});
