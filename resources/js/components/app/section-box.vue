<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    title: string;
    // Heading id; the section is labelled by it
    id: string;
    count?: number | null;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <section
        :aria-labelledby="id"
        :class="
            cn(
                'box flex min-w-0 flex-col gap-[18px] rounded-3xl p-3.5 shadow-(--box-shadow) sm:px-[26px] sm:pt-6 sm:pb-[26px]',
                props.class,
            )
        "
    >
        <div
            class="border-border flex flex-wrap items-baseline justify-between gap-4 border-b pb-3.5"
        >
            <h2 :id="id" class="text-h2">
                {{ title
                }}<span
                    v-if="count"
                    class="text-fg-soft ml-1.5 text-[15px] font-medium tabular-nums"
                    >{{ count }}</span
                >
            </h2>
            <div v-if="$slots.action" class="flex flex-wrap gap-2.5">
                <slot name="action" />
            </div>
        </div>
        <slot />
    </section>
</template>
