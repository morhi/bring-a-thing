<script setup lang="ts">
import Toast from 'primevue/toast';
import FlashToasts from '@/components/FlashToasts.vue';
</script>

<template>
    <div
        class="bg-surface-50 dark:bg-surface-950 flex min-h-screen items-center justify-center p-6"
    >
        <Toast />
        <FlashToasts />
        <main
            class="dark:bg-surface-900 w-full max-w-md rounded-lg bg-white p-8 shadow-sm"
        >
            <slot />
        </main>
    </div>
</template>
