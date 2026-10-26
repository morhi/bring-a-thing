<script setup lang="ts">
defineProps<{
    placeholder?: string;
    dateFormat?: string;
    invalid?: boolean;
    updateModelType?: string;
    inputId?: string;
}>();

const model = defineModel<string | Date | null>();

function toInputValue(value: string | Date | null | undefined): string {
    if (!value) {
        return '';
    }

    if (value instanceof Date) {
        return value.toISOString().slice(0, 10);
    }

    return value.slice(0, 10);
}
</script>

<template>
    <input
        type="date"
        :value="toInputValue(model)"
        :placeholder="placeholder"
        :aria-invalid="invalid || undefined"
        class="h-9 rounded-lg border border-input bg-control px-2"
        @input="model = ($event.target as HTMLInputElement).value || null"
    />
</template>
