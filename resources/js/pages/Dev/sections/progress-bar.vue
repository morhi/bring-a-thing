<script setup lang="ts">
import ProgressBar from '@/components/app/progress-bar.vue';

const cases = [
    { done: 0, total: 5 },
    { done: 1, total: 5 },
    { done: 3, total: 5 },
    { done: 5, total: 5 },
    { done: 0, total: 0 },
    { done: 7, total: 5 },
];
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="progress-bar" class="text-xl">ProgressBar</h2>
        <p class="text-fg-muted">
            role=progressbar with a visible track in both modes; it fills the
            width its parent gives it.
        </p>
        <div
            v-for="mode in ['', 'dark']"
            :key="mode"
            :class="mode"
            class="bg-surface-raised text-fg rounded-2xl p-5"
        >
            <p class="text-fg-muted mb-3 text-sm font-semibold">
                {{ mode || 'light' }}
            </p>
            <div
                v-for="item in cases"
                :key="`${item.done}-${item.total}`"
                class="mb-3 flex items-center gap-3"
            >
                <span class="text-fg-muted w-20 text-sm whitespace-nowrap"
                    >{{ item.done }} / {{ item.total }}</span
                >
                <ProgressBar v-bind="item" label="Claimed items" />
            </div>
            <div class="flex items-center gap-3">
                <span class="text-fg-muted text-sm">Narrow (64px)</span>
                <div class="flex w-16">
                    <ProgressBar
                        :done="2"
                        :total="3"
                        label="Answered options"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
