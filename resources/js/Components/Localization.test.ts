import { mount } from '@vue/test-utils';
import { defineComponent, nextTick, reactive } from 'vue';
import { expect, it, vi } from 'vitest';
import StatusBadge from './StatusBadge.vue';
import ScanProgress from './ScanProgress.vue';
import { useTranslations } from '../Composables/useTranslations';

const { page } = vi.hoisted(() => ({ page: { props: {} as Record<string, unknown> } }));
vi.mock('@inertiajs/vue3', async importOriginal => ({ ...await importOriginal<typeof import('@inertiajs/vue3')>(), usePage: () => page }));

it('translates statuses and progress using the current page locale, then falls back to readable English', async () => {
    page.props = reactive({ i18n: { locale: 'nl', messages: {
        Pending: 'In afwachting',
        'Checking mail endpoints': 'Mailservers controleren',
        ':completed / :planned addresses completed': ':completed / :planned adressen voltooid',
        'Endpoint scan progress': 'Voortgang van de adrescontrole',
        'Updates automatically': 'Wordt automatisch bijgewerkt',
    } } });
    const badge = mount(StatusBadge, { props: { outcome: 'pending' } });
    const progress = mount(ScanProgress, { props: {status:'running',coverage:{planned:2,completed:1,pending:1,failed:0,omitted:0,incomplete:true,ipv4:1,ipv6:1,not_run:0}} });
    expect(badge.text()).toBe('In afwachting');
    expect(progress.text()).toContain('1 / 2 adressen voltooid');
    expect(progress.get('[role=progressbar]').attributes('aria-label')).toBe('Voortgang van de adrescontrole');
    (page.props.i18n as {locale:string;messages:Record<string,string>}).messages = {};
    (page.props.i18n as {locale:string}).locale = 'en_US';
    await nextTick();
    expect(badge.text()).toBe('Pending');
    expect(progress.text()).toContain('1 / 2 addresses completed');
});

it('formats dates for the selected language rather than the machine locale', async () => {
    page.props = reactive({i18n: {locale: 'nl', messages: {'Not yet':'Nog niet'}}});
    const value = new Date(2026, 9, 11, 12, 0, 0).toISOString();
    const preview = mount(defineComponent({
        setup() { return {...useTranslations(), value}; },
        template: '<div><span>{{ date(value) }}</span><small>{{ date(null) }}</small></div>',
    }));
    expect(preview.get('span').text()).toBe('11-10-2026, 12:00:00');
    expect(preview.get('small').text()).toBe('Nog niet');
    (page.props.i18n as {locale:string}).locale = 'en_US';
    await nextTick();
    expect(preview.get('span').text()).toBe('10/11/2026, 12:00:00 PM');
});
