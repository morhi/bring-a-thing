<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ref, useTemplateRef } from 'vue';
import 'vue-sonner/style.css';
import FlashToasts from '@/components/FlashToasts.vue';
import AppHeader from '@/components/app/shell/app-header.vue';
import ConnectionNote from '@/components/app/shell/connection-note.vue';
import NotificationsPanel from '@/components/app/shell/notifications-panel.vue';
import SiteFooter from '@/components/app/shell/site-footer.vue';
import SkipLink from '@/components/app/shell/skip-link.vue';
import { useConnectionLost } from '@/components/app/shell/use-connection-lost';
import { useNotifications } from '@/components/app/shell/use-notifications';
import { Toaster } from '@/components/ui/sonner';

const page = usePage();
const { lost } = useConnectionLost();
const notifications = useNotifications(lost);

const header = useTemplateRef<InstanceType<typeof AppHeader>>('header');
const notificationsOpen = ref(false);

function toggleNotifications(): void {
    notificationsOpen.value = !notificationsOpen.value;

    if (!notificationsOpen.value) {
        return;
    }

    void notifications.load();
}

function closeNotifications(returnFocus: boolean): void {
    notificationsOpen.value = false;

    if (!returnFocus) {
        return;
    }

    header.value?.focusBell();
}

function openNotification(entry: App.Data.NotificationData): void {
    notificationsOpen.value = false;
    void notifications.markRead(entry);
}
</script>

<template>
    <div class="flex min-h-dvh flex-col">
        <SkipLink />
        <ConnectionNote v-if="lost" />
        <AppHeader
            ref="header"
            variant="app"
            :user="page.props.auth.user"
            :current-url="page.url"
            :unread="page.props.auth.user ? notifications.unread.value : null"
            :notifications-open="notificationsOpen"
            @toggle-notifications="toggleNotifications"
        >
            <template #notifications>
                <NotificationsPanel
                    v-if="notificationsOpen"
                    :entries="notifications.entries.value"
                    :unread="notifications.unread.value"
                    :loading="notifications.loading.value"
                    :loading-more="notifications.loadingMore.value"
                    :has-more="notifications.nextPage.value !== null"
                    :failed="notifications.failed.value"
                    @close="closeNotifications"
                    @open="openNotification"
                    @mark-all-read="notifications.markAllRead"
                    @load-more="notifications.loadMore"
                    @retry="notifications.load"
                />
            </template>
        </AppHeader>
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
