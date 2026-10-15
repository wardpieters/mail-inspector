<script setup lang="ts">
import type { Coverage } from '../types/report';
import { computed } from 'vue';
import { useTranslations } from '../Composables/useTranslations';
import StatusBadge from './StatusBadge.vue';
const props = defineProps<{status: string; coverage: Coverage}>();
const { t } = useTranslations();
const active = computed(() => ['queued', 'running'].includes(props.status));
const percent = computed(() => props.coverage.planned ? Math.round(100 * (props.coverage.completed + props.coverage.failed) / props.coverage.planned) : 0);
const phase = computed(() => props.status === 'queued' ? 'Waiting for a scan worker' : (props.coverage.planned ? 'Checking mail endpoints' : 'Discovering DNS and mail routes'));
</script>
<template>
    <div v-if="active || coverage.planned" :class="['panel', 'progress-panel', {'is-active': active}]">
        <p class="scan-phase" role="status" aria-live="polite">
            <span v-if="active" class="activity-indicator" aria-hidden="true"></span>
            <strong v-if="active">{{ t(phase) }}</strong>
            <strong v-else><StatusBadge :outcome="status" /></strong>
            <span v-if="coverage.planned" class="muted"> · {{ t(':completed / :planned addresses completed', {completed: coverage.completed, planned: coverage.planned}) }}</span>
        </p>
        <div v-if="coverage.planned || active" :class="['progress-bar', {'is-indeterminate': !coverage.planned}]" role="progressbar" :aria-valuenow="coverage.planned ? percent : undefined" :aria-valuemin="coverage.planned ? 0 : undefined" :aria-valuemax="coverage.planned ? 100 : undefined" :aria-label="t(coverage.planned ? 'Endpoint scan progress' : 'Scan activity')"><span :style="coverage.planned ? {width: percent + '%'} : undefined"></span></div>
        <p v-if="coverage.planned" class="help">{{ coverage.ipv4 }} IPv4 · {{ coverage.ipv6 }} IPv6<span v-if="coverage.failed"> · {{ t(':count unfinished', {count: coverage.failed}) }}</span><span v-if="coverage.omitted"> · {{ t(':count omitted by the endpoint limit', {count: coverage.omitted}) }}</span><span v-if="active"> · {{ t('Updates automatically') }}</span></p>
        <p v-else-if="active" class="help">{{ t('Results appear automatically as checks finish.') }}</p>
    </div>
</template>
