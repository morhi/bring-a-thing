<script setup lang="ts">
import Mark from '@/components/app/mark.vue';
import type { MarkAnswer } from '@/components/app/mark.vue';

const answers: MarkAnswer[] = ['yes', 'maybe', 'no', 'none'];
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="mark" class="text-xl">Mark</h2>
        <p class="text-fg-muted">
            The answer is carried by icon and shape as well as color; the dashed
            circle means no answer.
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
            <div class="flex flex-wrap items-center gap-4">
                <span
                    v-for="answer in answers"
                    :key="answer"
                    class="flex items-center gap-2"
                >
                    <Mark :answer="answer" />
                    <span class="text-fg-muted text-sm">{{ answer }}</span>
                </span>
                <span class="flex items-center gap-2">
                    <Mark answer="yes" label="Anna: yes" />
                    <span class="text-fg-muted text-sm">custom label</span>
                </span>
            </div>
        </div>
    </section>
</template>
