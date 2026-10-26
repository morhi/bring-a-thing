<script setup lang="ts">
import { CalendarDays, Users } from '@lucide/vue';
import EmptyState from '@/components/app/empty-state.vue';
import { Button } from '@/components/ui/button';
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="empty-state" class="text-xl">EmptyState</h2>
        <div v-for="mode in ['', 'dark']" :key="mode" :class="mode" class="flex flex-col gap-4 rounded-2xl bg-surface-raised p-5 text-fg">
            <p class="text-sm font-semibold text-fg-muted">{{ mode || 'light' }}</p>
            <EmptyState title="Title only" />
            <EmptyState title="No polls yet" text="Propose a few dates and let the group answer." />
            <EmptyState :icon="CalendarDays" title="With icon and action" text="An event is a fixed date. Create one directly or fix it from a poll.">
                <Button>Create event</Button>
                <Button variant="outline">Secondary action</Button>
            </EmptyState>
            <EmptyState
                :icon="Users"
                title="A very long title that keeps going to check how the heading wraps on a narrow screen without breaking the layout"
                text="Long text: a group is the circle of people you plan with. Invite them once and reuse the group for every poll, event and list, so nobody has to be asked twice."
            />
        </div>
    </section>
</template>
