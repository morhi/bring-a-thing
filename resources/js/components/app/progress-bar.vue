<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    done: number;
    total: number;
    // Accessible name, for example "Claimed items"
    label: string;
}>();

const percent = computed(() =>
    Math.min(100, Math.round((100 * props.done) / Math.max(1, props.total))),
);
</script>

<template>
    <span
        role="progressbar"
        :aria-label="label"
        aria-valuemin="0"
        :aria-valuemax="Math.max(1, total)"
        :aria-valuenow="Math.min(done, Math.max(1, total))"
        :aria-valuetext="`${done} / ${total}`"
        class="bg-progress-track block h-1.5 min-w-16 flex-1 overflow-hidden rounded-[3px]"
    >
        <span
            class="bg-primary block h-full rounded-[3px] transition-[width] duration-200"
            :style="{ width: `${percent}%` }"
        />
    </span>
</template>
