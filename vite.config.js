import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Source Sans 3 für Fließtext, IBM Plex Mono für Marke, Labels und
            // alle Zahlen — so wie im Entwurf.
            fonts: [
                bunny('Source Sans 3', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('IBM Plex Mono', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
