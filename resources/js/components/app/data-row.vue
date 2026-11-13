<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import type { Component } from 'vue';
import { rowLinkClass } from '@/lib/row-link';

withDefaults(
    defineProps<{
        href: string;
        icon: Component;
        // Fixed count width below the wide grid, so the count starts at the same x in every row of a section
        countClass?: string;
    }>(),
    { countClass: 'w-[5.5rem]' },
);
</script>

<template>
    <!-- Every column renders even when its slot is empty, so the same field sits at the same x in every row -->
    <Link :href="href" :class="[rowLinkClass, '@container block py-2.5']">
        <span
            class="grid min-h-9 grid-cols-[1rem_minmax(0,1fr)_1.25rem] items-center gap-x-3 [grid-template-areas:'icon_title_chev'_'._meta_.'_'._badge_.'] @[60rem]:grid-cols-[1rem_minmax(16rem,1fr)_9rem_6.5rem_9.5rem_11.5rem_1.25rem] @[60rem]:[grid-template-areas:'icon_title_context_date_count_badge_chev']"
        >
            <component
                :is="icon"
                class="text-fg-soft size-4 [grid-area:icon]"
                aria-hidden="true"
            />
            <span
                class="group-hover:text-primary min-w-0 font-semibold break-words [grid-area:title]"
            >
                <slot name="title" />
            </span>
            <!-- Narrow: one meta line under the title on its own grid; wide: the wrapper dissolves into the row columns -->
            <span
                class="text-fg-muted mt-0.5 grid min-w-0 grid-cols-[minmax(4.5rem,1fr)_auto_auto] items-center text-sm [grid-area:meta] @[60rem]:contents"
            >
                <span
                    class="min-w-0 truncate pr-3 @[60rem]:pr-0 @[60rem]:[grid-area:context]"
                >
                    <slot name="context" />
                </span>
                <!-- Always rendered, so an undated row keeps the count in its column -->
                <span
                    :class="[
                        'whitespace-nowrap tabular-nums @[60rem]:pr-0 @[60rem]:[grid-area:date]',
                        $slots.date && 'pr-3',
                    ]"
                >
                    <slot name="date" />
                </span>
                <span
                    :class="[
                        'flex items-center justify-end gap-2 whitespace-nowrap tabular-nums @[60rem]:w-auto @[60rem]:justify-start @[60rem]:[grid-area:count]',
                        countClass,
                    ]"
                >
                    <slot name="count" />
                </span>
            </span>
            <!-- Narrow: badges get their own line, so the title keeps the width; the line collapses without badges -->
            <span
                class="mt-1 flex flex-wrap items-center gap-1.5 [grid-area:badge] @[60rem]:mt-0 @[60rem]:justify-end [&:not(:has(*))]:hidden"
            >
                <slot name="badges" />
            </span>
            <ChevronRight
                class="text-fg-soft size-5 [grid-area:chev]"
                aria-hidden="true"
            />
        </span>
    </Link>
</template>
