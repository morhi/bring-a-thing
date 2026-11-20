<script setup lang="ts">
import { Check } from '@lucide/vue';
import EmptyState from '@/components/app/empty-state.vue';

// The one empty state every dashboard section shows when it has no content
defineProps<{
    title: string;
    text?: string;
}>();
</script>

<template>
    <EmptyState :icon="Check" :title="title" :text="text">
        <slot />
    </EmptyState>
</template>
