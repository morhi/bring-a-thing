<script setup lang="ts">
import {
    CalendarCheckIcon,
    CalendarIcon,
    HeartIcon,
    LayoutDashboardIcon,
    ListChecksIcon,
    PlusIcon,
    SearchIcon,
    SettingsIcon,
    UsersIcon,
} from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import {
    ListboxContent,
    ListboxFilter,
    ListboxGroup,
    ListboxGroupLabel,
    ListboxItem,
    ListboxRoot,
} from 'reka-ui';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/use-i18n';
import { dashboard } from '@/routes';
import { index as friendsIndex } from '@/routes/friends';
import { create as groupsCreate } from '@/routes/groups';
import { create as rostersCreate } from '@/routes/rosters';
import { edit as settingsEdit } from '@/routes/settings';
import { useSearch } from './use-search';

type Entry = {
    id: string;
    title: string;
    context: string | null;
    url: string;
    icon: Component;
};

const { t } = useI18n();
const { query, term, active, sections, loading, failed, reset } = useSearch();

const open = ref(false);
// ListboxRoot is generic, so InstanceType cannot describe it; only the exposed method is needed
const listbox = useTemplateRef<{ highlightFirstItem: () => void }>('listbox');

const isMac =
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad|iPod/.test(navigator.userAgent);
const shortcutHint = isMac ? '⌘K' : 'Ctrl K';
const shortcutAria = isMac ? 'Meta+K' : 'Control+K';

// Icons are presentation only; types without one fall back to the search icon
const typeIcons: Record<string, Component> = {
    groups: UsersIcon,
    polls: CalendarCheckIcon,
    events: CalendarIcon,
    lists: ListChecksIcon,
};

const allActions = computed<Entry[]>(() => [
    {
        id: 'action:newGroup',
        title: t('search.action.newGroup'),
        context: null,
        url: groupsCreate().url,
        icon: PlusIcon,
    },
    {
        id: 'action:newList',
        title: t('search.action.newList'),
        context: null,
        url: rostersCreate().url,
        icon: PlusIcon,
    },
    {
        id: 'action:dashboard',
        title: t('search.action.dashboard'),
        context: null,
        url: dashboard().url,
        icon: LayoutDashboardIcon,
    },
    {
        id: 'action:settings',
        title: t('search.action.settings'),
        context: null,
        url: settingsEdit().url,
        icon: SettingsIcon,
    },
    {
        id: 'action:friends',
        title: t('search.action.friends'),
        context: null,
        url: friendsIndex().url,
        icon: HeartIcon,
    },
]);

const actions = computed(() => {
    const needle = term.value.toLocaleLowerCase();

    if (needle === '') {
        return allActions.value;
    }

    return allActions.value.filter((action) =>
        action.title.toLocaleLowerCase().includes(needle),
    );
});

const recordGroups = computed(() =>
    sections.value.map((section) => ({
        type: section.type,
        label: section.label,
        entries: section.results.map((result): Entry => ({
            ...result,
            icon: typeIcons[section.type] ?? SearchIcon,
        })),
    })),
);

const showSkeleton = computed(
    () => active.value && loading.value && sections.value.length === 0,
);
const showEmpty = computed(
    () =>
        active.value &&
        !loading.value &&
        !failed.value &&
        sections.value.length === 0 &&
        actions.value.length === 0,
);

const status = computed(() => {
    if (!active.value) {
        return '';
    }

    if (loading.value) {
        return t('search.loading');
    }

    if (failed.value) {
        return t('search.failed');
    }

    if (showEmpty.value) {
        return t('search.empty', { query: term.value });
    }

    return '';
});

function highlightFirst(): void {
    void nextTick(() => listbox.value?.highlightFirstItem());
}

// Results arrive after typing, so the first entry gets the highlight again once they land
watch([sections, actions], highlightFirst);

watch(open, (isOpen) => {
    if (isOpen) {
        highlightFirst();

        return;
    }

    reset();
});

useEventListener(window, 'keydown', (event: KeyboardEvent) => {
    if (event.key.toLowerCase() !== 'k' || !(event.metaKey || event.ctrlKey)) {
        return;
    }

    if (event.altKey || event.shiftKey) {
        return;
    }

    event.preventDefault();
    open.value = !open.value;
});

function visit(entry: Entry): void {
    open.value = false;
    router.visit(entry.url);
}

const itemClass =
    'text-fg data-highlighted:bg-surface-muted flex w-full cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-left text-sm outline-none';
const labelClass = 'text-fg-muted px-3 pt-3 pb-1 text-xs font-semibold';
</script>

<template>
    <button
        type="button"
        class="border-border-strong bg-surface-raised text-fg-muted hover:border-fg-soft focus-visible:outline-focus-ring hidden h-9 w-full max-w-sm items-center gap-2 rounded-full border px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 sm:flex"
        aria-haspopup="dialog"
        :aria-expanded="open"
        :aria-keyshortcuts="shortcutAria"
        data-feature="F-25"
        @click="open = true"
    >
        <SearchIcon class="size-4 shrink-0" aria-hidden="true" />
        <span class="flex-1 text-left">{{ t('search.placeholder') }}</span>
        <kbd
            class="border-border bg-surface-muted rounded border px-1.5 py-0.5 font-sans text-[11px] font-medium"
            :aria-label="t('search.shortcut', { keys: shortcutHint })"
            >{{ shortcutHint }}</kbd
        >
    </button>
    <Button
        type="button"
        variant="ghost"
        size="icon-lg"
        class="rounded-full sm:hidden"
        :aria-label="t('search.open')"
        aria-haspopup="dialog"
        :aria-expanded="open"
        :aria-keyshortcuts="shortcutAria"
        data-feature="F-25"
        @click="open = true"
    >
        <SearchIcon class="size-5" aria-hidden="true" />
    </Button>

    <Dialog v-model:open="open">
        <DialogContent
            :show-close-button="false"
            class="top-[12vh] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-xl"
        >
            <DialogTitle class="sr-only">{{ t('search.title') }}</DialogTitle>
            <DialogDescription class="sr-only">{{
                t('search.description')
            }}</DialogDescription>
            <ListboxRoot ref="listbox" highlight-on-hover>
                <div
                    class="border-border flex items-center gap-2 border-b px-3"
                >
                    <SearchIcon
                        class="text-fg-muted size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <ListboxFilter
                        v-model="query"
                        auto-focus
                        :aria-label="t('search.inputLabel')"
                        :placeholder="t('search.placeholder')"
                        class="text-fg placeholder:text-fg-muted h-12 min-w-0 flex-1 bg-transparent text-base outline-none"
                    />
                </div>
                <p role="status" class="sr-only">{{ status }}</p>
                <ListboxContent class="max-h-[60vh] overflow-y-auto p-2">
                    <ListboxGroup v-if="actions.length > 0">
                        <ListboxGroupLabel :class="labelClass">{{
                            t('search.actions')
                        }}</ListboxGroupLabel>
                        <ListboxItem
                            v-for="action in actions"
                            :key="action.id"
                            :value="action.id"
                            :class="itemClass"
                            @select="visit(action)"
                        >
                            <component
                                :is="action.icon"
                                class="text-fg-muted size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ action.title }}</span>
                        </ListboxItem>
                    </ListboxGroup>
                    <ListboxGroup
                        v-for="group in recordGroups"
                        :key="group.type"
                    >
                        <ListboxGroupLabel :class="labelClass">{{
                            group.label
                        }}</ListboxGroupLabel>
                        <ListboxItem
                            v-for="entry in group.entries"
                            :key="entry.id"
                            :value="entry.id"
                            :class="itemClass"
                            @select="visit(entry)"
                        >
                            <component
                                :is="entry.icon"
                                class="text-fg-muted size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate">{{ entry.title }}</span>
                                <span
                                    v-if="entry.context"
                                    class="text-fg-soft truncate text-xs"
                                    >{{ entry.context }}</span
                                >
                            </span>
                        </ListboxItem>
                    </ListboxGroup>
                </ListboxContent>
                <div
                    v-if="showSkeleton"
                    class="flex flex-col gap-2 px-5 pb-4"
                    aria-hidden="true"
                >
                    <div
                        v-for="row in 3"
                        :key="row"
                        class="flex items-center gap-3 py-1"
                    >
                        <span
                            class="bg-surface-muted size-4 animate-pulse rounded motion-reduce:animate-none"
                        />
                        <span
                            class="bg-surface-muted h-3.5 w-2/3 animate-pulse rounded motion-reduce:animate-none"
                        />
                    </div>
                </div>
                <p
                    v-if="showEmpty"
                    class="text-fg-muted px-5 py-6 text-center text-sm"
                >
                    {{ t('search.empty', { query: term }) }}
                </p>
                <p
                    v-if="failed && active && !loading"
                    class="text-fg-muted px-5 py-4 text-center text-sm"
                >
                    {{ t('search.failed') }}
                </p>
            </ListboxRoot>
        </DialogContent>
    </Dialog>
</template>
