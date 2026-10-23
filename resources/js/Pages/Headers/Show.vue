<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusIcon from '../../Components/StatusIcon.vue';
import MessageSummary from '../../Components/Headers/MessageSummary.vue';
import DeliveryJourney from '../../Components/Headers/DeliveryJourney.vue';
import AuthenticationResults from '../../Components/Headers/AuthenticationResults.vue';
import FilteringDetails from '../../Components/Headers/FilteringDetails.vue';
import HeaderList from '../../Components/Headers/HeaderList.vue';
import { useHeaderAnalyzer } from '../../Composables/useHeaderAnalyzer';
import { useTranslations } from '../../Composables/useTranslations';
import { ERROR_LABELS, headerIssueText, headerDuration } from './analysis/formatHeaderAnalysis';
const { t } = useTranslations();
const { input, analysis, error, status, manualCopy, analyze, clear, loadExample, copyAnalysis, downloadJson } = useHeaderAnalyzer();
const textarea = ref<HTMLTextAreaElement | null>(null);
function resetRestoredInput(): void {
    clear();
    if (textarea.value) { textarea.value.value = ''; }
}
onMounted(() => { resetRestoredInput(); window.addEventListener('pageshow', resetRestoredInput); });
onUnmounted(() => window.removeEventListener('pageshow', resetRestoredInput));
async function clearInput(): Promise<void> { clear(); await nextTick(); textarea.value?.focus(); }
</script>
<template>
    <AppLayout>
        <Head :title="t('Message Header Analyzer')" />
        <div class="header-analyzer">
            <section class="header-tool-heading">
                <p class="eyebrow">{{ t('MESSAGE DIAGNOSTICS') }}</p>
                <h1>{{ t('Message Header Analyzer') }}</h1>
                <p class="lede">{{ t('Follow a message’s route. Understand delivery delays, authentication results, and the filters it passed through.') }}</p>
            </section>
            <form class="panel header-input-panel" autocomplete="off" @submit.prevent="analyze">
                <div class="header-input-title">
                    <label for="header-input">{{ t('Message headers') }}</label>
                    <span class="local-processing"><StatusIcon kind="success" />{{ t('Analyzed locally') }}</span>
                </div>
                <textarea id="header-input" ref="textarea" v-model="input" class="mono" rows="12" autocomplete="off" autocapitalize="none" spellcheck="false" :placeholder="t('Paste the original message headers here…')" :aria-invalid="!!error" :aria-describedby="error ? 'header-input-help header-error' : 'header-input-help'" />
                <p id="header-input-help" class="help">{{ t('Your headers stay in your browser.') }} {{ t('Nothing is uploaded or saved. Reloading or leaving this page clears the analysis.') }}</p>
                <p v-if="error" id="header-error" class="error" role="alert">{{ t(ERROR_LABELS[error.code]) }}</p>
                <div class="header-input-actions">
                    <button id="analyze-headers" class="primary" type="submit">{{ t('Analyze headers') }} <span aria-hidden="true">→</span></button>
                    <button id="clear-headers" class="secondary-button" type="button" @click="clearInput">{{ t('Clear') }}</button>
                    <button id="example-headers" class="text-button" type="button" @click="loadExample">{{ t('Load example') }}</button>
                </div>
                <details class="header-help">
                    <summary>{{ t('Where do I find message headers?') }}</summary>
                    <p class="help">{{ t('Gmail: open the message menu and choose Show original. Outlook: open the message details or source. Thunderbird: choose View Source. Copy the internet headers above the first blank line.') }}</p>
                    <p class="help">{{ t('Paste only headers. If you paste a complete message, its body is excluded from the analysis and exports.') }}</p>
                    <p class="help">{{ t('Maximum input: 1 MiB, 10,000 lines, and 2,000 header fields.') }}</p>
                </details>
            </form>
            <div class="header-export-bar">
                <div class="header-export-actions">
                    <button id="copy-analysis" class="secondary-button" type="button" :disabled="!analysis" @click="copyAnalysis(t)">{{ t('Copy analysis') }}</button>
                    <button id="download-analysis" class="secondary-button" type="button" :disabled="!analysis" @click="downloadJson">{{ t('Download JSON') }}</button>
                </div>
                <p class="help">{{ t('Copied and downloaded reports can contain personal information. Share them carefully.') }}</p>
            </div>
            <p class="header-tool-status help" role="status" aria-live="polite">{{ status ? t(status) : '' }}</p>
            <div v-if="manualCopy" class="panel">
                <label for="manual-copy">{{ t('Select and copy the report') }}</label>
                <textarea id="manual-copy" :value="manualCopy" rows="8" readonly class="mono" />
            </div>
            <div v-if="analysis" id="header-results">
                <div class="header-result-heading"><h2>{{ t('Analysis results') }}</h2><span class="badge">{{ t('Temporary · this browser only') }}</span></div>
                <div class="header-glance">
                    <div><span>{{ t('Observed hops') }}</span><strong>{{ analysis.delivery.hops.length }}</strong></div>
                    <div><span>{{ t('Observed transit time') }}</span><strong>{{ headerDuration(analysis.delivery.observedTransitMs, t) }}</strong></div>
                    <div><span>{{ t('Header fields') }}</span><strong>{{ analysis.document.fields.length }}</strong></div>
                </div>
                <aside v-if="analysis.warnings.length" class="header-analysis-notes" aria-labelledby="header-notes-title">
                    <h3 id="header-notes-title"><StatusIcon kind="warning" /> {{ t('Analysis notes') }}</h3>
                    <ul><li v-for="(issue, index) in analysis.warnings" :key="index">{{ headerIssueText(issue, t) }}</li></ul>
                </aside>
                <MessageSummary :analysis="analysis" />
                <DeliveryJourney :analysis="analysis" />
                <AuthenticationResults :analysis="analysis" />
                <FilteringDetails :analysis="analysis" />
                <HeaderList :analysis="analysis" />
            </div>
        </div>
    </AppLayout>
</template>
