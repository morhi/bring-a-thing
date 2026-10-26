<script setup lang="ts">
defineProps<{ inputId?: string; binary?: boolean }>();

const model = defineModel<boolean>();
</script>

<template>
    <input :id="inputId" v-model="model" type="checkbox" />
</template>
