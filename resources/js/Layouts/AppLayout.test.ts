import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import AppLayout from './AppLayout.vue';
const { page } = vi.hoisted(() => ({ page: { component: 'Home', props: { navigation: { home: '/', headers: '/headers', privacy: '/privacy' } } } }));
vi.mock('@inertiajs/vue3', async importOriginal => ({
    ...await importOriginal<typeof import('@inertiajs/vue3')>(),
    usePage: () => page,
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));
it.each([['Home', '/'], ['Headers/Show', '/headers'], ['Scans/Show', '/']])('identifies the active tool on %s', (component, href) => {
    page.component = component;
    const wrapper = mount(AppLayout);
    expect(wrapper.get('[aria-current=page]').attributes('href')).toBe(href);
    if (component === 'Headers/Show') {
        expect(wrapper.text()).toContain('have not been independently verified');
        expect(wrapper.text()).not.toContain('Evidence from this scanner’s network.');
    }
    wrapper.unmount();
});
