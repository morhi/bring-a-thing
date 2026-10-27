<script setup lang="ts">
import { Button } from '@/components/ui/button';

const variants = [
    'default',
    'secondary',
    'outline',
    'ghost',
    'destructive',
    'link',
] as const;
const sizes = ['xs', 'sm', 'default', 'lg'] as const;

const colours = [
    'bg-background',
    'bg-card',
    'bg-muted',
    'bg-secondary',
    'bg-primary',
    'bg-primary-soft',
    'bg-accent',
    'bg-success',
    'bg-success-soft',
    'bg-warning',
    'bg-warning-soft',
    'bg-danger',
    'bg-danger-soft',
    'bg-info',
    'bg-info-soft',
    'bg-fg',
    'bg-fg-muted',
    'bg-fg-soft',
    'bg-border',
    'bg-border-strong',
];
</script>

<template>
    <section class="space-y-8">
        <h2 id="button-and-tokens" class="font-display text-2xl">
            Button and tokens
        </h2>

        <div class="space-y-3">
            <h3 class="font-display text-lg">Button variants and sizes</h3>
            <div
                v-for="variant in variants"
                :key="variant"
                class="flex flex-wrap items-center gap-3"
            >
                <span class="text-fg-muted w-24 text-sm">{{ variant }}</span>
                <Button
                    type="button"
                    v-for="size in sizes"
                    :key="size"
                    :variant="variant"
                    :size="size"
                    >{{ size }}</Button
                >
                <Button type="button" :variant="variant" disabled
                    >disabled</Button
                >
            </div>
        </div>

        <div class="space-y-3">
            <h3 class="font-display text-lg">Colour tokens</h3>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                <li v-for="colour in colours" :key="colour" class="space-y-1">
                    <div
                        class="border-border h-10 rounded-lg border"
                        :class="colour"
                    />
                    <span class="text-fg-muted text-xs">{{
                        colour.replace('bg-', '')
                    }}</span>
                </li>
            </ul>
        </div>

        <div class="space-y-3">
            <h3 class="font-display text-lg">Depth tokens</h3>
            <div
                class="space-y-3 rounded-2xl border border-[var(--depth-1-border)] bg-[var(--depth-1-bg)] p-4"
            >
                <p class="text-sm">Depth 1: page containers</p>
                <div
                    class="space-y-3 rounded-xl border border-[var(--depth-2-border)] bg-[var(--depth-2-bg)] p-4"
                >
                    <p class="text-sm">Depth 2: boxes inside containers</p>
                    <div
                        class="rounded-lg border border-[var(--depth-3-border)] bg-[var(--depth-3-bg)] p-4"
                    >
                        <p class="text-sm">Depth 3: boxes inside those</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <h3 class="font-display text-lg">Fonts</h3>
            <p class="font-display text-3xl">Headline: Familjen Grotesk</p>
            <p class="font-tag text-lg">Tag: Gabarito</p>
            <p class="font-sans">
                Body: Instrument Sans. The quick brown fox jumps over the lazy
                dog.
            </p>
        </div>

        <div class="space-y-3">
            <h3 class="font-display text-lg">Content width</h3>
            <div class="content-width">
                <div
                    class="bg-primary-soft text-primary-soft-fg rounded-lg p-3 text-center text-sm"
                >
                    content-width (max 1120px, responsive gutters)
                </div>
            </div>
        </div>
    </section>
</template>
