<script setup lang="ts">
import { checkLabel, type Finding } from '../types/report';
import { useTranslations } from '../Composables/useTranslations';
import StatusBadge from './StatusBadge.vue';
defineProps<{finding: Finding}>();
const { t } = useTranslations();
</script>
<template>
    <article class="panel finding">
        <div class="finding-head"><div><p class="eyebrow finding-label">{{ t(checkLabel(finding.key)) }}<span v-if="finding.scope !== 'domain'"> · {{ finding.scope === 'null_mx' ? t('Null MX') : finding.scope }}</span></p><h3>{{ finding.title }}</h3></div><StatusBadge :outcome="finding.outcome" /></div>
        <p class="muted">{{ finding.explanation }}</p>
        <p v-if="finding.recommendation" class="recommendation">{{ finding.recommendation }}</p>
        <details v-if="Object.keys(finding.evidence).length"><summary>{{ t('Evidence & observations') }}</summary><pre>{{ JSON.stringify(finding.evidence, null, 2) }}</pre></details>
    </article>
</template>
