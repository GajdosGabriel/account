import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
        tailwindcss(),
    ],
    server: {
        // Bez tohto sa Vite naviaze na [::1] a prehliadac na account.local
        // si assety nestiahne.
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,

        // Vite 6+ blokuje cross-origin requesty na dev server. Aplikacia bezi
        // na inom origine ako Vite, takze ho treba vyslovne povolit.
        cors: {
            origin: [
                'http://account.local',
                'https://account.local',
                'http://localhost:8000',
                'http://127.0.0.1:8000',
            ],
        },

        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
