<script setup lang="ts">
type Option = Record<string, unknown>;

const props = defineProps<{
    options?: Option[];
    optionLabel?: string;
    optionValue?: string;
    placeholder?: string;
    size?: string;
    inputId?: string;
}>();

const model = defineModel<unknown>();

function valueOf(option: Option): unknown {
    return props.optionValue ? option[props.optionValue] : option;
}

function labelOf(option: Option): string {
    return String(props.optionLabel ? option[props.optionLabel] : option);
}

function onChange(event: Event) {
    const index = (event.target as HTMLSelectElement).selectedIndex - 1;
    const option = props.options?.[index];
    model.value = option ? valueOf(option) : null;
}
</script>

<template>
    <select
        class="h-9 rounded-lg border border-input bg-control px-2"
        @change="onChange"
    >
        <option value="">{{ placeholder }}</option>
        <option
            v-for="(option, index) in options ?? []"
            :key="index"
            :selected="valueOf(option) === model"
        >
            {{ labelOf(option) }}
        </option>
    </select>
</template>
