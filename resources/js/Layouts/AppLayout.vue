<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useDocumentLanguage, useTranslations } from '../Composables/useTranslations';
const page = usePage<{navigation: {home: string; headers: string; privacy: string}}>();
const { t } = useTranslations();
useDocumentLanguage();
const isHeaders = computed(() => page.component?.startsWith('Headers/'));
const isDomain = computed(() => page.component === 'Home' || page.component?.startsWith('Scans/'));
</script>
<template>
    <div class="app-shell">
        <header class="site-header">
            <Link :href="page.props.navigation.home" class="brand"><span class="brand-mark">M</span>Mail Inspector</Link>
            <nav class="tool-navigation" :aria-label="t('Diagnostic tools')">
                <Link :href="page.props.navigation.home" :aria-current="isDomain ? 'page' : undefined">{{ t('Domain diagnostics') }}</Link>
                <Link :href="page.props.navigation.headers" :aria-current="isHeaders ? 'page' : undefined">{{ t('Message headers') }}</Link>
            </nav>
        </header>
        <main><slot /></main>
        <footer>
            <p v-if="isHeaders" class="footer-note">{{ t('Authentication and filtering results are reported by the pasted headers; they have not been independently verified.') }}</p>
            <p v-else-if="isDomain" class="footer-note">{{ t('Evidence from this scanner’s network. Configuration checks do not guarantee delivery.') }}</p>
            <div class="footer-meta">
                <nav class="footer-links" :aria-label="t('Footer navigation')">
                    <Link :href="page.props.navigation.privacy">{{ t('Privacy policy') }}</Link>
                    <a href="https://github.com/wardpieters/mail-inspector" target="_blank" rel="noopener noreferrer">{{ t('Open source on GitHub') }}</a>
                </nav>
                <p class="creator-credit">{{ t('Created by') }} <a href="https://ward.nl" target="_blank" rel="noopener noreferrer">ward.nl</a></p>
            </div>
        </footer>
    </div>
</template>
