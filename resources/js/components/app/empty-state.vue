<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    text?: string;
    icon?: Component;
}>();
</script>

<template>
    <div
        class="flex flex-col items-start gap-2.5 rounded-2xl border-2 border-dashed border-border-strong p-7 bg-[repeating-linear-gradient(135deg,var(--texture-hatch-color)_0_1px,transparent_1px_12px)]"
    >
        <component :is="icon" v-if="icon" class="size-7 text-primary" aria-hidden="true" />
        <p class="font-display text-lg font-[640]">{{ title }}</p>
        <p v-if="text" class="max-w-[48ch] text-fg-muted">{{ text }}</p>
        <div v-if="$slots.default" class="mt-1.5 flex flex-wrap gap-2.5"><slot /></div>
    </div>
</template>
