import "@fontsource-variable/familjen-grotesk";
import "@fontsource-variable/gabarito";
import "@fontsource-variable/instrument-sans";
import { createInertiaApp } from "@inertiajs/vue3";
import { createApp, h, watchEffect } from "vue";
import { configureEcho } from "@laravel/echo-vue";
import { locale, t, tc } from "@/lib/i18n";

configureEcho({
    broadcaster: "reverb",
});

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    setup({ el, App, props, plugin }) {
        if (!el) {
            return;
        }

        const app = createApp({ render: () => h(App, props) }).use(plugin);

        // Templates can call $t() / $tc() without importing the helpers.
        app.config.globalProperties.$t = t;
        app.config.globalProperties.$tc = tc;
        app.mount(el);
        watchEffect(() => {
            document.documentElement.lang = locale.value;
        });
    },
    progress: {
        color: "#4B5563",
    },
});
