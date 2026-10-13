<script setup lang="ts">
import AssessmentLabel from './AssessmentLabel.vue';
import type { Report, ScanStatus } from '../types/report';
import { useTranslations } from '../Composables/useTranslations';
defineProps<{assessment: Report['assessment']; status: ScanStatus}>();
const { t } = useTranslations();
const titles: Record<string, string> = {reachability: 'Mail reachability', transport: 'Transport security', sender_authentication: 'Sender DNS policy'};
const descriptions: Record<string, string> = {reachability: 'MX routing and SMTP response on every address.', transport: 'Encryption, certificate identity, and policy enforcement.', sender_authentication: 'SPF, DKIM key publication, and DMARC policy.'};
</script>
<template>
    <div class="grid">
        <article v-for="(dimension, key) in assessment.dimensions" :key="key" :class="['panel', 'stat', {'is-pending': dimension.pending && ['queued', 'running'].includes(status)}]">
            <p class="eyebrow">{{ t(titles[key]) }}</p>
            <strong v-if="dimension.pending && ['queued', 'running'].includes(status)" class="pending-summary"><span class="activity-indicator" aria-hidden="true"></span>{{ t(status === 'queued' ? 'Waiting for results' : 'Checking…') }}</strong>
            <strong v-else><AssessmentLabel :label="dimension.label" /></strong>
            <p class="help">{{ t(descriptions[key]) }}</p>
        </article>
    </div>
</template>
