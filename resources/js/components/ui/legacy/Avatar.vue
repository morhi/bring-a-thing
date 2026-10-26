<script setup lang="ts">
defineProps<{ label?: string; image?: string; shape?: string; size?: string }>();
</script>

<template>
    <span
        class="inline-flex size-8 items-center justify-center rounded-full bg-muted text-sm font-medium"
    >
        <img v-if="image" :src="image" alt="" class="size-full rounded-full" />
        <template v-else>{{ label }}</template>
    </span>
</template>
