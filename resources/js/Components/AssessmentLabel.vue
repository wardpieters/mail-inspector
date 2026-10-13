<script setup lang="ts">
import { computed } from 'vue';
import { useTranslations } from '../Composables/useTranslations';
import StatusIcon from './StatusIcon.vue';
const props = defineProps<{label: string}>();
const { t } = useTranslations();
const kind = computed(() => {
    if (props.label === t('Checks passed')) return 'success';
    if ([t('Attention needed'), t('Improvements recommended')].includes(props.label)) return 'warning';
    return 'info';
});
</script>
<template><span class="assessment-label"><StatusIcon :kind="kind" /><span>{{ label }}</span></span></template>
