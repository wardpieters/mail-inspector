<script setup lang="ts">
import type { HeaderAnalysis } from '../../types/headers';
import { useTranslations } from '../../Composables/useTranslations';
import { headerDuration, HEADER_LIMITATIONS } from '../../Pages/Headers/analysis/formatHeaderAnalysis';
import HeaderEvidence from './HeaderEvidence.vue';
defineProps<{ analysis: HeaderAnalysis }>();
const { t, date } = useTranslations();
</script>
<template>
    <section class="panel header-result-panel" aria-labelledby="delivery-journey-title">
        <h2 id="delivery-journey-title">{{ t('Delivery journey') }}</h2>
        <p class="help">{{ t('Oldest observed hop first. Delays compare adjacent Received timestamps; an unknown timestamp breaks the timing chain.') }}</p>
        <p v-if="!analysis.delivery.hops.length" class="muted">{{ t('No Received headers were found.') }}</p>
        <div v-else class="scroll-table header-hop-table" tabindex="0" role="region" :aria-label="t('Delivery journey')">
            <table>
                <thead><tr><th scope="col">{{ t('Hop') }}</th><th scope="col">{{ t('From server') }}</th><th scope="col">{{ t('Receiving server') }}</th><th scope="col">{{ t('Protocol') }}</th><th scope="col">{{ t('Received at') }}</th><th scope="col">{{ t('Delay') }}</th></tr></thead>
                <tbody>
                    <tr v-for="hop in analysis.delivery.hops" :key="hop.fieldId">
                        <td><span class="hop-number">{{ hop.hop }}</span></td>
                        <td class="mono">{{ hop.from ?? t('Unknown') }}<span v-if="hop.ips.length" class="help hop-ips">{{ hop.ips.join(', ') }}</span></td>
                        <td><span class="mono">{{ hop.by ?? t('Unknown') }}</span><HeaderEvidence :analysis="analysis" :field-ids="[hop.fieldId]" /></td>
                        <td class="mono">{{ hop.protocol ?? t('Unknown') }}</td>
                        <td><time v-if="hop.timestamp.iso" :datetime="hop.timestamp.iso">{{ date(hop.timestamp.iso) }}</time><span v-else>{{ t('Unknown') }}</span><span class="help hop-timestamp">{{ hop.timestamp.raw || t('Not present') }}</span></td>
                        <td :class="{ 'negative-delay': hop.delayMs !== null && hop.delayMs < 0 }">
                            <strong>{{ headerDuration(hop.delayMs, t) }}</strong>
                            <div v-if="hop.delayPercent !== null" class="hop-delay-bar" aria-hidden="true"><span :style="{ width: hop.delayPercent + '%' }" /></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="help">{{ t(HEADER_LIMITATIONS[2]) }}</p>
    </section>
</template>
