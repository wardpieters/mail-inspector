<script setup lang="ts">
import type { Coverage } from '../types/report';
import { computed } from 'vue';
const props=defineProps<{status: string; coverage: Coverage}>();
const active=computed(()=>['queued','running'].includes(props.status));
const percent=computed(()=>props.coverage.planned ? Math.round(100*(props.coverage.completed+props.coverage.failed)/props.coverage.planned) : 0);
</script>
<template><div class="panel progress-panel"><p role="status" aria-live="polite"><strong>{{ active ? (coverage.planned ? 'Checking mail endpoints' : 'Discovering DNS and mail routes') : 'Scan '+status }}</strong><span class="muted"> · {{ coverage.completed }} / {{ coverage.planned }} addresses completed</span></p><div v-if="coverage.planned" class="progress-bar" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" aria-label="Endpoint scan progress"><span :style="{width:percent+'%'}"></span></div><p class="help">{{ coverage.ipv4 }} IPv4 · {{ coverage.ipv6 }} IPv6<span v-if="coverage.failed"> · {{ coverage.failed }} unfinished</span><span v-if="coverage.omitted"> · {{ coverage.omitted }} omitted by the endpoint limit</span><span v-if="active"> · Updates automatically</span></p></div></template>
