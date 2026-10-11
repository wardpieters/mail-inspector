import '../css/app.css';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
const pages = import.meta.glob<{default: DefineComponent}>('./Pages/**/*.vue');
createInertiaApp({ title: title => title ? `${title} · Mail Inspector` : 'Mail Inspector', resolve: async name => {const loader = pages[`./Pages/${name}.vue`]; if (!loader) throw new Error(`Unknown page: ${name}`); return (await loader()).default;}, setup({ el, App, props, plugin }) { createApp({ render: () => h(App, props) }).use(plugin).mount(el); } });
