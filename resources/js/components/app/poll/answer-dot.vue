<script setup lang="ts">
import { X } from '@lucide/vue';
import type { PollAnswer } from '@/components/app/poll/types';
import { cn } from '@/lib/utils';

defineProps<{ answer: PollAnswer | null }>();

const tones: Record<PollAnswer | 'none', string> = {
    yes: 'bg-primary',
    // A half-filled ring reads as "maybe" without colour alone
    maybe: 'border-warning border-2 bg-[linear-gradient(90deg,var(--warning)_50%,transparent_50%)]',
    no: 'text-danger',
    none: 'border-border-strong border-[1.5px]',
};
</script>

<template>
    <!-- Decorative: callers speak the answer in their own label -->
    <span
        aria-hidden="true"
        :class="
            cn(
                'inline-grid size-2.5 flex-none place-items-center rounded-full',
                tones[answer ?? 'none'],
            )
        "
    >
        <X v-if="answer === 'no'" class="size-3" stroke-width="3.5" />
    </span>
</template>
