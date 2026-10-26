<script setup lang="ts">
import UserAvatar from '@/components/app/user-avatar.vue';

const sizes = ['sm', 'default', 'lg'] as const;
const people = [
    { name: 'Anna Berger', email: 'anna@example.test' },
    { name: 'Maurice', email: 'maurice@example.test' },
    { name: 'Hans-Peter Müller von und zu Hohenstein', email: 'hp@example.test' },
    { name: null, email: 'shadow@example.test' },
    { name: null, email: null },
];
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="user-avatar" class="text-xl">UserAvatar</h2>
        <p class="text-fg-muted">Named accounts get a tone derived from the name; shadow accounts (no name yet) are dashed with email initials.</p>
        <div v-for="mode in ['', 'dark']" :key="mode" :class="mode" class="rounded-2xl bg-surface-raised p-5 text-fg">
            <p class="mb-3 text-sm font-semibold text-fg-muted">{{ mode || 'light' }}</p>
            <div v-for="size in sizes" :key="size" class="mb-3 flex flex-wrap items-center gap-3">
                <span class="w-16 text-sm text-fg-muted">{{ size }}</span>
                <UserAvatar v-for="(person, index) in people" :key="index" v-bind="person" :size="size" />
                <UserAvatar v-for="tone in 5" :key="`tone-${tone}`" name="Tone Test" :tone="tone" :size="size" />
            </div>
        </div>
    </section>
</template>
