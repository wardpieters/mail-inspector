<script setup lang="ts">
import type { Coverage } from '../types/report';
import { computed } from 'vue';
import { useTranslations } from '../Composables/useTranslations';
import StatusBadge from './StatusBadge.vue';
const props = defineProps<{status: string; coverage: Coverage}>();
const { t } = useTranslations();
const active = computed(() => ['queued', 'running'].includes(props.status));
const percent = computed(() => props.coverage.planned ? Math.round(100 * (props.coverage.completed + props.coverage.failed) / props.coverage.planned) : 0);
</script>
<template>
    <div class="panel progress-panel">
        <p role="status" aria-live="polite">
            <strong v-if="active">{{ t(coverage.planned ? 'Checking mail endpoints' : 'Discovering DNS and mail routes') }}</strong>
            <strong v-else><StatusBadge :outcome="status" /></strong>
            <span class="muted"> · {{ t(':completed / :planned addresses completed', {completed: coverage.completed, planned: coverage.planned}) }}</span>
        </p>
        <div v-if="coverage.planned" class="progress-bar" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" :aria-label="t('Endpoint scan progress')"><span :style="{width: percent + '%'}"></span></div>
        <p class="help">{{ coverage.ipv4 }} IPv4 · {{ coverage.ipv6 }} IPv6<span v-if="coverage.failed"> · {{ t(':count unfinished', {count: coverage.failed}) }}</span><span v-if="coverage.omitted"> · {{ t(':count omitted by the endpoint limit', {count: coverage.omitted}) }}</span><span v-if="active"> · {{ t('Updates automatically') }}</span></p>
    </div>
</template>
