<script setup lang="ts">
import { useTranslations } from '../Composables/useTranslations';
defineProps<{ outcome: string }>();
const { t } = useTranslations();
const labels: Record<string, string> = {
    pass: 'Pass', warning: 'Warning', fail: 'Fail', info: 'Info', skipped: 'Skipped',
    inconclusive: 'Inconclusive', not_applicable: 'Not applicable', pending: 'Pending',
    queued: 'Queued', running: 'Running', completed: 'Completed', partial: 'Partial',
    failed: 'Failed', expired: 'Expired',
};
</script>
<template><span :class="['badge', outcome]">{{ t(labels[outcome] ?? 'Unknown status') }}</span></template>
