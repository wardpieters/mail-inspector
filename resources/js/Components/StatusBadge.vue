<script setup lang="ts">
import { computed } from 'vue';
import StatusIcon from './StatusIcon.vue';
import { useTranslations } from '../Composables/useTranslations';
const props = defineProps<{ outcome: string }>();
const { t } = useTranslations();
const labels: Record<string, string> = {
    pass: 'Pass', warning: 'Warning', fail: 'Fail', info: 'Info', skipped: 'Skipped',
    inconclusive: 'Inconclusive', not_applicable: 'Not applicable', pending: 'Pending',
    queued: 'Queued', running: 'Running', completed: 'Completed', partial: 'Partial',
    failed: 'Failed', expired: 'Expired',
};
const kind = computed(() => {
    if (props.outcome === 'pass') return 'success';
    if (props.outcome === 'warning') return 'warning';
    if (['fail', 'failed'].includes(props.outcome)) return 'failure';
    return 'info';
});
</script>
<template><span :class="['badge', 'status-badge', outcome]"><StatusIcon :kind="kind" />{{ t(labels[outcome] ?? 'Unknown status') }}</span></template>
