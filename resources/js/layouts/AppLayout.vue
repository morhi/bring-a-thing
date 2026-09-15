<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Menu from 'primevue/menu';
import Toast from 'primevue/toast';
import Bell from '@primeicons/vue/bell';
import type { Auth } from '@/types';
import { edit as settingsEdit } from '@/actions/App/Http/Controllers/SettingsController';
import { destroy as logout } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';

const page = usePage<{ name: string; auth: Auth }>();

const userMenu = ref();
const userMenuItems = [
    {
        label: 'Account settings',
        command: () => router.visit(settingsEdit().url),
    },
    { label: 'Log out', command: () => router.post(logout().url) },
];

function toggleUserMenu(event: MouseEvent) {
    userMenu.value?.toggle(event);
}

function avatarLabel(name: string | null, email: string): string {
    return (name ?? email).charAt(0).toUpperCase();
}
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
                <template v-if="page.props.auth.user">
                    <Avatar
                        :label="
                            avatarLabel(
                                page.props.auth.user.name,
                                page.props.auth.user.email,
                            )
                        "
                        shape="circle"
                        class="cursor-pointer"
                        @click="toggleUserMenu"
                    />
                    <Menu ref="userMenu" :model="userMenuItems" popup />
                </template>
            </div>
        </header>
        <main class="p-6">
            <slot />
        </main>
    </div>
</template>
