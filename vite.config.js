import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        // Own port, so MetMemo never loads another project's dev server (tagpo uses the default 5173).
        // strictPort: if 5174 is taken, fail loudly instead of quietly moving to another port.
        port: 5174,
        strictPort: true,
        hmr: { host: '192.168.1.10' },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
