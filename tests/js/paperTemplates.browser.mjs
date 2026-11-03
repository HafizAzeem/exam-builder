import assert from 'node:assert/strict';
import { build } from 'vite';
import vue from '@vitejs/plugin-vue';
import puppeteer from 'puppeteer';
import path from 'node:path';
import fs from 'node:fs/promises';

const harness = `
import {createApp, h, reactive} from 'vue';
import '@/../css/app.css';
import '@/../css/print.css';
import Preview from '@/Components/PaperPreview.vue';
import Sidebar from '@/Components/LayoutEditor/PaperSettingsSidebar.vue';
import searchable from '@/Directives/searchable.js';
const state = reactive({layout:{header_template:1,dual_column:false,template_meta:{}},settings:{},content:{header:{institute_name:'Pakistan Sample Academy',institute_address:'Main Campus, Lahore',class:'10',subject:'Physics',paper_type:'Chapter Test',marks:'25',paper_time:'45 minutes'},sections:[{type:'mcq',number:1,heading_en:'Choose the correct answer.',heading_ur:'درست جواب کا انتخاب کریں۔',marks:'(8 × 1 = 8)',questions:Array.from({length:8},(_,i)=>({id:i+1,type:'mcq',text_en:'The SI unit of force is:',text_ur:'قوت کی اکائی کیا ہے؟',options:{A:{en:'Joule',ur:'جول'},B:{en:'Newton',ur:'نیوٹن'},C:{en:'Watt',ur:'واٹ'},D:{en:'Pascal',ur:'پاسکل'}}}))}]}});
window.paperState=state;
state.editable = true;
state.layout.margins = {top:15,right:15,bottom:15,left:15};
const app=createApp({render:()=>h('div',{class:'harness'},[
h('aside',[h(Sidebar,{layout:state.layout,settings:state.settings,paperId:1,'onUpdate:layout':v=>state.layout=v,'onUpdate:settings':v=>state.settings=v})]),
h('main',[h(Preview,{layout:state.layout,paperContent:state.content,editable:state.editable,dualMedium:state.layout.dual_medium,'onUpdate:paperContent':v=>state.content=v})])
])});app.directive('searchable',searchable);app.mount('#app');
`;
const output = await build({ configFile: false, logLevel: 'error', plugins: [vue(), {
    name: 'paper-template-harness',
    resolveId(id) { if (id === 'virtual:templates') return id; },
    load(id) { if (id === 'virtual:templates') return harness; },
}], resolve: { alias: { '@': path.resolve('resources/js') } }, build: { write: false, minify: false, rollupOptions: { input: 'virtual:templates', output: { format: 'iife', name: 'TemplateHarness' } } } });
const files = (Array.isArray(output) ? output : [output]).flatMap(item => item.output);
const js = files.filter(file => file.type === 'chunk').map(file => file.code).join('\n');
const css = files.filter(file => file.fileName.endsWith('.css')).map(file => file.source).join('\n');
let browser;
try {
    browser = await puppeteer.launch({ headless: true, pipe: true, args: ['--no-sandbox', '--disable-gpu'] });
    const page = await browser.newPage();
    await page.setViewport({ width: 1150, height: 1250 });
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', message => { if (message.type() === 'error') console.error(message.text()); });
    await page.setContent('<html><head></head><body><div id="app"></div></body></html>');
    await page.addStyleTag({content: css + `body{margin:0;background:#edf1f5;font-family:Arial}.harness{display:flex;gap:24px;padding:20px}aside{width:270px;flex-shrink:0}main{width:794px}aside label,aside select,aside input{display:block;max-width:100%;margin:8px 0}.question-ur{direction:rtl;font-family:"Jameel Noori Nastaleeq",serif}@media print{aside{display:none}.harness{display:block;padding:0}main{width:100%}}`});
    await page.addScriptTag({content:js});
    await page.waitForSelector('.paper-preview');
    assert.equal(await page.$$eval('#paper-template option', els => els.length), 6, await page.$eval('#paper-template', el => el.outerHTML) + JSON.stringify(errors));
    for (const id of ['1', 'board', 'federal', 'academy', 'urdu', 'entry']) {
        await page.select('#paper-template', id);
        await page.waitForFunction(id => String(window.paperState.layout.header_template) === id, {}, id);
        if (id === 'academy') {
            await page.type('#template-batch', 'Evening A');
            assert.ok((await page.$eval('.paper-preview', el => el.innerText)).includes('Evening A'));
        }
        if (id === 'federal') assert.equal(await page.$$('.federal-response').then(els => els.length), 8);
        if (id === 'urdu') {
            assert.equal(await page.$eval('.exam-header', el => el.dir), 'rtl');
            assert.equal(await page.$$('.tpl1-question-ur').then(els => els.length), 8);
        }
        if (id === 'entry') {
            assert.equal(await page.$eval('.questions-container', el => getComputedStyle(el).columnCount), '2');
        }
        const overflow = await page.$eval('.paper-preview', el => el.scrollWidth > el.clientWidth + 2);
        assert.equal(overflow, false, `${id} overflows screen preview: ` + JSON.stringify(await page.$eval('.paper-preview', el => ({width:el.clientWidth,scroll:el.scrollWidth,children:[...el.querySelectorAll('*')].filter(child=>child.getBoundingClientRect().right>el.getBoundingClientRect().right+2).slice(0,5).map(child=>[child.className,child.getBoundingClientRect().width])}))));
        await page.emulateMediaType('print');
        assert.equal(await page.$eval('.paper-preview', el => el.scrollWidth > el.clientWidth + 2), false, `${id} overflows print`);
        await page.emulateMediaType('screen');
        if (process.env.TEMPLATE_SCREENSHOTS) {
            await fs.mkdir(process.env.TEMPLATE_SCREENSHOTS, { recursive: true });
            await page.evaluate(() => { window.paperState.editable = false; });
            await page.waitForFunction(() => !document.querySelector('.paper-preview--editing'));
            await page.$eval('.paper-preview', el => el.scrollIntoView());
            await (await page.$('.paper-preview')).screenshot({ path: path.join(process.env.TEMPLATE_SCREENSHOTS, `template-${id}.png`) });
            await page.evaluate(() => { window.paperState.editable = true; });
        }
    }
    await page.select('#paper-template', 'board');
    await page.$eval('.exam-header h1', el => { el.innerText = 'Edited institute'; el.dispatchEvent(new Event('blur')); });
    await page.waitForFunction(() => window.paperState.content.header.institute_name === 'Edited institute');
    await page.select('#paper-template', 'academy');
    assert.equal(await page.$eval('.exam-header h1', el => el.innerText), 'Edited institute');
    assert.equal(await page.$eval('#template-batch', el => el.value), 'Evening A');
    await page.evaluate(() => { window.paperState.layout.dual_column = true; window.paperState.layout.template_meta.board = 'Hidden board'; window.paperState.layout.template_meta.group = 'Hidden group'; });
    await page.select('#paper-template', 'federal');
    assert.equal(await page.evaluate(() => window.paperState.layout.dual_column), true, 'Switching templates preserves user column preference');
    await page.select('#paper-template', 'academy');
    assert.ok(!(await page.$eval('.exam-header', el => el.innerText)).includes('Hidden board'));
    assert.ok(!(await page.$eval('.exam-header', el => el.innerText)).includes('Hidden group'));
    assert.deepEqual(errors, []);
    console.log('All six layouts switch, retain edits/details, render Urdu, and fit screen/print; entry uses two columns.');
} finally {
    if (browser) await browser.close();
}
