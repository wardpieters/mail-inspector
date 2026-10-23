<script setup lang="ts">
import type { HeaderAnalysis } from '../../types/headers';
import { useTranslations } from '../../Composables/useTranslations';
defineProps<{ analysis: HeaderAnalysis; fieldIds: number[] }>();
const { t } = useTranslations();
</script>
<template>
    <details class="header-evidence">
        <summary>{{ t('Header evidence') }}</summary>
        <div v-for="id in fieldIds" :key="id">
            <p class="help">{{ t('Source lines :start–:end', { start: analysis.document.fields[id].startLine, end: analysis.document.fields[id].endLine }) }}</p>
            <pre>{{ analysis.document.fields[id].raw }}</pre>
        </div>
    </details>
</template>
