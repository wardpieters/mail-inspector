<script setup lang="ts">
import { computed } from 'vue';
import type { HeaderAnalysis } from '../../types/headers';
import { useTranslations } from '../../Composables/useTranslations';
import { HEADER_LIMITATIONS, reportedResult } from '../../Pages/Headers/analysis/formatHeaderAnalysis';
import StatusIcon from '../StatusIcon.vue';
import HeaderEvidence from './HeaderEvidence.vue';
const props = defineProps<{ analysis: HeaderAnalysis }>();
const { t } = useTranslations();
const groups = computed(() => [...new Set(props.analysis.authentication.observations.map(o => o.fieldId))].map(fieldId => ({ fieldId, observations: props.analysis.authentication.observations.filter(o => o.fieldId === fieldId) })));
</script>
<template>
    <section class="panel header-result-panel" aria-labelledby="authentication-title">
        <h2 id="authentication-title">{{ t('Authentication') }}</h2>
        <p class="help">{{ t(HEADER_LIMITATIONS[0]) }} {{ t(HEADER_LIMITATIONS[1]) }}</p>
        <p v-if="!groups.length" class="muted">{{ t('No authentication evidence was found.') }}</p>
        <article v-for="group in groups" :key="group.fieldId" class="header-observation">
            <h3>{{ group.observations[0].reporter ?? t('Unspecified reporter') }} <span class="badge">{{ analysis.document.fields[group.fieldId].name }}</span></h3>
            <div v-for="(observation, index) in group.observations" :key="index" class="auth-observation">
                <div class="auth-result"><strong class="mono">{{ observation.method.toUpperCase() }}</strong>
                    <span class="badge reported-badge"><StatusIcon :kind="observation.kind === 'result' && observation.result === 'pass' ? 'success' : observation.result === 'fail' ? 'failure' : 'info'" />
                        {{ observation.kind === 'result' ? reportedResult(observation.result, t) : t('Signature metadata only') }}
                    </span>
                    <span v-if="observation.instance !== null" class="help">{{ t('ARC instance :instance', { instance: observation.instance }) }}</span>
                </div>
                <dl v-if="observation.properties.length" class="header-properties"><div v-for="(property, propertyIndex) in observation.properties" :key="propertyIndex"><dt class="mono">{{ property.name }}</dt><dd class="mono">{{ property.value }}</dd></div></dl>
            </div>
            <HeaderEvidence :analysis="analysis" :field-ids="[group.fieldId]" />
        </article>
    </section>
</template>
