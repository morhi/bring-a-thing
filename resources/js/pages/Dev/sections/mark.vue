<script setup lang="ts">
import Mark from '@/components/app/mark.vue';
import type { MarkAnswer } from '@/components/app/mark.vue';

const answers: MarkAnswer[] = ['yes', 'maybe', 'no', 'none'];
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 class="text-xl">Mark</h2>
        <p class="text-fg-muted">The answer is carried by icon and shape as well as color; the dashed circle means no answer.</p>
        <div v-for="mode in ['', 'dark']" :key="mode" :class="mode" class="rounded-2xl bg-surface-raised p-5 text-fg">
            <p class="mb-3 text-sm font-semibold text-fg-muted">{{ mode || 'light' }}</p>
            <div class="flex flex-wrap items-center gap-4">
                <span v-for="answer in answers" :key="answer" class="flex items-center gap-2">
                    <Mark :answer="answer" />
                    <span class="text-sm text-fg-muted">{{ answer }}</span>
                </span>
                <span class="flex items-center gap-2">
                    <Mark answer="yes" label="Anna: yes" />
                    <span class="text-sm text-fg-muted">custom label</span>
                </span>
            </div>
        </div>
    </section>
</template>
