import assert from 'node:assert/strict';
import { build } from 'vite';
import vue from '@vitejs/plugin-vue';
import puppeteer from 'puppeteer';
import path from 'node:path';
import { readFile } from 'node:fs/promises';

const harness = `
import {createApp,h} from 'vue';
import {createInertiaApp,router} from '@inertiajs/vue3';
import ActivityLog from '@/Pages/Dashboard/ActivityLog.vue';
window.route=(name)=>name ? '/'+name : {current:()=>false};
router.get=(url,data)=>{window.lastFilter={url,data:{...data}};};
createInertiaApp({page:{component:'ActivityLog',url:'/',version:null,props:window.testProps},resolve:()=>ActivityLog,setup({el,App,props,plugin}){const app=createApp({render:()=>h(App,props)});app.config.globalProperties.route=window.route;app.directive('searchable',{});app.use(plugin).mount(el);}});
`;
const result = await build({ configFile: false, logLevel: 'error', plugins: [vue(), { name: 'activity-test', resolveId(id) { if (id === 'virtual:activity-test') return id; }, load(id) { if (id === 'virtual:activity-test') return harness; } }], resolve: { alias: { '@': path.resolve('resources/js') } }, build: { write: false, minify: false, rollupOptions: { input: 'virtual:activity-test', output: { format: 'iife', name: 'ActivityHarness' } } } });
const script = (Array.isArray(result) ? result : [result]).flatMap(item => item.output).filter(item => item.type === 'chunk').map(item => item.code).join('\n');
const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
const styles = await Promise.all(manifest['resources/js/app.js'].css.map(file => readFile(path.join('public/build', file), 'utf8')));
const base = { auth: { user: { name: 'Owner', roles: ['institution_admin'] } }, flash: {}, errors: {}, filters: {}, people: [{ id: 2, name: 'Ahmed Khan' }], actions: [{ value: 'admin.teachers.store', label: 'Added teacher' }], logs: { total: 1, from: 1, to: 1, links: [], data: [{ id: 1, actor_name: 'Ahmed Khan', actor_role: 'School admin', description: 'Added teacher Sara Ali', created_at: '2026-11-01T08:00:00Z', method: 'POST', path: '/admin/teachers', ip_address: '127.0.0.1' }] } };
const browser = await puppeteer.launch({ headless: true, pipe: true, args: ['--no-sandbox', '--disable-gpu'] });
try {
    const page = await browser.newPage();
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.setRequestInterception(true);
    page.on('request', request => request.respond({ status: 200, contentType: 'text/html', body: '<html><body><div id="app"></div></body></html>' }));
    async function mount(props = {}) {
        await page.goto('http://activity.test/');
        await page.evaluate(props => { window.testProps = props; }, { ...base, ...props });
        await page.addScriptTag({ content: script });
        for (const content of styles) await page.addStyleTag({ content });
        await page.waitForSelector('h1');
    }
    await page.setViewport({ width: 1280, height: 900 });
    await mount();
    assert.ok((await page.$eval('body', el => el.innerText)).includes('Added teacher Sara Ali'));
    assert.ok((await page.$eval('body', el => el.innerText)).includes('Ahmed Khan'));
    assert.equal(await page.$eval('details', el => el.open), false);
    await page.click('summary');
    assert.ok((await page.$eval('body', el => el.innerText)).includes('/admin/teachers'));
    await page.select('#activity-person', '2');
    await page.click('button[type="submit"]');
    assert.equal(await page.evaluate(() => window.lastFilter.data.user_id), 2);
    await page.setViewport({ width: 390, height: 844 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true);
    await mount({ logs: { total: 0, data: [], links: [] } });
    assert.ok((await page.$eval('body', el => el.innerText)).includes('No activity found'));
    assert.deepEqual(errors, []);
    console.log('Activity log actors, readable actions, details, person filter, empty state and mobile layout pass.');
} finally { await browser.close(); }
