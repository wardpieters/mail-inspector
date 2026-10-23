import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, expect, it, vi } from 'vitest';
import Show from './Show.vue';

const { page } = vi.hoisted(() => ({ page: { props: { i18n: { locale: 'en_US', messages: {} as Record<string, string> } } } }));
vi.mock('@inertiajs/vue3', async importOriginal => ({
    ...await importOriginal<typeof import('@inertiajs/vue3')>(),
    Head: { render: () => null }, usePage: () => page,
}));
afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); page.props.i18n = { locale: 'en_US', messages: {} }; document.body.innerHTML = ''; });
const mountPage = () => mount(Show, { attachTo: document.body, global: { stubs: { AppLayout: { template: '<main><slot /></main>' } } } });
async function analyze(wrapper: ReturnType<typeof mountPage>, input: string) {
    await wrapper.get('#header-input').setValue(input);
    await wrapper.get('form').trigger('submit');
}
it('loads an example without auto-analysis and invalidates edits, clear and history-restored input', async () => {
    const wrapper = mountPage();
    expect(wrapper.get('#copy-analysis').attributes('disabled')).toBeDefined();
    await wrapper.get('#example-headers').trigger('click');
    expect(wrapper.find('#header-results').exists()).toBe(false);
    wrapper.get<HTMLButtonElement>('#analyze-headers').element.focus();
    await wrapper.get('form').trigger('submit');
    expect(document.activeElement).toBe(wrapper.get('#analyze-headers').element);
    expect(wrapper.findAll('h2').map(n => n.text())).toEqual(expect.arrayContaining(['Message summary', 'Delivery journey', 'Authentication', 'Filtering details', 'All headers']));
    expect(wrapper.get('#copy-analysis').attributes('disabled')).toBeUndefined();
    await wrapper.get('#header-input').setValue('Subject: edited');
    expect(wrapper.find('#header-results').exists()).toBe(false);
    await wrapper.get('form').trigger('submit');
    expect(wrapper.get('#header-results').text()).toContain('edited');
    window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
    await flushPromises();
    expect(wrapper.get<HTMLTextAreaElement>('#header-input').element.value).toBe('');
    expect(wrapper.find('#header-results').exists()).toBe(false);
    await analyze(wrapper, 'Subject: private');
    await wrapper.get('#clear-headers').trigger('click');
    expect(document.activeElement).toBe(wrapper.get('#header-input').element);
    expect(wrapper.get('#download-analysis').attributes('disabled')).toBeDefined();
    wrapper.unmount();
});
it('shows actionable empty and oversized input errors', async () => {
    const wrapper = mountPage();
    await wrapper.get('form').trigger('submit');
    expect(wrapper.get('[role=alert]').text()).toContain('Paste message headers');
    await analyze(wrapper, 'X: ' + 'a'.repeat(1048576));
    expect(wrapper.get('[role=alert]').text()).toContain('1 MiB');
    wrapper.unmount();
});
it('renders evidence safely, duplicate summary values, negative timing and source-ordered search', async () => {
    const wrapper = mountPage();
    await analyze(wrapper, 'Subject: <img src=x onerror=alert(1)>\nSubject: duplicate\nReceived: by second; 23 Oct 2026 10:00:00 +0000\nReceived: by first; 23 Oct 2026 10:00:10 +0000\nX-Custom: FIRST\nx-custom: second');
    expect(wrapper.get('#header-results').text()).toContain('<img src=x onerror=alert(1)>');
    expect(wrapper.find('img').exists()).toBe(false);
    expect(wrapper.find('script').exists()).toBe(false);
    expect(wrapper.text()).toContain('appears more than once');
    expect(wrapper.text()).toContain('-10 s');
    await wrapper.get('#header-search').setValue('X-CUSTOM');
    expect(wrapper.findAll('[data-header-field]').map(n => n.text())).toEqual([expect.stringContaining('FIRST'), expect.stringContaining('second')]);
    await wrapper.get('#header-search').setValue('first');
    expect(wrapper.findAll('[data-header-field]')).toHaveLength(2);
    wrapper.unmount();
});
it('shows reported outcomes and distinct reporters, explicit none and manual-copy fallback', async () => {
    vi.stubGlobal('navigator', {});
    const wrapper = mountPage();
    await analyze(wrapper, 'Authentication-Results: one.example; spf=pass\nAuthentication-Results: two.example; none\nDKIM-Signature: v=1; d=example.org; s=mail');
    expect(wrapper.text()).toContain('Reported pass');
    expect(wrapper.text()).toContain('Reported none');
    expect(wrapper.text()).toContain('one.example');
    expect(wrapper.text()).toContain('two.example');
    expect(wrapper.text()).toContain('Signature metadata only');
    await wrapper.get('#copy-analysis').trigger('click'); await flushPromises();
    expect(wrapper.get<HTMLTextAreaElement>('#manual-copy').element.value).toContain('Authentication-Results: one.example');
    wrapper.unmount();
});
it('translates controls while leaving evidence unchanged', async () => {
    page.props.i18n = { locale: 'nl', messages: { 'Message Header Analyzer': 'Headeranalyse', 'Analyze headers': 'Headers analyseren', 'Message summary': 'Berichtgegevens', 'Your headers stay in your browser.': 'Je headers blijven in je browser.' } };
    const wrapper = mountPage();
    expect(wrapper.get('h1').text()).toBe('Headeranalyse');
    await analyze(wrapper, 'Subject: Raw evidence remains unchanged');
    expect(wrapper.text()).toContain('Berichtgegevens');
    expect(wrapper.text()).toContain('Raw evidence remains unchanged');
    wrapper.unmount();
});

it('erases a form value restored by native history even after pagehide already cleared reactive state', async () => {
    const wrapper = mountPage();
    await analyze(wrapper, 'Subject: private');
    window.dispatchEvent(new PageTransitionEvent('pagehide', { persisted: true }));
    await flushPromises();
    wrapper.get<HTMLTextAreaElement>('#header-input').element.value = 'Subject: browser restored private';
    window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
    await flushPromises();
    expect(wrapper.get<HTMLTextAreaElement>('#header-input').element.value).toBe('');
    wrapper.unmount();
});
