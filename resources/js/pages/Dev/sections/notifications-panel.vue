<script setup lang="ts">
import NotificationsPanel from '@/components/app/shell/notifications-panel.vue';
import { toIso } from '@/components/app/poll/dates';

const now = new Date();
const earlier = (hours: number): string =>
    new Date(now.getTime() - hours * 3_600_000).toISOString();
const params = {
    name: null,
    title: null,
    group: null,
    list: null,
    item: null,
    date: null,
};

const entries: App.Data.NotificationData[] = [
    {
        id: 'a',
        kind: 'fixed',
        key: 'notif.fixed',
        params: {
            ...params,
            title: 'Summer concert',
            date: toIso(new Date(now.getTime() + 20 * 86_400_000)),
        },
        text: '',
        url: '#',
        read_at: null,
        created_at: earlier(1),
    },
    {
        id: 'b',
        kind: 'claimed_for',
        key: 'notif.claimedFor',
        params: { ...params, name: 'Anna Keller', item: 'Cables' },
        text: '',
        url: '#',
        read_at: null,
        created_at: earlier(26),
    },
    {
        id: 'c',
        kind: 'invited',
        key: 'notif.invited',
        params: {
            ...params,
            name: 'Ben Roth',
            group: 'Brass band with a very long name that has to wrap on narrow screens',
        },
        text: '',
        url: '#',
        read_at: earlier(2),
        created_at: earlier(80),
    },
    {
        id: 'd',
        kind: 'comment',
        key: 'notif.comment',
        params: { ...params, name: 'guest@example.com', list: 'Snacks' },
        text: '',
        url: null,
        read_at: earlier(2),
        created_at: earlier(200),
    },
];

const states = [
    {
        label: 'Entries, unread and more pages',
        entries,
        unread: 2,
        loading: false,
        hasMore: true,
        failed: false,
    },
    {
        label: 'Loading (skeleton)',
        entries: [],
        unread: 0,
        loading: true,
        hasMore: false,
        failed: false,
    },
    {
        label: 'Empty, all read',
        entries: [],
        unread: 0,
        loading: false,
        hasMore: false,
        failed: false,
    },
    {
        label: 'Load failed',
        entries: entries.slice(2),
        unread: 0,
        loading: false,
        hasMore: false,
        failed: true,
    },
];
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="notifications-panel" class="text-xl">NotificationsPanel</h2>
        <p class="text-fg-muted">
            The panel behind the bell, shown inline here. In the header it
            floats below the bell, traps focus, closes on Escape (focus returns
            to the bell) and on a click outside. Below 560 px it spans the
            header width. An entry without a target (deleted subject) is a
            button that only marks it as read.
        </p>
        <div
            v-for="mode in ['', 'dark']"
            :key="mode"
            :class="mode"
            class="bg-ground text-fg flex flex-col gap-5 rounded-2xl p-5"
        >
            <p class="text-fg-muted text-sm font-semibold">
                {{ mode || 'light' }}
            </p>
            <div class="grid gap-5 lg:grid-cols-2">
                <div
                    v-for="(state, index) in states"
                    :key="state.label"
                    class="flex flex-col gap-2"
                >
                    <p class="text-fg-muted text-sm">{{ state.label }}</p>
                    <NotificationsPanel
                        :id="`dev-notifications-${mode || 'light'}-${index}`"
                        inline
                        :entries="state.entries"
                        :unread="state.unread"
                        :loading="state.loading"
                        :has-more="state.hasMore"
                        :failed="state.failed"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
