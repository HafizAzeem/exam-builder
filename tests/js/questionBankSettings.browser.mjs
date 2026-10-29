import assert from 'node:assert/strict';
import { build } from 'vite';
import vue from '@vitejs/plugin-vue';
import puppeteer from 'puppeteer';
import path from 'node:path';
import fs from 'node:fs/promises';

const harness = `
import {createApp,h} from 'vue';
import {createInertiaApp} from '@inertiajs/vue3';
import Compose from '@/Pages/PaperBuilder/Compose.vue';
import searchable from '@/Directives/searchable.js';
import '@/../css/app.css';
window.route=(name)=>name ? '/'+name : {current:()=>false};
createInertiaApp({page:{component:'Compose',url:'/',version:null,props:{auth:{user:{name:'Teacher',email:'teacher@example.test',roles:['teacher']}},flash:{},grades:[{id:1,number:10,label_en:'Class 10'}],subjects:[{id:1,name_en:'Physics'}],chapters:[{id:1,number:1,title_en:'Motion'}],selectedGradeId:1,selectedSubjectId:1,selectedChapterIds:[1],institution:{name:'Sample School'},useSystemQuestionBank:true}},resolve:()=>Compose,setup({el,App,props,plugin}){const app=createApp({render:()=>h(App,props)});app.config.globalProperties.route=window.route;app.use(plugin).directive('searchable',searchable).mount(el);}});
`;
const result = await build({ configFile:false, logLevel:'error', plugins:[vue(),{name:'question-bank-harness',resolveId(id){if(id==='virtual:bank-test')return id;},load(id){if(id==='virtual:bank-test')return harness;}}],resolve:{alias:{'@':path.resolve('resources/js')}},build:{write:false,minify:false,rollupOptions:{input:'virtual:bank-test',output:{format:'iife',name:'BankHarness'}}}});
const output=(Array.isArray(result)?result:[result]).flatMap(item=>item.output);
const js=output.filter(item=>item.type==='chunk').map(item=>item.code).join('\n');
const css=output.filter(item=>item.fileName.endsWith('.css')).map(item=>item.source).join('\n');
const appCss=(await Promise.all((await fs.readdir('public/build/assets')).filter(name=>name.endsWith('.css')).map(name=>fs.readFile('public/build/assets/'+name,'utf8')))).join('\n');
const browser=await puppeteer.launch({headless:true,pipe:true,args:['--no-sandbox','--disable-gpu']});
try {
    const page=await browser.newPage();await page.setViewport({width:1440,height:1050});
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    page.on('console',message=>{if(message.type()==='error')console.error(message.text());});
    const searches=[];let batch=0;
    await page.setRequestInterception(true);
    page.on('request',async request=>{
        const url=new URL(request.url());
        if(url.pathname==='/api/builder/past-paper-filters')return request.respond({status:200,contentType:'application/json',body:JSON.stringify({boards:['BISE Lahore','FBISE'],default_board:'BISE Lahore',years:[2025]})});
        if(url.pathname==='/api/builder/questions/all'){
            searches.push(url.searchParams.get('board_name'));
            const type=url.searchParams.get('type');
            return request.respond({status:200,contentType:'application/json',body:JSON.stringify(Array.from({length:4},(_,i)=>({id:batch*10+i+1,type,source:'exercise',text_en:'Question '+(batch*10+i+1),text_ur:'سوال',mcq_options:type==='mcq'?{option_a_en:'A',option_b_en:'B',option_c_en:'C',option_d_en:'D'}:null}))) });
        }
        return request.respond({status:200,contentType:'text/html',body:'<html><head></head><body><div id="app"></div></body></html>'});
    });
    await page.goto('http://exam.test/');await page.addStyleTag({content:css + appCss});await page.addScriptTag({content:js});
    const clickText=async(text)=>{await page.evaluate(text=>{const button=[...document.querySelectorAll('button')].find(el=>el.innerText.trim()===text);if(!button)throw Error('Missing button '+text);button.click();},text);};
    await page.waitForSelector('#compose-paper-preview');
    await clickText('From question bank');await page.waitForSelector('#bank-board');
    await page.waitForFunction(()=>document.querySelectorAll('.bank-result').length===4);
    assert.equal(await page.$eval('#bank-board',el=>el.value),'BISE Lahore');
    assert.equal(searches[0],'BISE Lahore','First search must use school board');
    const text=await page.$eval('[data-question-bank]',el=>el.innerText);
    for(const label of ['Marks per question','Questions to attempt','Answer lines','Two questions per line','Medium'])assert.ok(!text.includes(label),label+' should live in Details');
    await page.select('#bank-board','');await page.waitForFunction(()=>document.querySelectorAll('.bank-result').length===4);
    await page.waitForFunction(()=>document.querySelector('#bank-board').value==='');
    const typeSelect=await page.$('[data-question-bank] select');await typeSelect.select('short');
    await page.waitForFunction(()=>[...document.querySelectorAll('.bank-result')].every(el=>el.innerText.includes('Question')));
    await page.click('.bank-result');await clickText('Add 1 question to paper');
    await page.waitForFunction(()=>!document.querySelector('[data-question-bank]'));
    assert.equal(searches.at(-1),null,'All boards selection should stay cleared');
    await clickText('Details');await page.waitForSelector('[aria-label="Short questions: marks per question"]');
    const setValue=async(selector,value)=>page.$eval(selector,(el,value)=>{el.value=value;el.dispatchEvent(new Event('input',{bubbles:true}));},value);
    await setValue('[aria-label="Short questions: marks per question"]','3');
    await setValue('[aria-label="Short questions: answer lines"]','4');
    await setValue('[aria-label="Short questions: questions to attempt"]','1');
    await clickText('Done');
    await page.waitForFunction(()=>document.querySelector('#compose-paper-preview').innerText.includes('(1X3=3)'));
    assert.ok((await page.$eval('#compose-paper-preview',el=>el.innerText)).includes('Answer any 1'));
    batch=1;
    await clickText('From question bank');await page.waitForSelector('#bank-board');await page.waitForFunction(()=>document.querySelectorAll('.bank-result').length===4);
    assert.equal(await page.$eval('#bank-board',el=>el.value),'BISE Lahore');
    await page.click('.bank-result');await clickText('Add 1 question to paper');
    await page.waitForFunction(()=>!document.querySelector('[data-question-bank]'));
    await clickText('Details');await page.waitForSelector('[aria-label="Short questions: marks per question"]');
    assert.equal(await page.$eval('[aria-label="Short questions: marks per question"]',el=>el.value),'3');
    assert.equal(await page.$eval('[aria-label="Short questions: answer lines"]',el=>el.value),'4');
    assert.equal(await page.$eval('[aria-label="Short questions: questions to attempt"]',el=>el.value),'1');
    assert.deepEqual(errors,[]);
    console.log('Question Bank defaults to school board, supports All boards, and has no paper settings; Details updates preview and survives repeat adds.');
}finally{await browser.close();}
