<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useTranslations } from '../Composables/useTranslations';
const props = defineProps<{submit_url: string; retention_days: number}>();
const { t } = useTranslations();
const form = useForm({domain: '', selectors: ''});
function submit() { form.post(props.submit_url); }
const checks = ['DNSSEC', 'CAA', 'Answer', 'Connect', 'HELO', 'TLS', 'Secure', 'Cert', 'From', 'MTA-STS', 'DANE', 'FCRDNS', 'Open relay', 'SPF', 'DKIM', 'DMARC'];
</script>
<template>
    <AppLayout>
        <Head :title="t('Domain diagnostics')" />
        <section class="hero">
            <p class="eyebrow">{{ t('UNDERSTAND YOUR MAIL INFRASTRUCTURE') }}</p>
            <h1>{{ t('Every route.') }}<br><span>{{ t('Every address.') }}</span></h1>
            <p class="lede">{{ t('Inspect your domain’s incoming mail setup. Follow every MX record, test IPv4 and IPv6, and see where security needs attention.') }}</p>
            <form class="panel scan-form" @submit.prevent="submit">
                <label for="domain">{{ t('Domain to inspect') }}</label>
                <div class="form-row">
                    <input id="domain" v-model="form.domain" name="domain" type="text" inputmode="url" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="example.com" required :aria-invalid="!!form.errors.domain" aria-describedby="domain-help domain-error" />
                    <button class="primary" :disabled="form.processing" type="submit">{{ t(form.processing ? 'Starting…' : 'Inspect domain →') }}</button>
                </div>
                <p id="domain-help" class="help">{{ t('Enter a domain, without an email address or URL.') }}</p>
                <p v-if="form.errors.domain" id="domain-error" class="error" role="alert">{{ form.errors.domain }}</p>
                <details class="selector-options">
                    <summary>{{ t('Optional DKIM selectors') }}</summary>
                    <div class="selector-fields">
                        <label for="selectors">{{ t('DKIM selectors') }}</label>
                        <input id="selectors" v-model="form.selectors" placeholder="selector1, selector2" autocomplete="off" spellcheck="false" :aria-invalid="!!form.errors.selectors" aria-describedby="selector-help" />
                        <p id="selector-help" class="help">{{ t('Up to 10, separated by commas or spaces. Find these in your sending provider’s DKIM settings. Without selectors, DKIM is marked skipped.') }}</p>
                        <p v-if="form.errors.selectors" class="error" role="alert">{{ form.errors.selectors }}</p>
                    </div>
                </details>
                <p class="help scan-note">{{ t('Usually finishes within a few minutes. Anyone with the report link can view it for :days days.', {days: retention_days}) }}</p>
            </form>
            <div class="features">
                <div><h3>{{ t('Every mail route') }}</h3><p>{{ t('Primary and backup MX hosts, with a separate result for every IPv4 and IPv6 address.') }}</p></div>
                <div><h3>{{ t('Evidence you can inspect') }}</h3><p>{{ t('DNS records, SMTP responses, certificates, and clear recommendations in one report.') }}</p></div>
                <div><h3>{{ t('No test email') }}</h3><p>{{ t('Bounded SMTP dialogues stop before a message body. Relay acceptance is reported as suspected.') }}</p></div>
            </div>
            <div class="tags check-tags"><span v-for="check in checks" :key="check" class="badge">{{ t(check) }}</span></div>
        </section>
    </AppLayout>
</template>
