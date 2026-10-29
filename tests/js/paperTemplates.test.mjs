import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'vite';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

test('all paper layouts retain edited questions and expose their specific header fields', async () => {
    const server = await createServer({ configFile: false, plugins: [vue()], resolve: { alias: { '@': path.resolve('resources/js') } }, optimizeDeps: { noDiscovery: true }, server: { middlewareMode: true, watch: null }, appType: 'custom' });
    try {
        const { default: Preview } = await server.ssrLoadModule('/resources/js/Components/PaperPreview.vue');
        const { PAPER_TEMPLATES, normalizePaperTemplate } = await server.ssrLoadModule('/resources/js/utils/paperTemplates.js');
        assert.equal(PAPER_TEMPLATES.length, 6);
        assert.equal(normalizePaperTemplate(7), 1);
        assert.equal(normalizePaperTemplate('1'), 1);
        for (const template of PAPER_TEMPLATES) {
            const app = createSSRApp(Preview, {
                layout: { header_template: template.id, template_meta: { board: 'Board example', batch: 'Evening', version: 'B' } },
                editable: true,
                paperContent: { header: { institute_name: 'Edited institute', class: '10', subject: 'Physics', paper_type: 'Annual', marks: '25', paper_time: '45 minutes' }, sections: [{ type: 'short', number: 1, heading_en: 'Edited heading', marks: 2, questions: [{ id: 5, type: 'short', text_en: 'Edited question', text_ur: 'سوال' }] }] },
            });
            app.directive('searchable', {});
            const html = await renderToString(app);
            for (const text of ['Edited institute', 'Edited heading', 'Edited question', 'Physics', '45 minutes']) assert.ok(html.includes(text), `${template.id}: missing ${text}`);
            if (template.id !== 1) assert.ok(html.includes(`data-paper-template="${template.id}"`));
            if (template.id === 'federal') assert.ok(html.includes('Invigilator'));
            if (template.id === 'academy') assert.ok(html.includes('Evening'));
            if (template.id === 'urdu') assert.ok(html.includes('سوال'));
        }
    } finally { await server.close(); }
});
