<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    label?: string;
    severity?: string;
    variant?: string;
    size?: string;
    loading?: boolean;
    text?: boolean;
    outlined?: boolean;
    icon?: string;
}>();
</script>

<template>
    <Button :disabled="loading">
        <slot>{{ label }}</slot>
    </Button>
</template>
