<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea';

defineProps<{ invalid?: boolean; autoResize?: boolean }>();

const model = defineModel<string | null>();
</script>

<template>
    <Textarea
        :model-value="model ?? ''"
        :aria-invalid="invalid || undefined"
        @update:model-value="model = String($event)"
    />
</template>
