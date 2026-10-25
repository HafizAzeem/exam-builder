import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import puppeteer from 'puppeteer';
const browser=await puppeteer.launch({headless:true,pipe:true,args:['--no-sandbox','--disable-gpu']});
try {
 const page=await browser.newPage();await page.setViewport({width:756,height:1123});
 await page.setContent('<html><head></head><body><div id="app">Editor</div><div id="exam-print-root"><div class="paper-preview paper-preview--tpl1"><h1>Full width paper</h1><p>Question text</p></div></div></body></html>');
 await page.addStyleTag({content:await fs.readFile('resources/css/print.css','utf8')});
 const source=(await fs.readFile('resources/js/utils/printStyles.js','utf8')).replaceAll('export ','');
 await page.addScriptTag({content:source+'\nwindow.printTools={applyPrintStyles,clearPrintStyles,measurePrintFitScale,waitForPrintAssets};'});
 await page.waitForFunction(()=>window.printTools);
 await page.emulateMediaType('print');
 for(const fitScale of [1,.85,.72]) {
   const dimensions=await page.evaluate((fitScale)=>{
     document.body.classList.add('print-active');window.printTools.applyPrintStyles({fitScale});
     const root=document.getElementById('exam-print-root').getBoundingClientRect();
     const paper=document.querySelector('.paper-preview').getBoundingClientRect();
     return {fitScale,body:document.body.getBoundingClientRect().width,root:root.width,paper:paper.width,left:paper.left};
   },fitScale);
   assert.ok(Math.abs(dimensions.paper-dimensions.body)<2,`Fit must keep full printable width: ${JSON.stringify(dimensions)}`);
 }
 console.log('Single-page printing keeps full width at 100%, 85% and 72% app fit.');
 await page.setRequestInterception(true);
 page.on('request',request=>{
   if(request.url().includes('slow-diagram.png')) setTimeout(()=>request.respond({status:200,contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8ioAAAAASUVORK5CYII=','base64')}),200);
   else request.continue();
 });
 const assets=await page.evaluate(async()=>{
   const root=document.getElementById('exam-print-root');const image=new Image();root.append(image);
   image.src='http://print-test.local/slow-diagram.png';const start=performance.now();
   await window.printTools.waitForPrintAssets(root);
   return {elapsed:performance.now()-start,loaded:image.complete&&image.naturalWidth>0};
 });
 assert.ok(assets.loaded && assets.elapsed>=100,`Print must await diagram decoding: ${JSON.stringify(assets)}`);
 console.log('Printing waits for a delayed diagram image to load and decode.');
 await page.setViewport({width:1123,height:794});
 const dual=await page.evaluate(()=>{
   const root=document.getElementById('exam-print-root');
   root.innerHTML='<div class="exam-print-dual-sheet">'+Array(2).fill('<div class="exam-print-dual-slot"><div class="exam-print-dual-scaler"><div class="paper-preview">Complete copy</div></div></div>').join('')+'</div>';
   window.printTools.applyPrintStyles({dual:true});root.classList.add('exam-print-dual');
   return [...root.querySelectorAll('.paper-preview')].map(el=>({width:el.getBoundingClientRect().width,slot:el.closest('.exam-print-dual-slot').getBoundingClientRect().width}));
 });
 assert.equal(dual.length,2);assert.ok(dual.every(x=>x.width<=x.slot+1),'Both copies must fit inside their landscape slots');
 await page.evaluate(()=>window.printTools.clearPrintStyles());
 assert.equal(await page.evaluate(()=>document.body.classList.contains('print-fit')),false);
 console.log('Double-page landscape layout and print cleanup remain intact.');
}finally{await browser.close();}

