<script setup lang="ts">
import AppHeader from '@/components/app/shell/app-header.vue';
import ConnectionNote from '@/components/app/shell/connection-note.vue';
import SiteFooter from '@/components/app/shell/site-footer.vue';
import Wordmark from '@/components/app/shell/wordmark.vue';
import { cn } from '@/lib/utils';
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

// The heading utilities must survive a merge with a text colour, or pages lose their heading scale
const mergeChecks = [
    { input: ['text-h1', 'text-fg'], expected: 'text-h1 text-fg' },
    { input: ['text-h2', 'text-fg-muted'], expected: 'text-h2 text-fg-muted' },
    { input: ['text-h1', 'text-xl'], expected: 'text-xl' },
].map((check) => {
    const output = cn(...check.input);

    return { ...check, output, passed: output === check.expected };
});
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="app-shell" class="text-xl">App shell</h2>
        <p class="text-fg-muted">
            Header states of both layouts. Signed in, the header holds only the
            logo, notifications and the account menu (settings, friends,
            language, theme, log out). Guests get language and theme inline, or
            in the menu button below 1024 px. The footer carries the language
            switch. The skip link appears on the first Tab of a page; each
            layout has exactly one main landmark, so the examples here leave it
            out.
        </p>
        <ul class="flex flex-col gap-1 text-sm" data-test="cn-merge-checks">
            <li v-for="check in mergeChecks" :key="check.input.join(' ')">
                <code>cn('{{ check.input.join("', '") }}')</code>
                gives <code>{{ check.output }}</code
                >:
                <span :class="check.passed ? 'text-success' : 'text-danger'">{{
                    check.passed ? 'pass' : 'FAIL'
                }}</span>
            </li>
        </ul>
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
