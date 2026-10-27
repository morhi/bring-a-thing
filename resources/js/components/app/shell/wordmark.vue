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
        <svg
            aria-hidden="true"
            viewBox="0 0 64 64"
            :class="cn('flex-none self-center', large ? 'size-9' : 'size-7')"
        >
            <rect width="64" height="64" rx="14" class="fill-primary" />
            <path
                class="fill-primary-fg"
                fill-rule="evenodd"
                transform="translate(32 32) scale(1.3) rotate(-12) translate(-32 -32)"
                d="M14 32 24 21H44a4 4 0 0 1 4 4V39a4 4 0 0 1-4 4H24Z M24.7 32a2.8 2.8 0 1 0 5.6 0 2.8 2.8 0 1 0-5.6 0Z"
            />
        </svg>
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
