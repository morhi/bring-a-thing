<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{ value?: string | number; severity?: string; rounded?: boolean }>();
</script>

<template>
    <Badge variant="secondary">
        <slot>{{ value }}</slot>
    </Badge>
</template>
