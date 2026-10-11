import { usePoll } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount, watch, type Ref } from 'vue';
export function useScanPolling(status: Ref<string>): void {
 const {start, stop} = usePoll(2000, {only: ['report']}, {autoStart: false, mode: 'rest'});
 const sync = () => ['queued','running'].includes(status.value) ? start() : stop();
 onMounted(sync); watch(status,sync); onBeforeUnmount(stop);
}
