import { mount } from '@vue/test-utils';
import { expect, it } from 'vitest';
import ScanProgress from './ScanProgress.vue';
import SummaryCards from './SummaryCards.vue';

it('shows indeterminate queue activity until counts are known and stops activity after completion', async () => {
    const wrapper = mount(ScanProgress, {props:{status:'queued',coverage:{planned:0,completed:0,pending:0,failed:0,omitted:0,incomplete:true,ipv4:0,ipv6:0,not_run:0}}});
    expect(wrapper.text()).toContain('Waiting for a scan worker');
    expect(wrapper.get('[role=progressbar]').attributes('aria-valuenow')).toBeUndefined();
    expect(wrapper.find('.activity-indicator').exists()).toBe(true);
    expect(wrapper.text()).not.toContain('0 / 0');
    await wrapper.setProps({status:'running',coverage:{planned:2,completed:1,pending:1,failed:0,omitted:0,incomplete:true,ipv4:1,ipv6:1,not_run:0}});
    expect(wrapper.get('[role=progressbar]').attributes('aria-valuenow')).toBe('50');
    expect(wrapper.text()).toContain('1 / 2 addresses completed');
    await wrapper.setProps({status:'completed'});
    expect(wrapper.find('.activity-indicator').exists()).toBe(false);
    expect(wrapper.classes()).not.toContain('is-active');
});

it('shows checking for unfinished summaries while preserving observed warnings and final labels', async () => {
    const assessment = {overall:'Attention needed',pending:false,coverage:{planned:0,completed:0,pending:0,failed:0,omitted:0,incomplete:true,ipv4:0,ipv6:0,not_run:0},dimensions:{transport:{label:'Assessment incomplete',outcomes:{},pending:true},sender_authentication:{label:'Attention needed',outcomes:{fail:1},pending:false}}};
    const wrapper = mount(SummaryCards, {props:{assessment,status:'running'}});
    expect(wrapper.text()).toContain('Checking…');
    expect(wrapper.text()).toContain('Attention needed');
    expect(wrapper.text()).not.toContain('Assessment incomplete');
    await wrapper.setProps({status:'partial'});
    expect(wrapper.text()).toContain('Assessment incomplete');
    expect(wrapper.find('.activity-indicator').exists()).toBe(false);
});

it.each(['completed', 'partial', 'failed'])('hides the progress card for a %s scan without endpoints', async status => {
    const wrapper = mount(ScanProgress, {props:{status:'queued',coverage:{planned:0,completed:0,pending:0,failed:0,omitted:0,incomplete:false,ipv4:0,ipv6:0,not_run:0}}});
    expect(wrapper.find('.progress-panel').exists()).toBe(true);

    await wrapper.setProps({status});
    expect(wrapper.find('.progress-panel').exists()).toBe(false);
});
