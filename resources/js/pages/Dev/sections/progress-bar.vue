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
        <h2 class="text-xl">ProgressBar</h2>
        <p class="text-fg-muted">role=progressbar with a visible track in both modes; it fills the width its parent gives it.</p>
        <div v-for="mode in ['', 'dark']" :key="mode" :class="mode" class="rounded-2xl bg-surface-raised p-5 text-fg">
            <p class="mb-3 text-sm font-semibold text-fg-muted">{{ mode || 'light' }}</p>
            <div v-for="item in cases" :key="`${item.done}-${item.total}`" class="mb-3 flex items-center gap-3">
                <span class="w-20 text-sm whitespace-nowrap text-fg-muted">{{ item.done }} / {{ item.total }}</span>
                <ProgressBar v-bind="item" label="Claimed items" />
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-fg-muted">Narrow (64px)</span>
                <div class="flex w-16"><ProgressBar :done="2" :total="3" label="Answered options" /></div>
            </div>
        </div>
    </section>
</template>
