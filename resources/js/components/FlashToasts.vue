<script setup lang="ts">
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';

const page = usePage();
const toast = useToast();

watch(
    () => page.props.flash.success,
    (message) => {
        if (message) {
            toast.add({ severity: 'success', summary: message, life: 4000 });
        }
    },
    { immediate: true },
);

watch(
    () => page.props.flash.error,
    (message) => {
        if (message) {
            toast.add({ severity: 'error', summary: message, life: 6000 });
        }
    },
    { immediate: true },
);
</script>

<template>
    <!-- renders no markup; only triggers toasts -->
</template>
