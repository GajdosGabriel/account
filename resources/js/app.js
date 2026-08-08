import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Account';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),

    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`Stránka ./Pages/${name}.vue neexistuje.`);
        }

        // Prihlasovacie stránky si layout riešia samy. `Public/` sú stránky
        // pre zákazníka (napr. potvrdenie e-mailu) – ten prihlásený nie je
        // a AppLayout by na `auth.user.name` spadol.
        const standalone = name.startsWith('Auth/') || name.startsWith('Public/');

        page.default.layout = page.default.layout ?? (standalone ? undefined : AppLayout);

        return page;
    },

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },

    progress: { color: '#0f172a' },
});
