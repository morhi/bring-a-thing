<script setup lang="ts">
import DateBlock from '@/components/app/date-block.vue';

const dates = ['2026-01-01', '2026-03-09', '2026-12-31'];
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="date-block" class="text-xl">DateBlock</h2>
        <p class="text-fg-muted">Decorative (aria-hidden); the full date must appear as text next to it. Fixed marks a confirmed date.</p>
        <div v-for="mode in ['', 'dark']" :key="mode" :class="mode" class="rounded-2xl bg-surface-raised p-5 text-fg">
            <p class="mb-3 text-sm font-semibold text-fg-muted">{{ mode || 'light' }}</p>
            <div class="flex flex-wrap items-start gap-4">
                <template v-for="date in dates" :key="date">
                    <DateBlock :date="date" />
                    <DateBlock :date="date" fixed />
                    <DateBlock :date="date" size="lg" />
                    <DateBlock :date="date" size="lg" fixed />
                </template>
            </div>
        </div>
    </section>
</template>
