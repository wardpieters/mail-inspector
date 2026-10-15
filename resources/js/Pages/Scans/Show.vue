<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SummaryCards from '../../Components/SummaryCards.vue';
import FindingCard from '../../Components/FindingCard.vue';
import EndpointMatrix from '../../Components/EndpointMatrix.vue';
import ScanProgress from '../../Components/ScanProgress.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AssessmentLabel from '../../Components/AssessmentLabel.vue';
import { useScanPolling } from '../../Composables/useScanPolling';
import { useTranslations } from '../../Composables/useTranslations';
import type { Report } from '../../types/report';
const props = defineProps<{report: Report; message?: string; cooldown: {available_at: string; server_now: string}}>();
const { t, date } = useTranslations();
useScanPolling(computed(() => props.report.scan.status));
const active = computed(() => ['queued', 'running'].includes(props.report.scan.status));
const remainingSeconds = ref(0);
const endpointDetails = ref<HTMLDetailsElement[]>([]);

function revealEndpoint(index: number): void {
    const endpoint = endpointDetails.value.find(details => details.id === 'endpoint-' + index);
    if (endpoint) {
        endpoint.open = true;
    }
}

const countdown = computed(() => `${Math.floor(remainingSeconds.value / 60)}:${String(remainingSeconds.value % 60).padStart(2, '0')}`);

watch(() => props.cooldown, (cooldown, _previous, onCleanup) => {
    const clockOffset = Date.parse(cooldown.server_now) - Date.now();
    const availableAt = Date.parse(cooldown.available_at);
    let timer: ReturnType<typeof setInterval> | undefined;
    const update = () => {
        remainingSeconds.value = Math.max(0, Math.ceil((availableAt - Date.now() - clockOffset) / 1000));
        if (remainingSeconds.value === 0 && timer !== undefined) {
            clearInterval(timer);
        }
    };

    update();
    if (remainingSeconds.value > 0) {
        timer = setInterval(update, 1000);
    }
    onCleanup(() => {
        if (timer !== undefined) {
            clearInterval(timer);
        }
    });
}, {immediate: true});
</script>
<template>
    <AppLayout>
        <Head :title="report.scan.domain" />
        <section class="scan-heading">
            <div class="section-title">
                <div><p class="eyebrow">{{ t('DOMAIN CONFIGURATION REPORT') }}</p><h1>{{ report.scan.domain }}</h1><p class="muted report-assessment"><span v-if="active && report.assessment.pending">{{ t(report.scan.status === 'queued' ? 'Waiting for results' : 'Scan in progress') }}</span><AssessmentLabel v-else :label="report.assessment.overall" /><span v-if="active"> · {{ t('Observations are still arriving') }}</span></p></div>
                <div class="actions"><Link :href="report.home_url">{{ t('New scan') }}</Link><a :href="report.export_url" class="export-link">{{ t('Export JSON ↗') }}</a></div>
            </div>
            <p v-if="remainingSeconds > 0" class="help mono" role="timer" aria-live="off">{{ t('Scan again in :time', {time: countdown}) }}</p>
            <p v-if="message" class="help">{{ message }}</p>
            <p v-if="report.scan.error" class="error" role="alert">{{ report.scan.error }}</p>
            <ScanProgress :status="report.scan.status" :coverage="report.assessment.coverage" />
            <div v-if="report.scan.null_mx" class="panel null-mx-notice"><h2>{{ t('Mail is intentionally disabled') }}</h2><p class="muted">{{ t('This domain publishes a valid null MX. Incoming SMTP checks are not applicable; sender DNS policies are shown independently.') }}</p></div>
            <SummaryCards :assessment="report.assessment" :status="report.scan.status" />
            <div class="section-title report-section-title"><h2>{{ t('DNS & domain policies') }}</h2><span class="help">{{ t(report.domain_findings.length === 1 ? ':count observation' : ':count observations', {count: report.domain_findings.length}) }}</span></div>
            <p v-if="!report.domain_findings.length" class="muted">{{ t('Waiting for DNS discovery and policy checks.') }}</p>
            <FindingCard v-for="finding in report.domain_findings" :key="finding.key + finding.scope" :finding="finding" />
            <div v-if="report.endpoints.length" class="section-title report-section-title"><h2>{{ t('Mail endpoints') }}</h2><span class="help">{{ t('All priorities · both address families') }}</span></div>
            <EndpointMatrix v-if="report.endpoints.length" :endpoints="report.endpoints" @select="revealEndpoint" />
            <details v-for="(endpoint, index) in report.endpoints" ref="endpointDetails" :id="'endpoint-' + index" :key="endpoint.mx + endpoint.ip" class="endpoint">
                <summary class="endpoint-toggle">
                    <span class="endpoint-overview"><span class="endpoint-name">{{ endpoint.mx }}</span><span class="tags"><span class="badge">{{ t('Priority :priority', {priority: endpoint.preference}) }}</span><span class="badge">IPv{{ endpoint.family }}</span><span class="mono">{{ endpoint.ip }}</span></span></span>
                    <StatusBadge :outcome="endpoint.status" />
                </summary>
                <p v-if="!endpoint.findings.length" class="muted">{{ t(endpoint.status === 'expired' || endpoint.status === 'failed' ? 'No endpoint observations returned.' : 'Waiting for this address to be inspected.') }}</p>
                <FindingCard v-for="finding in endpoint.findings" :key="finding.key + finding.scope" :finding="finding" />
            </details>
            <section class="panel report-notes">
                <h2>{{ t('About these observations') }}</h2><p v-for="note in report.limitations" :key="note" class="help">{{ note }}</p>
                <dl class="metadata"><div><dt>{{ t('Started') }}</dt><dd>{{ date(report.scan.started_at) }}</dd></div><div><dt>{{ t('Finished') }}</dt><dd>{{ date(report.scan.finished_at) }}</dd></div><div><dt>{{ t('Report expires') }}</dt><dd>{{ date(report.scan.expires_at) }}</dd></div><div><dt>{{ t('Scanner') }}</dt><dd>{{ report.scan.scanner_version }}</dd></div></dl>
                <details><summary>{{ t('Scanner capabilities') }}</summary><pre>{{ JSON.stringify(report.capabilities, null, 2) }}</pre></details>
            </section>
        </section>
    </AppLayout>
</template>
