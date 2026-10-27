<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    Clock,
    MessageSquare,
    Minus,
    Sparkles,
    Users,
    Vote,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    useTemplateRef,
} from 'vue';
import type { Component } from 'vue';
import EmptyState from '@/components/app/empty-state.vue';
import { formatDate, toIso } from '@/components/app/poll/dates';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

/**
 * The panel behind the bell (F-23): newest notifications first, mark as read, open the target.
 *
 * Behaves like a small dialog: focus moves in on open and stays inside on
 * Tab, Escape closes it and hands focus back to the bell, a click outside
 * closes it without moving focus.
 */
const props = withDefaults(
    defineProps<{
        entries: App.Data.NotificationData[];
        unread: number;
        loading?: boolean;
        loadingMore?: boolean;
        hasMore?: boolean;
        failed?: boolean;
        id?: string;
        // The Dev page shows the panel inline instead of as a popover
        inline?: boolean;
    }>(),
    {
        loading: false,
        loadingMore: false,
        hasMore: false,
        failed: false,
        id: 'notifications-panel',
        inline: false,
    },
);

const emit = defineEmits<{
    close: [returnFocus: boolean];
    open: [entry: App.Data.NotificationData];
    markAllRead: [];
    loadMore: [];
    retry: [];
}>();

const { t, locale } = useI18n();

const root = useTemplateRef<HTMLElement>('root');

const icons: Record<App.Enums.NotificationKind, Component> = {
    invited: Users,
    new_poll: Vote,
    new_event: CalendarDays,
    fixed: Sparkles,
    moved: Clock,
    comment: MessageSquare,
    claimed_for: Check,
    released: Minus,
};

const showSkeleton = computed(
    () => props.loading && props.entries.length === 0,
);

function text(entry: App.Data.NotificationData): string {
    const params = Object.fromEntries(
        Object.entries(entry.params).map(([name, value]) => [
            name,
            value ?? '',
        ]),
    ) as Record<string, string>;

    if (entry.params.date) {
        params.date = formatDate(entry.params.date, locale.value, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }

    return t(entry.key, params);
}

function when(entry: App.Data.NotificationData): string {
    const created = new Date(entry.created_at);
    const day = toIso(created);
    const time = created.toLocaleTimeString(
        locale.value === 'de' ? 'de-CH' : 'en-GB',
        { hour: '2-digit', minute: '2-digit' },
    );
    const today = new Date();
    const yesterday = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate() - 1,
    );

    if (day === toIso(today)) {
        return `${t('common.today')}, ${time}`;
    }

    if (day === toIso(yesterday)) {
        return `${t('notif.yesterday')}, ${time}`;
    }

    return `${formatDate(day, locale.value, { day: 'numeric', month: 'short' })}, ${time}`;
}

function focusables(): HTMLElement[] {
    return Array.from(
        root.value?.querySelectorAll<HTMLElement>(
            'a[href], button:not(:disabled)',
        ) ?? [],
    );
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close', true);

        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const items = focusables();

    if (items.length === 0) {
        event.preventDefault();

        return;
    }

    const first = items[0];
    const last = items[items.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();

        return;
    }

    if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function onPointerDown(event: PointerEvent): void {
    const target = event.target as Element | null;

    // The bell toggles on its own; closing here as well would reopen the panel at once
    if (
        !target ||
        root.value?.contains(target) ||
        target.closest(`[aria-controls="${props.id}"]`)
    ) {
        return;
    }

    emit('close', false);
}

onMounted(() => {
    if (props.inline) {
        return;
    }

    document.addEventListener('pointerdown', onPointerDown);
    nextTick(() => (focusables()[0] ?? root.value)?.focus());
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown);
});

const rowClass =
    'flex w-full items-start gap-3 px-4 py-3 text-left text-fg no-underline hover:bg-surface-muted hover:text-fg focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-focus-ring';
</script>

<template>
    <div
        :id="id"
        ref="root"
        role="dialog"
        :aria-label="t('nav.notifications')"
        tabindex="-1"
        data-feature="F-23"
        :class="
            cn(
                'box-float text-fg flex flex-col overflow-auto rounded-xl outline-none',
                inline
                    ? 'w-full max-w-[400px]'
                    : 'absolute top-[calc(100%+8px)] right-0 z-30 max-h-[min(70vh,560px)] w-[min(400px,calc(100vw-32px))] shadow-lg max-[560px]:right-3 max-[560px]:left-3 max-[560px]:w-auto',
            )
        "
        @keydown="onKeydown"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 pt-3.5 pb-2.5"
        >
            <h2 class="text-[17px]">{{ t('nav.notifications') }}</h2>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="ml-auto"
                :disabled="unread === 0"
                @click="emit('markAllRead')"
            >
                {{ t('notif.markAllRead') }}
            </Button>
        </div>

        <ul
            v-if="showSkeleton"
            class="flex flex-col"
            :aria-label="t('notif.loading')"
            aria-busy="true"
        >
            <li
                v-for="row in 3"
                :key="row"
                class="flex items-start gap-3 px-4 py-3"
            >
                <span
                    class="bg-surface-muted size-9 flex-none animate-pulse rounded-[10px] motion-reduce:animate-none"
                />
                <span class="flex flex-1 flex-col gap-2 pt-1">
                    <span
                        class="bg-surface-muted h-3.5 w-4/5 animate-pulse rounded motion-reduce:animate-none"
                    />
                    <span
                        class="bg-surface-muted h-3 w-1/3 animate-pulse rounded motion-reduce:animate-none"
                    />
                </span>
            </li>
        </ul>

        <ul
            v-else-if="entries.length"
            class="divide-border flex flex-col divide-y"
        >
            <li v-for="entry in entries" :key="entry.id">
                <component
                    :is="entry.url ? Link : 'button'"
                    :href="entry.url ?? undefined"
                    :type="entry.url ? undefined : 'button'"
                    :class="rowClass"
                    @click="emit('open', entry)"
                >
                    <span
                        :class="
                            cn(
                                'grid size-9 flex-none place-items-center rounded-[10px]',
                                entry.read_at === null
                                    ? 'bg-primary-soft text-primary-soft-fg'
                                    : 'bg-surface-muted text-fg-muted',
                            )
                        "
                    >
                        <component
                            :is="icons[entry.kind]"
                            class="size-[18px]"
                            aria-hidden="true"
                        />
                    </span>
                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span
                            :class="
                                cn(
                                    'text-sm leading-snug break-words',
                                    entry.read_at === null && 'font-semibold',
                                )
                            "
                            >{{ text(entry) }}</span
                        >
                        <span class="text-fg-soft text-[13px]">{{
                            when(entry)
                        }}</span>
                    </span>
                    <span
                        v-if="entry.read_at === null"
                        class="bg-primary mt-2 size-2 flex-none rounded-full"
                    >
                        <span class="sr-only">{{ t('notif.unread') }}</span>
                    </span>
                </component>
            </li>
        </ul>

        <div v-else-if="!failed" class="px-4 pt-1 pb-4">
            <EmptyState
                :title="t('notif.empty.title')"
                :text="t('notif.empty.text')"
                class="p-5"
            />
        </div>

        <div
            v-if="failed"
            role="alert"
            class="border-border flex flex-wrap items-center gap-2 border-t px-4 py-3 text-sm"
        >
            <span class="text-fg-muted">{{ t('notif.loadFailed') }}</span>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="emit('retry')"
                >{{ t('notif.retry') }}</Button
            >
        </div>

        <div
            v-if="hasMore && entries.length"
            class="border-border border-t px-4 py-2.5"
        >
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="w-full"
                :disabled="loadingMore"
                @click="emit('loadMore')"
            >
                {{ loadingMore ? t('notif.loading') : t('notif.loadMore') }}
            </Button>
        </div>
    </div>
</template>
