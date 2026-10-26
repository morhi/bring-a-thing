<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import 'vue-sonner/style.css';
import FlashToasts from '@/components/FlashToasts.vue';
import AppHeader from '@/components/app/shell/app-header.vue';
import ConnectionNote from '@/components/app/shell/connection-note.vue';
import SiteFooter from '@/components/app/shell/site-footer.vue';
import SkipLink from '@/components/app/shell/skip-link.vue';
import { useConnectionLost } from '@/components/app/shell/use-connection-lost';
import { Toaster } from '@/components/ui/sonner';

const page = usePage();
const { lost } = useConnectionLost();

// The panel behind the bell comes later; until the backend shares notifications.unread the bell stays hidden
const unread = computed(() => {
    const notifications = page.props.notifications as
        | { unread?: unknown }
        | undefined;

    return typeof notifications?.unread === 'number'
        ? notifications.unread
        : null;
});

const notificationsOpen = ref(false);
</script>

<template>
    <div class="flex min-h-dvh flex-col">
        <SkipLink />
        <ConnectionNote v-if="lost" />
        <AppHeader
            variant="app"
            :user="page.props.auth.user"
            :current-url="page.url"
            :unread="unread"
            :notifications-open="notificationsOpen"
            @toggle-notifications="notificationsOpen = !notificationsOpen"
        />
        <main
            id="main"
            tabindex="-1"
            class="content-width flex-1 pt-8 pb-16 outline-none"
        >
            <slot />
        </main>
        <SiteFooter />
        <Toaster position="bottom-center" />
        <FlashToasts />
    </div>
</template>
