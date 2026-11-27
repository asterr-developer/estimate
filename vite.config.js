import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { compileTemplate } from 'vue/compiler-sfc';

const vueTemplates = {
    name: 'vue-html-templates',
    enforce: 'pre',
    transform(source, id) {
        if (!id.endsWith('.html?vue-template')) return;

        const filename = id.slice(0, id.indexOf('?'));
        const result = compileTemplate({ source, filename, id: filename, transformAssetUrls: false });
        if (result.errors.length) this.error(result.errors.map(String).join('\n'));

        return { code: result.code, map: result.map };
    },
};

export default defineConfig({
    plugins: [vueTemplates, laravel({ input: ['resources/js/app.js', 'resources/js/mini.js'], refresh: true })],
});
