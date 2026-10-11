import { mount } from '@vue/test-utils';
import { defineComponent, toRef } from 'vue';
import { router } from '@inertiajs/vue3';
import { afterEach, expect, it, vi } from 'vitest';
import { useScanPolling } from './useScanPolling';
afterEach(() => { vi.useRealTimers(); vi.restoreAllMocks(); });
it('reloads only the report and stops at terminal state and unmount', async () => {
 vi.useFakeTimers(); const reload = vi.fn();
 vi.spyOn(router,'poll').mockImplementation((interval,options) => {
  let timer: ReturnType<typeof setInterval> | undefined;
  const stop = () => {clearInterval(timer); timer=undefined;};
  return { start: () => {stop(); timer=setInterval(() => reload(typeof options === 'function' ? options() : options),interval);}, stop, destroy: stop };
 });
 const component = defineComponent({ props: { status: {type: String,required: true} }, setup(props) { useScanPolling(toRef(props,'status')); return () => null; } });
 const wrapper = mount(component, { props: {status: 'running'} });
 await vi.advanceTimersByTimeAsync(2000); expect(reload).toHaveBeenCalledWith(expect.objectContaining({only: ['report']}));
 await wrapper.setProps({status: 'completed'}); expect(vi.getTimerCount()).toBe(0);
 wrapper.unmount(); expect(vi.getTimerCount()).toBe(0);
});
