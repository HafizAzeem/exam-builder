import assert from 'node:assert/strict';
import { build } from 'vite';
import vue from '@vitejs/plugin-vue';
import puppeteer from 'puppeteer';
import path from 'node:path';
import fs from 'node:fs/promises';

const teacherProps={auth:{user:{name:'Owner',email:'owner@example.test',roles:['institution_admin']}},flash:{},errors:{},teachers:[{id:2,name:'Teacher One',email:'one@example.test',is_active:true,teacher_permission:{allowed_grades:[9],allowed_subjects:null,allowed_categories:['exercise']}}],grades:[{id:1,number:9,label_en:'Class 9'},{id:2,number:10,label_en:'Class 10'}],subjects:[{id:1,name_en:'English',grade:{number:9,label_en:'Class 9'}}],categories:['exercise','additional','past_paper']};
const harness = `
import {createApp,h} from 'vue';
import {createInertiaApp} from '@inertiajs/vue3';
import Teachers from '@/Pages/Admin/Teachers.vue';
import '@/../css/app.css';
window.route=(name)=>name ? '/'+name : {current:()=>false};
createInertiaApp({page:{component:'Teachers',url:'/',version:null,props:${JSON.stringify(teacherProps)}},resolve:()=>Teachers,setup({el,App,props,plugin}){const app=createApp({render:()=>h(App,props)});app.config.globalProperties.route=window.route;app.use(plugin).mount(el);}});
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
    await page.setRequestInterception(true);
    page.on('request',request=>request.respond({status:200,contentType:'text/html',body:'<html><head></head><body><div id="app"></div></body></html>'}));
    await page.goto('http://exam.test/');await page.addStyleTag({content:css + appCss});await page.addScriptTag({content:js});
    const clickText=async(text)=>{await page.evaluate(text=>{const button=[...document.querySelectorAll('button')].find(el=>el.innerText.trim()===text);if(!button)throw Error('Missing button '+text);button.click();},text);};
    await page.waitForSelector('#teacher-form');
    assert.equal(await page.$$eval('fieldset', els=>els.length),3);
    assert.ok((await page.$eval('body',el=>el.innerText)).includes('Class 9'));
    await clickText('Edit / permissions');
    assert.equal(await page.$eval('#teacher-form input',el=>el.value),'Teacher One');
    assert.equal(await page.$eval('#teacher-form input[type="email"]',el=>el.value),'one@example.test');
    await clickText('Cancel editing');
    assert.equal(await page.$eval('#teacher-form input',el=>el.value),'');
    assert.equal(await page.$eval('#teacher-form input[type="email"]',el=>el.value),'');
    await clickText('Edit / permissions');
    await page.evaluate(()=>{const label=[...document.querySelectorAll('label')].find(el=>el.innerText.trim()==='All classes');label.querySelector('input').click();});
    assert.equal(await page.$$eval('fieldset:first-of-type input',els=>els.length),1);
    await clickText('Cancel editing');
    assert.equal(await page.$$eval('fieldset:first-of-type input',els=>els.length),3);
    await page.setViewport({width:390,height:844});
    assert.equal(await page.$eval('#teacher-form',el=>el.scrollWidth>el.clientWidth+2),false);
    assert.deepEqual(errors,[]);
    console.log('Teacher permission groups render; editing, All classes, cancel/reset and mobile form layout pass.');
}finally{await browser.close();}
