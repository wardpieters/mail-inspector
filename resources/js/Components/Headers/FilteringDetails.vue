<script setup lang="ts">
import type { HeaderAnalysis } from '../../types/headers';
import { useTranslations } from '../../Composables/useTranslations';
import { HEADER_LIMITATIONS } from '../../Pages/Headers/analysis/formatHeaderAnalysis';
import HeaderEvidence from './HeaderEvidence.vue';
defineProps<{ analysis: HeaderAnalysis }>();
const { t } = useTranslations();
const families: Record<string, string> = { rspamd: 'Rspamd', 'spamassassin-compatible': 'SpamAssassin-compatible', microsoft: 'Microsoft', barracuda: 'Barracuda', proofpoint: 'Proofpoint', ironport: 'IronPort', generic: 'Generic filtering evidence' };
</script>
<template>
    <section class="panel header-result-panel" aria-labelledby="filtering-title">
        <h2 id="filtering-title">{{ t('Filtering details') }}</h2>
        <p class="help">{{ t(HEADER_LIMITATIONS[3]) }} {{ t('Provider names are indicative; headers do not authenticate their source.') }}</p>
        <p v-if="!analysis.filtering.observations.length" class="muted">{{ t('No recognized filtering headers were found.') }}</p>
        <article v-for="observation in analysis.filtering.observations" :key="observation.sourceFieldIds[0]" class="header-observation">
            <h3>{{ t(families[observation.family]) }} <span class="badge">{{ analysis.document.fields[observation.sourceFieldIds[0]].name }}</span></h3>
            <dl class="header-filter-metrics">
                <div v-if="observation.verdict !== null"><dt>{{ t('Reported verdict') }}</dt><dd>{{ /^(yes|true)$/i.test(observation.verdict) ? t('Reported spam') : /^(no|false)$/i.test(observation.verdict) ? t('Reported not spam') : observation.verdict }}</dd></div>
                <div v-if="observation.score !== null"><dt>{{ t('Reported score') }}</dt><dd class="mono">{{ observation.score }}<span v-if="observation.threshold !== null"> / {{ observation.threshold }} <span class="help">{{ t('threshold') }}</span></span></dd></div>
                <div v-if="observation.action !== null"><dt>{{ t('Reported action') }}</dt><dd class="mono">{{ observation.action }}</dd></div>
                <div v-if="observation.metric !== null"><dt>{{ t('Metric') }}</dt><dd class="mono">{{ observation.metric }}</dd></div>
            </dl>
            <dl class="header-properties"><div v-for="(value, index) in observation.values.slice(1)" :key="index"><dt class="mono">{{ value.name }}</dt><dd><span class="mono">{{ value.value }}</span><span v-if="value.explanationKey" class="help field-explanation">{{ t(value.explanationKey) }}</span></dd></div></dl>
            <div v-if="observation.rules.length" class="scroll-table" tabindex="0" role="region" :aria-label="t('Filter rules')">
                <table><thead><tr><th scope="col">{{ t('Rule or symbol') }}</th><th scope="col">{{ t('Score') }}</th><th scope="col">{{ t('Options') }}</th></tr></thead>
                    <tbody><tr v-for="(rule, index) in observation.rules" :key="index"><td class="mono">{{ rule.name }}</td><td class="mono">{{ rule.score ?? t('Not reported') }}</td><td class="mono">{{ rule.options ?? t('Not reported') }}</td></tr></tbody>
                </table>
            </div>
            <details v-if="observation.unknownFragments.length"><summary>{{ t('Uninterpreted details') }}</summary><pre v-for="(fragment, index) in observation.unknownFragments" :key="index">{{ fragment }}</pre></details>
            <p v-if="!observation.rules.length && observation.score === null && observation.action === null" class="help">{{ t('Inspect the original header for provider-specific details.') }}</p>
            <HeaderEvidence :analysis="analysis" :field-ids="observation.sourceFieldIds" />
        </article>
    </section>
</template>
