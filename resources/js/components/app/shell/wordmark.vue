<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

withDefaults(
    defineProps<{
        href?: string | null;
        large?: boolean;
    }>(),
    { href: null, large: false },
);
</script>

<template>
    <component
        :is="href ? Link : 'span'"
        :href="href ?? undefined"
        aria-label="Bring a Thing"
        :class="
            cn(
                'font-display text-fg hover:text-fg inline-flex items-baseline gap-[7px] rounded-md py-1 font-bold tracking-[-0.02em] whitespace-nowrap no-underline',
                large ? 'text-[28px]' : 'text-[21px]',
            )
        "
    >
        <span aria-hidden="true">Bring a</span>
        <span
            aria-hidden="true"
            :class="
                cn(
                    'bg-primary font-tag text-primary-fg inline-block rotate-(--tag-tilt) rounded-lg leading-[1.2] shadow-sm',
                    large
                        ? 'px-[11px] pt-0.5 pb-[3px] text-[23px]'
                        : 'px-[9px] pt-px pb-0.5 text-[17px]',
                )
            "
            >Thing</span
        >
    </component>
</template>
