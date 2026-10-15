<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useDocumentLanguage, useTranslations } from '../Composables/useTranslations';
const page = usePage<{navigation: {home: string; privacy: string}}>();
const { t } = useTranslations();
useDocumentLanguage();
</script>
<template>
    <div class="app-shell">
        <header class="site-header">
            <Link :href="page.props.navigation.home" class="brand"><span class="brand-mark">M</span>Mail Inspector</Link>
            <span class="header-note">{{ t('DNS & SMTP diagnostics') }}</span>
        </header>
        <main><slot /></main>
        <footer>
            <p class="footer-note">{{ t('Evidence from this scanner’s network. Configuration checks do not guarantee delivery.') }}</p>
            <div class="footer-meta">
                <nav class="footer-links" :aria-label="t('Footer navigation')">
                    <Link :href="page.props.navigation.privacy">{{ t('Privacy policy') }}</Link>
                    <a href="https://github.com/wardpieters/mail-tester" target="_blank" rel="noopener noreferrer">{{ t('Open source on GitHub') }}</a>
                </nav>
                <p class="creator-credit">{{ t('Created by') }} <a href="https://ward.nl" target="_blank" rel="noopener noreferrer">ward.nl</a></p>
            </div>
        </footer>
    </div>
</template>
