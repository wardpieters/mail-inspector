<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useTranslations } from '../Composables/useTranslations';

const props = defineProps<{retention_days: number}>();
const page = usePage<{navigation: {home: string; privacy: string}}>();
const { t } = useTranslations();
</script>

<template>
    <AppLayout>
        <Head :title="t('Privacy policy')" />
        <article class="privacy-page">
            <Link :href="page.props.navigation.home" class="back-link">{{ t('← Back to domain diagnostics') }}</Link>
            <h1>{{ t('Privacy policy') }}</h1>
            <p class="lede">{{ t('What Mail Inspector processes when you visit this site or inspect a domain.') }}</p>
            <p class="help"><time datetime="2026-10-15">{{ t('Last updated: 15 October 2026') }}</time></p>

            <section aria-labelledby="privacy-operator">
                <h2 id="privacy-operator">{{ t('Who is responsible') }}</h2>
                <p>{{ t('Mail Inspector is operated by Ward Pieters, the creator of') }} <a href="https://ward.nl">ward.nl</a>. {{ t('For privacy questions or requests, email') }} <a href="mailto:mail@ward.nl">mail@ward.nl</a>.</p>
            </section>

            <section aria-labelledby="privacy-data">
                <h2 id="privacy-data">{{ t('Data used for a scan') }}</h2>
                <p>{{ t('We store the domain and optional DKIM selectors you submit, scan timestamps and status, and the diagnostic results. Results can include DNS records, mail-server hostnames and IP addresses, SMTP responses, and TLS certificate details. Published DNS records and server responses may contain personal data, such as contact email addresses.') }}</p>
                <p>{{ t('Scans query public DNS, connect to mail servers, and may retrieve a domain’s MTA-STS policy over HTTPS. They do not access mailboxes, ask for mail credentials, or read your emails. SMTP checks stop before sending a message body; no test email is delivered.') }}</p>
            </section>

            <section aria-labelledby="privacy-purpose">
                <h2 id="privacy-purpose">{{ t('Why we process this data') }}</h2>
                <p>{{ t('We use this information to run the diagnostics you request, display and share reports, troubleshoot failures, and protect the service from abuse. Where this involves personal data, we rely on our legitimate interests in providing a useful, secure diagnostic service. We do not use scan data for advertising or profiling, or sell it.') }}</p>
            </section>

            <section aria-labelledby="privacy-reports">
                <h2 id="privacy-reports">{{ t('Report access and retention') }}</h2>
                <p>{{ t('Anyone with a report link can view its results and download its JSON export without signing in. Treat report links as shareable, and only submit domains you are comfortable including in a report.') }}</p>
                <p>{{ t('Reports expire :days days after the scan is created and are then no longer available through the site or export. Expired scans and their related results are removed by the scheduled daily cleanup.', {days: props.retention_days}) }}</p>
            </section>

            <section aria-labelledby="privacy-cookies">
                <h2 id="privacy-cookies">{{ t('Cookies and technical data') }}</h2>
                <p>{{ t('The site uses essential session and security cookies to keep forms working and protect against forged requests. Your browser’s language preference selects English or Dutch. The app does not include advertising cookies or analytics trackers.') }}</p>
                <p>{{ t('Your IP address is used to limit scan requests and prevent abuse. Depending on the session and hosting configuration, session storage and operational logs may also contain IP addresses, browser details, request information, and errors. The scan retention period above applies to reports; session, log, and backup retention depends on the hosting configuration. Contact us for details about this deployment.') }}</p>
            </section>

            <section aria-labelledby="privacy-sharing">
                <h2 id="privacy-sharing">{{ t('Infrastructure and external services') }}</h2>
                <p>{{ t('Hosting and infrastructure providers process data needed to run the service. DNS resolvers and the servers being checked receive the relevant DNS queries or network connections and may log them. External links, including GitHub and ward.nl, have their own privacy policies and receive your request when you follow them.') }}</p>
            </section>

            <section aria-labelledby="privacy-rights">
                <h2 id="privacy-rights">{{ t('Your rights and contact') }}</h2>
                <p>{{ t('Where applicable, you can request access to, correction of, or deletion of your personal data, request restriction or portability, and object to processing based on legitimate interests. Email') }} <a href="mailto:mail@ward.nl">mail@ward.nl</a> {{ t('with your request and, if relevant, the report link. Please do not post personal data in a public GitHub issue.') }}</p>
                <p>{{ t('You can also lodge a complaint with your local data protection authority or the Dutch') }} <a href="https://www.autoriteitpersoonsgegevens.nl/">Autoriteit Persoonsgegevens</a>.</p>
            </section>
        </article>
    </AppLayout>
</template>
