<script setup lang="ts">
import { Input } from '@/components/ui/input';

defineProps<{ invalid?: boolean; size?: string; fluid?: boolean }>();

const model = defineModel<string | number | null>();
</script>

<template>
    <Input
        :model-value="model ?? ''"
        :aria-invalid="invalid || undefined"
        @update:model-value="model = $event"
    />
</template>
