<script setup lang="ts">
defineProps<{
    feedback?: boolean;
    invalid?: boolean;
    toggleMask?: boolean;
    inputId?: string;
    placeholder?: string;
}>();

const model = defineModel<string | null>();
</script>

<template>
    <input
        :id="inputId"
        v-model="model"
        type="password"
        :placeholder="placeholder"
        :aria-invalid="invalid || undefined"
        class="h-9 rounded-lg border border-input bg-control px-2"
    />
</template>
