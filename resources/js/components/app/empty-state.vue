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
        class="border-border-strong flex flex-col items-start gap-2.5 rounded-2xl border-2 border-dashed bg-[repeating-linear-gradient(135deg,var(--texture-hatch-color)_0_1px,transparent_1px_12px)] p-7"
    >
        <component
            :is="icon"
            v-if="icon"
            class="text-primary size-7"
            aria-hidden="true"
        />
        <p class="font-display text-lg font-[640]">{{ title }}</p>
        <p v-if="text" class="text-fg-muted max-w-[48ch]">{{ text }}</p>
        <div v-if="$slots.default" class="mt-1.5 flex flex-wrap gap-2.5">
            <slot />
        </div>
    </div>
</template>
