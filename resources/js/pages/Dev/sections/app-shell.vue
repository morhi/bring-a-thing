<script setup lang="ts">
import AppHeader from '@/components/app/shell/app-header.vue';
import ConnectionNote from '@/components/app/shell/connection-note.vue';
import SiteFooter from '@/components/app/shell/site-footer.vue';
import Wordmark from '@/components/app/shell/wordmark.vue';
import { dashboard } from '@/routes';

const member = { name: 'Anna Keller', email: 'anna@example.com' };
const shadowMember = { name: null, email: 'guest@example.com' };

const states = [
    {
        label: 'AppLayout: unread notifications',
        variant: 'app',
        user: member,
        unread: 3,
        offline: false,
    },
    {
        label: 'AppLayout: no unread, connection lost',
        variant: 'app',
        user: member,
        unread: 0,
        offline: true,
    },
    {
        label: 'AppLayout: notifications not shared yet (no bell), shadow account',
        variant: 'app',
        user: shadowMember,
        unread: null,
        offline: false,
    },
    {
        label: 'WebsiteLayout: guest',
        variant: 'website',
        user: null,
        unread: null,
        offline: false,
    },
    {
        label: 'WebsiteLayout: signed in (shared list, join page)',
        variant: 'website',
        user: member,
        unread: null,
        offline: false,
    },
] as const;
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="app-shell" class="text-xl">App shell</h2>
        <p class="text-fg-muted">
            Header states of both layouts. Below 1024 px the nav, language and
            theme move into the menu button. The skip link appears on the first
            Tab of a page; each layout has exactly one main landmark, so the
            examples here leave it out.
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
            <div class="flex items-center gap-6">
                <Wordmark />
                <Wordmark large />
            </div>
            <div
                v-for="state in states"
                :key="state.label"
                class="flex flex-col gap-2"
            >
                <p class="text-fg-muted text-sm">{{ state.label }}</p>
                <div class="border-border overflow-hidden rounded-xl border">
                    <ConnectionNote v-if="state.offline" />
                    <AppHeader
                        :variant="state.variant"
                        :user="state.user"
                        :current-url="dashboard().url"
                        :unread="state.unread"
                    />
                    <div class="content-width text-fg-soft py-6 text-sm">
                        Page content
                    </div>
                    <SiteFooter />
                </div>
            </div>
        </div>
    </section>
</template>
