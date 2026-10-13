<script setup lang="ts">
withDefaults(defineProps<{kind?: 'success' | 'warning' | 'failure' | 'info'}>(), {kind: 'info'});
</script>
<template>
    <svg :class="['status-icon', 'status-icon--' + kind]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
        <path v-if="kind === 'success'" d="m5 12 4 4L19 6" />
        <template v-else-if="kind === 'warning'"><path d="M12 3 22 21H2Z" /><path d="M12 9v5" /><circle cx="12" cy="17.5" r=".7" fill="currentColor" stroke="none" /></template>
        <path v-else-if="kind === 'failure'" d="m6 6 12 12M18 6 6 18" />
        <template v-else><circle cx="12" cy="12" r="9" /><path d="M12 11v5" /><circle cx="12" cy="7.5" r=".8" fill="currentColor" stroke="none" /></template>
    </svg>
</template>
