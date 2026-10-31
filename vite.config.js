import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                // Auth + settings pages (Blade, Flux).
                'resources/css/app.css',
                'resources/js/app.js',
                /* @chisel-passkeys */
                'resources/js/passkeys.js',
                /* @end-chisel-passkeys */
                // The React single-page app behind login.
                'resources/css/spa.css',
                'resources/js/spa/main.tsx',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        react(),
        tailwindcss(),
    ]),
    resolve: {
        alias: {
            '@': '/resources/js/spa',
        },
    },
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
