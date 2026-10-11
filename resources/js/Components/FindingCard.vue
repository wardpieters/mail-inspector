<script setup lang="ts">
import { checkLabels, type Finding } from '../types/report';
import StatusBadge from './StatusBadge.vue';
defineProps<{finding: Finding}>();
</script>
<template><article class="panel finding"><div class="finding-head"><div><p class="eyebrow finding-label">{{ checkLabels[finding.key] ?? finding.key.replaceAll('_',' ') }}<span v-if="finding.scope !== 'domain'"> · {{ finding.scope }}</span></p><h3>{{ finding.title }}</h3></div><StatusBadge :outcome="finding.outcome"/></div><p class="muted">{{ finding.explanation }}</p><p v-if="finding.recommendation" class="recommendation">{{ finding.recommendation }}</p><details v-if="Object.keys(finding.evidence).length"><summary>Evidence &amp; observations</summary><pre>{{ JSON.stringify(finding.evidence,null,2) }}</pre></details></article></template>
