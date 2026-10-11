<script setup lang="ts">
import type { Endpoint } from '../types/report';
import { checkLabels } from '../types/report';
import StatusBadge from './StatusBadge.vue';
defineProps<{endpoints: Endpoint[]}>();
const checks=['connect','answer','helo','tls','secure','cert','from','mtasts','dane','fcrdns','open_relay'];
function outcome(endpoint: Endpoint,key: string): string {return endpoint.findings.find(f=>f.key===key)?.outcome??'pending';}
</script>
<template><div class="scroll-table"><table><caption class="sr-only">Mail endpoint comparison by address and check</caption><thead><tr><th>MX / priority</th><th>Address</th><th v-for="check in checks" :key="check">{{ checkLabels[check] }}</th></tr></thead><tbody><tr v-for="(endpoint,index) in endpoints" :key="endpoint.mx+endpoint.ip"><td><a :href="'#endpoint-'+index">{{ endpoint.mx }}</a><div class="help">Priority {{ endpoint.preference }}</div></td><td><span class="badge">IPv{{ endpoint.family }}</span> <span class="mono">{{ endpoint.ip }}</span></td><td v-for="check in checks" :key="check"><StatusBadge :outcome="outcome(endpoint,check)"/></td></tr></tbody></table></div></template>
