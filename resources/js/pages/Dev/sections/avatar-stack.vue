<script setup lang="ts">
import AvatarStack from '@/components/app/avatar-stack.vue';

const everyone = [
    { id: 1, name: 'Anna Berger' },
    { id: 2, name: 'Ben Carter' },
    { id: 3, name: 'Chiara Rossi' },
    { id: 4, name: 'Dmitri Ivanov' },
    { id: 5, name: null, email: 'shadow@example.test' },
    { id: 6, name: 'Eva Lang' },
    { id: 7, name: 'Felix Weber' },
];

const cases = [
    { label: 'Zero', people: [], max: 4 },
    { label: 'One', people: everyone.slice(0, 1), max: 4 },
    { label: 'Exactly max (4 of 4)', people: everyone.slice(0, 4), max: 4 },
    { label: 'Many (7, max 4, +3)', people: everyone, max: 4 },
    { label: 'Many (7, max 2, +5)', people: everyone, max: 2 },
];
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="avatar-stack" class="text-xl">AvatarStack</h2>
        <div
            v-for="mode in ['', 'dark']"
            :key="mode"
            :class="mode"
            class="bg-surface-raised text-fg rounded-2xl p-5"
        >
            <p class="text-fg-muted mb-3 text-sm font-semibold">
                {{ mode || 'light' }}
            </p>
            <div
                v-for="item in cases"
                :key="item.label"
                class="mb-3 flex flex-wrap items-center gap-3"
            >
                <span class="text-fg-muted w-48 text-sm">{{ item.label }}</span>
                <AvatarStack :people="item.people" :max="item.max" />
            </div>
        </div>
    </section>
</template>
