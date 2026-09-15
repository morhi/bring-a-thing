import Aura from '@primeuix/themes/aura';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import PrimeVue from 'primevue/config';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    setup({ el, App, props, plugin }) {
        if (!el) {
            return;
        }

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                },
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
