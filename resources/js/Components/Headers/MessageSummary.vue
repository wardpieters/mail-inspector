<script setup lang="ts">
import type { HeaderAnalysis } from '../../types/headers';
import { useTranslations } from '../../Composables/useTranslations';
import { SUMMARY_LABELS } from '../../Pages/Headers/analysis/formatHeaderAnalysis';
defineProps<{ analysis: HeaderAnalysis }>();
const { t } = useTranslations();
</script>
<template>
    <section class="panel header-result-panel" aria-labelledby="message-summary-title">
        <h2 id="message-summary-title">{{ t('Message summary') }}</h2>
        <dl class="header-summary">
            <div v-for="(fields, name) in analysis.summary" :key="name">
                <dt>{{ t(SUMMARY_LABELS[name]) }}</dt>
                <dd v-if="!fields.length" class="muted">{{ t('Not present') }}</dd>
                <dd v-for="field in fields" :key="field.id">{{ field.displayValue }}</dd>
            </div>
        </dl>
    </section>
</template>
