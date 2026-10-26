<script setup lang="ts">
defineProps<{
    min?: number;
    max?: number;
    maxFractionDigits?: number;
    suffix?: string;
    placeholder?: string;
    invalid?: boolean;
    inputId?: string;
}>();

const model = defineModel<number | null>({ default: null });

function parse(raw: string): number | null {
    if (raw === '') {
        return null;
    }

    return Number(raw);
}
</script>

<template>
    <input
        type="number"
        :value="model ?? ''"
        :min="min"
        :max="max"
        :placeholder="placeholder"
        :aria-invalid="invalid || undefined"
        class="h-9 rounded-lg border border-input bg-control px-2"
        @input="model = parse(($event.target as HTMLInputElement).value)"
    />
</template>
