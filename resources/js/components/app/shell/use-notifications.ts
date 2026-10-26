import { http, usePage } from '@inertiajs/vue3';
import type { Method } from '@inertiajs/core';
import { useEchoNotification } from '@laravel/echo-vue';
import { computed, ref, watch } from 'vue';
import type { Ref } from 'vue';
import {
    index as listNotifications,
    markAllRead as markAllNotificationsRead,
    markRead as markNotificationRead,
    unread as unreadCount,
} from '@/actions/App/Http/Controllers/NotificationController';

async function request<T>(method: Method, url: string): Promise<T> {
    const response = await http
        .getClient()
        .request({ method, url, headers: { Accept: 'application/json' } });

    return JSON.parse(response.data) as T;
}

/**
 * Keep the bell panel of the signed-in account (F-23): unread count, pages of entries and live delivery (F-21).
 *
 * The count starts from the shared prop and follows every page visit; the
 * entries load when the panel opens. New notifications arrive on the
 * account's private channel and are prepended without a reload. After the
 * live connection was lost, the count is fetched again.
 */
export function useNotifications(lost: Ref<boolean>) {
    const page = usePage();
    const userId = computed(() => page.props.auth.user?.id ?? null);

    const sharedUnread = computed(
        () =>
            (
                page.props.notifications as
                    | App.Data.SharedNotificationsData
                    | undefined
            )?.unread,
    );
    const unread = ref<number>(sharedUnread.value ?? 0);
    const entries = ref<App.Data.NotificationData[]>([]);
    const nextPage = ref<number | null>(null);
    const loaded = ref(false);
    const loading = ref(false);
    const loadingMore = ref(false);
    const failed = ref(false);

    watch(sharedUnread, (value) => {
        if (typeof value !== 'number') {
            return;
        }

        unread.value = value;
    });

    function merge(
        list: App.Data.NotificationData[],
        incoming: App.Data.NotificationData[],
    ): App.Data.NotificationData[] {
        const known = new Set(list.map((entry) => entry.id));

        return [...list, ...incoming.filter((entry) => !known.has(entry.id))];
    }

    async function load(): Promise<void> {
        loading.value = true;
        failed.value = false;

        try {
            const list = await request<App.Data.NotificationListData>(
                'get',
                listNotifications.url(),
            );

            entries.value = list.notifications;
            nextPage.value = list.next_page;
            unread.value = list.unread;
            loaded.value = true;
        } catch {
            failed.value = true;
        } finally {
            loading.value = false;
        }
    }

    async function loadMore(): Promise<void> {
        if (nextPage.value === null || loadingMore.value) {
            return;
        }

        loadingMore.value = true;
        failed.value = false;

        try {
            const list = await request<App.Data.NotificationListData>(
                'get',
                listNotifications.url({ query: { page: nextPage.value } }),
            );

            // Live entries shift the pages, so a later page can repeat an entry already shown
            entries.value = merge(entries.value, list.notifications);
            nextPage.value = list.next_page;
            unread.value = list.unread;
        } catch {
            failed.value = true;
        } finally {
            loadingMore.value = false;
        }
    }

    async function markRead(entry: App.Data.NotificationData): Promise<void> {
        if (entry.read_at !== null) {
            return;
        }

        entry.read_at = new Date().toISOString();
        unread.value = Math.max(0, unread.value - 1);

        try {
            unread.value = (
                await request<App.Data.SharedNotificationsData>(
                    'post',
                    markNotificationRead.url(entry.id),
                )
            ).unread;
        } catch {
            entry.read_at = null;
            unread.value += 1;
        }
    }

    async function markAllRead(): Promise<void> {
        const now = new Date().toISOString();
        const before = entries.value.map((entry) => entry.read_at);

        entries.value.forEach((entry) => {
            entry.read_at ??= now;
        });

        try {
            unread.value = (
                await request<App.Data.SharedNotificationsData>(
                    'post',
                    markAllNotificationsRead.url(),
                )
            ).unread;
        } catch {
            entries.value.forEach((entry, index) => {
                entry.read_at = before[index] ?? null;
            });
            failed.value = true;
        }
    }

    async function refreshUnread(): Promise<void> {
        try {
            unread.value = (
                await request<App.Data.SharedNotificationsData>(
                    'get',
                    unreadCount.url(),
                )
            ).unread;
        } catch {
            // The next page visit shares the count again
        }
    }

    if (userId.value !== null) {
        useEchoNotification<App.Data.NotificationData>(
            `App.Models.User.${userId.value}`,
            (payload) => {
                const { type: _type, ...entry } = payload;

                if (entries.value.some((known) => known.id === entry.id)) {
                    return;
                }

                entries.value = [
                    entry as App.Data.NotificationData,
                    ...entries.value,
                ];
                unread.value += 1;
            },
            'notification.created',
        );
    }

    watch(lost, (isLost, wasLost) => {
        if (isLost || !wasLost) {
            return;
        }

        void refreshUnread();

        if (loaded.value) {
            void load();
        }
    });

    return {
        unread,
        entries,
        nextPage,
        loaded,
        loading,
        loadingMore,
        failed,
        load,
        loadMore,
        markRead,
        markAllRead,
    };
}
