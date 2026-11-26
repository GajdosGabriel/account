import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';
import './Composables/useReturnUrl';

const appName = import.meta.env.VITE_APP_NAME || 'Account';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),

    // Každá stránka je vlastný chunk – prehliadač stiahne len to, čo práve
    // zobrazuje, nie celý back-office naraz.
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const load = pages[`./Pages/${name}.vue`];

        if (!load) {
            throw new Error(`Stránka ./Pages/${name}.vue neexistuje.`);
        }

        const page = await load();

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

    // Značková farba (brand-500), nech je načítavanie vidieť aj na tmavom tlačidle.
    progress: { color: '#6366f1' },
});
