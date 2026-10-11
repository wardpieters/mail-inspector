<script setup lang="ts">
import type { Report } from '../types/report';
import { useTranslations } from '../Composables/useTranslations';
defineProps<{assessment: Report['assessment']}>();
const { t } = useTranslations();
const titles: Record<string, string> = {reachability: 'Mail reachability', transport: 'Transport security', sender_authentication: 'Sender DNS policy'};
const descriptions: Record<string, string> = {reachability: 'MX routing and SMTP response on every address.', transport: 'Encryption, certificate identity, and policy enforcement.', sender_authentication: 'SPF, DKIM key publication, and DMARC policy.'};
</script>
<template><div class="grid"><article v-for="(dimension, key) in assessment.dimensions" :key="key" class="panel stat"><p class="eyebrow">{{ t(titles[key]) }}</p><strong>{{ dimension.label }}</strong><p class="help">{{ t(descriptions[key]) }}</p></article></div></template>
