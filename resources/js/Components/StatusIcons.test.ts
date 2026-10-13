import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { expect, it, vi } from 'vitest';
import StatusBadge from './StatusBadge.vue';
import SummaryCards from './SummaryCards.vue';

const { page } = vi.hoisted(() => ({page:{props:{} as Record<string, unknown>}}));
vi.mock('@inertiajs/vue3', async importOriginal => ({...await importOriginal<typeof import('@inertiajs/vue3')>(),usePage:()=>page}));

it.each([['pass','success','Pass'],['warning','warning','Warning'],['fail','failure','Fail'],['inconclusive','info','Inconclusive']])('shows a %s icon alongside the readable badge label', (outcome,kind,label) => {
    page.props = {};
    const wrapper = mount(StatusBadge,{props:{outcome}});
    expect(wrapper.text()).toBe(label);
    expect(wrapper.get('svg').classes()).toContain('status-icon--'+kind);
    expect(wrapper.get('svg').attributes('aria-hidden')).toBe('true');
});

it.each([
    ['en_US',{},'Checks passed','Attention needed','Assessment incomplete'],
    ['nl',{'Checks passed':'Controles geslaagd','Attention needed':'Aandacht nodig','Assessment incomplete':'Beoordeling onvolledig'},'Controles geslaagd','Aandacht nodig','Beoordeling onvolledig'],
])('uses the same verdict icons for %s summary labels', (locale,messages,pass,warning,incomplete) => {
    page.props = reactive({i18n:{locale,messages}});
    const wrapper = mount(SummaryCards,{props:{status:'completed',assessment:{overall:String(warning),pending:false,coverage:{planned:0,completed:0,pending:0,failed:0,omitted:0,incomplete:true,ipv4:0,ipv6:0,not_run:0},dimensions:{reachability:{label:String(pass),pending:false,outcomes:{}},transport:{label:String(warning),pending:false,outcomes:{}},sender_authentication:{label:String(incomplete),pending:false,outcomes:{}}}}}});
    expect(wrapper.findAll('svg').map(svg=>svg.classes().find(name=>name.startsWith('status-icon--')))).toEqual(['status-icon--success','status-icon--warning','status-icon--info']);
    expect(wrapper.text()).toContain(String(incomplete));
});
