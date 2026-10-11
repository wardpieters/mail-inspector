<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useDocumentLanguage, useTranslations } from '../Composables/useTranslations';
const { t } = useTranslations();
useDocumentLanguage();
</script>
<template><div class="app-shell"><header class="site-header"><Link href="/" class="brand"><span class="brand-mark">M</span>Mail Inspector</Link><span class="header-note">{{ t('DNS & SMTP diagnostics') }}</span></header><main><slot /></main><footer>{{ t('Evidence from this scanner’s network. Configuration checks do not guarantee delivery.') }}</footer></div></template>
