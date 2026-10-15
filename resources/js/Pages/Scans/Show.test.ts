import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Show from './Show.vue';
import type { Endpoint, Report } from '../../types/report';

vi.mock('@inertiajs/vue3', async importOriginal => ({
    ...await importOriginal<typeof import('@inertiajs/vue3')>(),
    Head: { render: () => null },
    Link: { template: '<a><slot /></a>' },
    usePage: () => ({ props: {} }),
    usePoll: () => ({ start: vi.fn(), stop: vi.fn() }),
}));

let wrapper: VueWrapper | undefined;

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-10-15T11:00:00Z'));
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = undefined;
    vi.useRealTimers();
});

function mountReport(availableAt = '2026-10-15T12:01:00Z', endpoints: Endpoint[] = []): VueWrapper {
    const report: Report = {
        scan: {
            public_id: '00000000-0000-4000-8000-000000000001', domain: 'example.com',
            selectors: [], status: 'completed', created_at: '2026-10-15T11:58:00Z',
            started_at: '2026-10-15T11:58:01Z', finished_at: '2026-10-15T11:59:00Z',
            deadline: '2026-10-15T12:03:00Z', expires_at: '2026-10-22T11:58:00Z',
            scanner_version: '1.0', error: null, null_mx: false,
        },
        assessment: {
            overall: 'No issues observed', pending: false, dimensions: {},
            coverage: { planned: 0, completed: 0, pending: 0, failed: 0, omitted: 0, incomplete: false, ipv4: 0, ipv6: 0, not_run: 0 },
        },
        domain_findings: [], endpoints, capabilities: {},
        export_url: '/scans/report/export', home_url: '/', limitations: [],
    };
    return mount(Show, {
        props: { report, cooldown: { available_at: availableAt, server_now: '2026-10-15T12:00:00Z' } },
        global: { stubs: { Head: true, Link: true, AppLayout: { template: '<main><slot /></main>' }, SummaryCards: true, ScanProgress: true } },
    });
}

it('counts down using server time and disappears at zero', async () => {
    wrapper = mountReport();
    expect(wrapper.get('[role="timer"]').text()).toBe('Scan again in 1:00');

    await vi.advanceTimersByTimeAsync(1000);
    expect(wrapper.get('[role="timer"]').text()).toBe('Scan again in 0:59');

    await vi.advanceTimersByTimeAsync(59000);
    expect(wrapper.find('[role="timer"]').exists()).toBe(false);
    expect(vi.getTimerCount()).toBe(0);
});

it('hides the countdown when the report cooldown has already expired', () => {
    wrapper = mountReport('2026-10-15T12:00:00Z');
    expect(wrapper.find('[role="timer"]').exists()).toBe(false);
    expect(vi.getTimerCount()).toBe(0);
});

it('updates the countdown for another report and cleans up when leaving the page', async () => {
    wrapper = mountReport();
    await vi.advanceTimersByTimeAsync(10000);
    await wrapper.setProps({ cooldown: { available_at: '2026-10-15T12:03:10Z', server_now: '2026-10-15T12:00:10Z' } });
    expect(wrapper.get('[role="timer"]').text()).toBe('Scan again in 3:00');
    expect(vi.getTimerCount()).toBe(1);

    wrapper.unmount();
    wrapper = undefined;
    expect(vi.getTimerCount()).toBe(0);
});

function mailEndpoint(ip: string): Endpoint {
    return {
        mx: 'example-com.mail.protection.outlook.com', preference: 10, ip, family: 4,
        status: 'completed', discovery_secure: false,
        findings: [{key: 'connect', scope: ip, outcome: 'pass', severity: 'info', title: 'SMTP connection succeeded', explanation: 'The server accepted a connection.', recommendation: null, evidence: {}, duration_ms: 10}],
    };
}

it('keeps endpoint summaries visible with findings collapsed by default and allows toggling', async () => {
    wrapper = mountReport(undefined, [mailEndpoint('192.0.2.1'), mailEndpoint('192.0.2.2')]);
    const endpoints = wrapper.findAll<HTMLDetailsElement>('details.endpoint');
    expect(endpoints).toHaveLength(2);
    expect(endpoints.every(endpoint => !endpoint.element.open)).toBe(true);
    expect(endpoints[0].get('summary').text()).toContain('192.0.2.1');
    expect(endpoints[0].get('summary').text()).toContain('Completed');

    endpoints[0].get('summary').element.click();
    expect(endpoints[0].element.open).toBe(true);
    expect(endpoints[1].element.open).toBe(false);
    expect(endpoints[0].text()).toContain('SMTP connection succeeded');
    endpoints[0].get('summary').element.click();
    expect(endpoints[0].element.open).toBe(false);
});

it('opens the matching endpoint from the comparison table and preserves it through report updates', async () => {
    wrapper = mountReport(undefined, [mailEndpoint('192.0.2.1'), mailEndpoint('192.0.2.2')]);
    await wrapper.get('.scroll-table a[href="#endpoint-1"]').trigger('click');
    const endpoints = wrapper.findAll<HTMLDetailsElement>('details.endpoint');
    expect(endpoints).toHaveLength(2);
    expect(endpoints[0].element.open).toBe(false);
    expect(endpoints[1].element.open).toBe(true);

    const { report } = wrapper.props() as {report: Report};
    await wrapper.setProps({report: {...report, endpoints: report.endpoints.map(endpoint => ({...endpoint, status: 'running'}))}});
    expect(endpoints[1].element.open).toBe(true);
});

it('does not show endpoint disclosures when there are no endpoints', () => {
    wrapper = mountReport();
    expect(wrapper.find('details.endpoint').exists()).toBe(false);
    expect(wrapper.find('.scroll-table').exists()).toBe(false);
});
