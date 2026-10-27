<script setup lang="ts">
import { computed } from 'vue';
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        // Element or component to render: 'div', 'li', 'a', 'button' or Inertia's Link
        as?: string | Component;
        class?: HTMLAttributes['class'];
    }>(),
    { as: 'div', class: undefined },
);

const isInteractive = computed(() => props.as !== 'div' && props.as !== 'li');
</script>

<template>
    <component
        :is="as"
        :type="as === 'button' ? 'button' : undefined"
        :class="
            cn(
                'box text-fg block min-w-0 px-[18px] py-4 shadow-(--box-shadow)',
                isInteractive &&
                    'hover:text-fg w-full cursor-pointer text-left no-underline transition-[border-color,transform,box-shadow] duration-(--duration-base) ease-out hover:-translate-y-px hover:shadow-md hover:[--box-border:var(--fg-soft)]',
                props.class,
            )
        "
    >
        <slot />
    </component>
</template>
