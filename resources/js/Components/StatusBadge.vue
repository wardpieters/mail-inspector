<script setup lang="ts">
defineProps<{ outcome: string }>();
const labels: Record<string,string> = {pass:'Pass',warning:'Warning',fail:'Fail',info:'Info',skipped:'Skipped',inconclusive:'Inconclusive',not_applicable:'Not applicable',queued:'Queued',running:'Running',completed:'Completed',partial:'Partial',failed:'Failed',expired:'Expired'};
</script>
<template><span :class="['badge',outcome]">{{ labels[outcome] ?? outcome }}</span></template>
