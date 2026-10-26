<script setup lang="ts">
defineProps<{ value?: number; showValue?: boolean }>();
</script>

<template>
    <div class="h-2 w-full rounded-full bg-progress-track">
        <div
            class="h-full rounded-full bg-primary"
            :style="{ width: `${value ?? 0}%` }"
        />
    </div>
</template>
