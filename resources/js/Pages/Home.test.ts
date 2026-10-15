import { mount } from '@vue/test-utils';
import { afterEach, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import Home from './Home.vue';

vi.mock('@inertiajs/vue3', async importOriginal => ({
    ...await importOriginal<typeof import('@inertiajs/vue3')>(),
    Head: { render: () => null },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: {} }),
}));

afterEach(() => vi.restoreAllMocks());

const props = { submit_url: '/scans', retention_days: 7, recent_scans: [] };
const global = { stubs: { AppLayout: { template: '<main><slot /></main>' } } };

it('submits the chosen visibility and defaults to public', async () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    const wrapper = mount(Home, { props, global });
    expect(wrapper.get<HTMLInputElement>('[type="checkbox"]').element.checked).toBe(false);
    await wrapper.get('#domain').setValue('example.com');
    await wrapper.get('[type="checkbox"]').setValue(true);
    await wrapper.get('form').trigger('submit');
    expect(post).toHaveBeenCalledWith('/scans', expect.objectContaining({ domain: 'example.com', hidden_from_overview: true }), expect.anything());
    wrapper.unmount();
});

it('shows recent report links and their status or an empty state', async () => {
    const wrapper = mount(Home, { props, global });
    expect(wrapper.text()).toContain('No public inspections yet.');
    await wrapper.setProps({ recent_scans: [{ domain: 'example.com', status: 'completed', created_at: '2026-10-16T12:00:00Z', url: '/scans/report' }] });
    expect(wrapper.get('a[href="/scans/report"]').text()).toContain('example.com');
    expect(wrapper.text()).toContain('Completed');
    expect(wrapper.get('time').attributes('datetime')).toBe('2026-10-16T12:00:00Z');
    expect(wrapper.text()).not.toContain('No public inspections yet.');
    wrapper.unmount();
});
