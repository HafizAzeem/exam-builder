import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import puppeteer from 'puppeteer';

const source = await fs.readFile('resources/js/Directives/searchable.js', 'utf8');
const browser = await puppeteer.launch({ headless: true, pipe: true, args: ['--no-sandbox', '--disable-gpu'] });
try {
    const page = await browser.newPage();
    await page.setViewport({ width: 390, height: 700 });
    await page.setContent('<label for="board">Board</label><select id="board" required><option value="">Choose board</option></select><select id="short"><option>Yes</option><option>No</option></select><button id="outside">Outside</button>');
    await page.addStyleTag({ content: (await fs.readFile('resources/css/app.css', 'utf8')).replaceAll(/@tailwind[^;]+;/g, '') });
    await page.addScriptTag({ content: source.replace('export default', 'window.searchable =') });
    await page.evaluate(() => {
        const select = document.querySelector('#board');
        for (let i = 0; i < 12; i++) select.add(new Option(`Board ${i}`, String(i)));
        select.addEventListener('change', () => { window.selected = select.value; });
        window.searchable.mounted(select);
        window.searchable.mounted(document.querySelector('#short'));
    });
    await page.click('#board');
    await page.waitForSelector('[data-searchable-popup] input');
    await page.type('[data-searchable-popup] input', 'board 10');
    await page.keyboard.press('Home');
    assert.equal(await page.$eval('[data-searchable-popup] input', el => el.selectionStart), 0);
    await page.keyboard.press('End');
    assert.equal(await page.$eval('[data-searchable-popup] input', el => el.selectionStart), 8);
    assert.equal(await page.$$eval('[role="option"]', els => els.length), 1);
    await page.keyboard.press('Enter');
    assert.equal(await page.evaluate(() => window.selected), '10');
    assert.equal(await page.evaluate(() => document.querySelector('#board').checkValidity()), true);
    assert.equal(await page.$('[data-searchable-popup]'), null);
    await page.focus('#board');
    await page.keyboard.press('ArrowDown');
    await page.waitForSelector('[data-searchable-popup] input');
    await page.keyboard.press('Escape');
    assert.equal(await page.evaluate(() => document.activeElement.id), 'board');
    await page.click('#board');
    await page.type('[data-searchable-popup] input', 'nothing matches');
    assert.match(await page.$eval('[data-searchable-popup]', el => el.textContent), /No matching options/);
    await page.click('#outside');
    assert.equal(await page.$('[data-searchable-popup]'), null);
    await page.evaluate(() => {
        const select = document.querySelector('#board');
        select.disabled = true;
        select.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
    });
    assert.equal(await page.$('[data-searchable-popup]'), null);
    assert.equal(await page.$eval('#short', el => el.hasAttribute('aria-controls')), false);
    await page.evaluate(() => {
        const select = document.querySelector('#board');
        select.disabled = false;
        select.add(new Option('Newly loaded board', 'new'));
    });
    await page.click('#board');
    await page.type('[data-searchable-popup] input', 'newly');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    assert.equal(await page.evaluate(() => window.selected), 'new');
    await page.click('#board');
    const bounds = await page.$eval('[data-searchable-popup]', el => {
        const r = el.getBoundingClientRect();
        return { left: r.left, right: r.right, bottom: r.bottom };
    });
    assert.ok(bounds.left >= 0 && bounds.right <= 390 && bounds.bottom <= 700);
    await page.evaluate(() => window.searchable.beforeUnmount(document.querySelector('#board')));
    assert.equal(await page.$('[data-searchable-popup]'), null);
    console.log('Search, native change/validation, keyboard, empty results, outside click, disabled/short lists, dynamic options, mobile bounds and cleanup passed.');

    await page.addScriptTag({ content: await fs.readFile('node_modules/vue/dist/vue.global.js', 'utf8') });
    await page.evaluate(() => {
        const root = document.createElement('div');
        document.body.append(root);
        window.model = Vue.reactive({ value: '', changes: 0, count: 12 });
        Vue.createApp({
            setup: () => ({ model: window.model }),
            template: '<dialog open><label for="vue-select">Subject</label><select id="vue-select" v-searchable v-model.number="model.value" @change="model.changes++"><option value="">Select subject</option><option v-for="i in model.count" :value="i">Subject {{ i }}</option></select></dialog>',
        }).directive('searchable', window.searchable).mount(root);
        const dialog = root.querySelector('dialog');
        dialog.close();
        dialog.showModal();
    });
    await page.click('#vue-select');
    await page.type('[data-searchable-popup] input', 'subject 12');
    await page.keyboard.press('Enter');
    assert.deepEqual(await page.evaluate(() => ({ value: window.model.value, changes: window.model.changes })), { value: 12, changes: 1 });
    assert.equal(await page.$eval('dialog', el => el.open), true);
    await page.click('#vue-select');
    await page.keyboard.press('Escape');
    assert.equal(await page.$eval('dialog', el => el.open), true);
    await page.click('#vue-select');
    await page.evaluate(() => { window.model.count = 3; });
    await page.waitForFunction(() => !document.querySelector('[data-searchable-popup]'));
    console.log('Vue numeric v-model/change handlers, native modal selection/Escape, and dependent options shrinking to a short list passed.');
} finally {
    await browser.close();
}
