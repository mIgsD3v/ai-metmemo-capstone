import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import ConfirmDialog from './Components/ConfirmDialog.vue';
import Toaster from './Components/Toaster.vue';
import { toastFlash } from './toast';
import { syncNotifications } from './notifications';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Every flash message from Laravel ("Card created.", "Note added.") shows as a toast.
router.on('success', (event) => toastFlash(event.detail.page));

// Keep the bell current on every page, and the live feed connected for whoever is signed in.
router.on('navigate', (event) => syncNotifications(event.detail.page.props));

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        toastFlash(props.initialPage);
        syncNotifications(props.initialPage.props);

        return createApp({ render: () => [h(App, props), h(Toaster), h(ConfirmDialog)] })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
