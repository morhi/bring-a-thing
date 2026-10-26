<script setup lang="ts">
defineProps<{ header?: string; modal?: boolean }>();

const visible = defineModel<boolean>('visible');
</script>

<template>
    <div
        v-if="visible"
        role="dialog"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
        <div class="box-float w-full max-w-lg p-6">
            <div class="mb-4 flex items-center justify-between gap-4">
                <h2 class="text-lg">{{ header }}</h2>
                <button type="button" aria-label="Close" @click="visible = false">
                    &times;
                </button>
            </div>
            <slot />
            <div class="mt-4 flex justify-end gap-2">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
