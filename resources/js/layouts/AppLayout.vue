<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Toast from 'primevue/toast';
import Bell from '@primeicons/vue/bell';
import type { Auth } from '@/types';

const page = usePage<{ name: string; auth: Auth }>();
</script>

<template>
    <div class="bg-surface-50 dark:bg-surface-950 min-h-screen">
        <Toast />
        <header
            class="border-surface-200 dark:border-surface-800 dark:bg-surface-900 flex items-center justify-between border-b bg-white px-6 py-3"
        >
            <Link
                href="/"
                class="text-surface-900 dark:text-surface-0 text-lg font-semibold"
            >
                {{ page.props.name }}
            </Link>
            <div class="flex items-center gap-4">
                <Button
                    aria-label="Notifications"
                    severity="secondary"
                    text
                    rounded
                >
                    <Bell />
                </Button>
                <Avatar
                    v-if="page.props.auth.user"
                    :label="page.props.auth.user.name.charAt(0).toUpperCase()"
                    shape="circle"
                />
            </div>
        </header>
        <main class="p-6">
            <slot />
        </main>
    </div>
</template>
