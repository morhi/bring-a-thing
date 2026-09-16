<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Toast from 'primevue/toast';
import FlashToasts from '@/components/FlashToasts.vue';
import type { Auth } from '@/types';

const page = usePage<{ name: string; auth: Auth }>();
</script>

<template>
    <div
        class="bg-surface-0 dark:bg-surface-950 relative isolate flex min-h-screen flex-col overflow-hidden"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 -z-10"
        >
            <div
                class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,var(--p-surface-400)_1px,transparent_0)] [background-size:32px_32px] opacity-[0.15] dark:opacity-[0.08]"
            />
            <div
                class="bg-primary-400/30 dark:bg-primary-500/20 absolute -top-32 -right-32 h-[28rem] w-[28rem] rounded-full blur-3xl"
            />
            <div
                class="bg-primary-300/25 dark:bg-primary-700/20 absolute top-[40rem] -left-40 h-[26rem] w-[26rem] rounded-full blur-3xl"
            />
            <div
                class="bg-surface-300/20 dark:bg-surface-700/10 absolute top-[80rem] right-0 h-[24rem] w-[24rem] rounded-full blur-3xl"
            />
        </div>
        <Toast />
        <FlashToasts />
        <header
            class="border-surface-200 dark:border-surface-800 dark:bg-surface-950/80 sticky top-0 z-10 border-b bg-white/80 backdrop-blur"
        >
            <div
                class="mx-auto flex w-full max-w-7xl items-center justify-between px-6 py-4"
            >
                <Link
                    href="/"
                    class="text-surface-900 dark:text-surface-0 text-lg font-semibold"
                >
                    {{ page.props.name }}
                </Link>
                <div
                    v-if="page.props.auth.user"
                    class="flex items-center gap-3"
                >
                    <Link href="/dashboard">
                        <Button label="Go to dashboard" />
                    </Link>
                </div>
            </div>
        </header>
        <main class="flex-1">
            <slot />
        </main>
        <footer class="border-surface-200 dark:border-surface-800 border-t">
            <div
                class="text-surface-500 dark:text-surface-400 mx-auto flex w-full max-w-7xl flex-col gap-2 px-6 py-8 text-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <span
                    >{{ page.props.name }} &mdash; organize who brings
                    what.</span
                >
                <span>No ads. No tracking. Just the list.</span>
            </div>
        </footer>
    </div>
</template>
