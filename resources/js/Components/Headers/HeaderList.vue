<script setup lang="ts">
import { computed, ref } from 'vue';
import type { HeaderAnalysis } from '../../types/headers';
import { useTranslations } from '../../Composables/useTranslations';
const props = defineProps<{ analysis: HeaderAnalysis }>();
const { t } = useTranslations();
const query = ref('');
const fields = computed(() => {
    const search = query.value.toLowerCase().trim();
    return props.analysis.document.fields.filter(f => !search || f.name.toLowerCase().includes(search) || f.value.toLowerCase().includes(search) || f.displayValue.toLowerCase().includes(search));
});
</script>
<template>
    <section class="panel header-result-panel" aria-labelledby="all-headers-title">
        <h2 id="all-headers-title">{{ t('All headers') }}</h2>
        <label for="header-search">{{ t('Search header names or values') }}</label>
        <input id="header-search" v-model="query" type="search" autocomplete="off" spellcheck="false" :placeholder="t('Search headers…')" />
        <p class="help" role="status">{{ t(':count of :total fields', { count: fields.length, total: analysis.document.fields.length }) }}</p>
        <ul class="header-field-list"><li v-for="field in fields" :key="field.id" data-header-field><h3 class="mono">{{ field.name }}</h3><p class="mono">{{ field.value }}</p></li></ul>
        <p v-if="!fields.length" class="muted">{{ t('No matching headers.') }}</p>
        <details><summary>{{ t('Original header block') }}</summary><pre>{{ analysis.document.rawHeaders }}</pre></details>
        <details v-if="analysis.document.malformedLines.length"><summary>{{ t('Unparsed lines') }}</summary><pre v-for="line in analysis.document.malformedLines" :key="line.line">{{ line.line }}: {{ line.raw }}</pre></details>
    </section>
</template>
