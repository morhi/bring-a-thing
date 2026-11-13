<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, Link2, ListChecks, Plus } from '@lucide/vue';
import { show as showEvent } from '@/actions/App/Http/Controllers/EventController';
import { index as listsIndex } from '@/actions/App/Http/Controllers/ListsController';
import {
    create as createList,
    show as showList,
} from '@/actions/App/Http/Controllers/RosterController';
import EmptyState from '@/components/app/empty-state.vue';
import ListRow from '@/components/app/list-row.vue';
import { formatDate } from '@/components/app/poll/dates';
import ProgressBar from '@/components/app/progress-bar.vue';
import SectionBox from '@/components/app/section-box.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

defineOptions({ layout: AppLayout });

// Mirrors App.Data.ListsIndexData
const props = defineProps<{
    lists: App.Data.DashboardListData[];
    filters: App.Data.ListsFiltersData;
}>();

const { t, locale } = useI18n();

const filterControls: {
    key: keyof App.Data.ListsFiltersData;
    label: string;
}[] = [
    { key: 'mine', label: 'lists.filter.mine' },
    { key: 'tasks', label: 'lists.filter.tasks' },
    { key: 'all', label: 'lists.filter.all' },
];

/**
 * Reload the page with one filter switched; the backend applies the filters.
 */
function setFilter(key: keyof App.Data.ListsFiltersData, value: boolean): void {
    const filters = { ...props.filters, [key]: value };
    // Unset filters stay out of the URL, so the default page keeps a clean address
    const query = Object.fromEntries(
        Object.entries(filters)
            .filter(([, active]) => active)
            .map(([name]) => [name, 1]),
    );

    router.get(listsIndex.url({ query }), undefined, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function shortDate(date: string): string {
    return formatDate(date, locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="t('lists.title')" />

    <div class="flex flex-col gap-10" data-feature="F-28">
        <header class="flex flex-col gap-2.5">
            <Link
                :href="dashboard().url"
                class="text-fg-muted hover:text-fg -mt-0.5 mb-1 inline-flex min-h-6 w-fit items-center gap-1.5 rounded-sm text-sm font-semibold no-underline"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('nav.backToDashboard') }}
            </Link>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-h1">{{ t('lists.title') }}</h1>
                    <p class="text-fg-muted max-w-[60ch]">
                        {{ t('lists.lede') }}
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="createList().url" class="no-underline"
                        ><Plus aria-hidden="true" />
                        {{ t('dash.lists.new') }}</Link
                    >
                </Button>
            </div>
        </header>

        <SectionBox
            id="lists-table-title"
            :title="t('dash.lists')"
            :count="lists.length"
        >
            <template #action>
                <div
                    role="group"
                    :aria-label="t('lists.filters')"
                    class="flex flex-wrap gap-2"
                >
                    <Button
                        v-for="control in filterControls"
                        :key="control.key"
                        type="button"
                        size="lg"
                        :variant="filters[control.key] ? 'default' : 'outline'"
                        :aria-pressed="filters[control.key]"
                        :class="[
                            'rounded-full px-3.5',
                            filters[control.key] &&
                                'text-primary-fg hover:bg-primary/90',
                        ]"
                        @click="setFilter(control.key, !filters[control.key])"
                    >
                        <!-- The check keeps the active state readable without relying on color -->
                        <Check v-if="filters[control.key]" aria-hidden="true" />
                        {{ t(control.label) }}
                    </Button>
                </div>
            </template>
            <!-- Narrow cards stack the lists as rows; the table only shows where its columns fit -->
            <div v-if="lists.length" class="@container min-w-0">
                <ul
                    class="divide-border flex min-w-0 flex-col divide-y @[720px]:hidden"
                >
                    <li v-for="list in lists" :key="list.slug">
                        <ListRow :list="list" />
                    </li>
                </ul>
                <table
                    class="hidden w-full border-collapse text-sm @[720px]:table"
                >
                    <thead>
                        <tr class="text-fg-muted text-left">
                            <th scope="col" class="py-2 pr-4 font-semibold">
                                {{ t('lists.column.list') }}
                            </th>
                            <th
                                scope="col"
                                class="w-56 min-w-40 py-2 pr-4 font-semibold"
                            >
                                {{ t('lists.column.placement') }}
                            </th>
                            <th scope="col" class="py-2 pr-4 font-semibold">
                                {{ t('lists.column.date') }}
                            </th>
                            <th scope="col" class="py-2 font-semibold">
                                {{ t('lists.column.progress') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        <tr
                            v-for="list in lists"
                            :key="list.slug"
                            class="align-middle"
                        >
                            <td class="py-3 pr-4">
                                <span class="flex min-w-0 flex-col gap-1">
                                    <!-- Icon stays beside the title; only title and badges wrap -->
                                    <span class="flex items-start gap-2">
                                        <ListChecks
                                            class="text-fg-soft mt-0.5 size-4 flex-none"
                                            aria-hidden="true"
                                        />
                                        <span
                                            class="flex min-w-0 flex-1 flex-wrap items-center gap-2"
                                        >
                                            <Link
                                                :href="showList(list.slug).url"
                                                class="text-fg hover:text-primary font-semibold break-words no-underline"
                                                >{{ list.title }}</Link
                                            >
                                            <Badge
                                                v-if="list.is_past"
                                                variant="outline"
                                                >{{ t('list.past') }}</Badge
                                            >
                                            <Badge
                                                v-if="list.is_shared"
                                                variant="outline"
                                                data-feature="F-19"
                                            >
                                                <Link2 aria-hidden="true" />
                                                {{ t('dash.lists.shared') }}
                                            </Badge>
                                        </span>
                                    </span>
                                </span>
                            </td>
                            <td class="text-fg-muted py-3 pr-4">
                                <!-- Long labels end in an ellipsis instead of breaking, so rows keep one height -->
                                <Link
                                    v-if="list.event_slug"
                                    :href="showEvent(list.event_slug).url"
                                    :title="list.event_title ?? undefined"
                                    class="text-fg-muted hover:text-fg block max-w-56 truncate break-normal whitespace-nowrap"
                                    >{{ list.event_title }}</Link
                                >
                                <span
                                    v-else
                                    :title="
                                        list.group_name ??
                                        t('newList.standalone')
                                    "
                                    class="block max-w-56 truncate whitespace-nowrap"
                                    >{{
                                        list.group_name ??
                                        t('newList.standalone')
                                    }}</span
                                >
                            </td>
                            <td
                                class="text-fg-muted py-3 pr-4 whitespace-nowrap"
                            >
                                {{ list.date ? shortDate(list.date) : '' }}
                            </td>
                            <td class="w-44 py-3">
                                <span class="flex items-center gap-2">
                                    <ProgressBar
                                        :done="list.claimed_count"
                                        :total="list.item_count"
                                        :label="
                                            t('dash.lists.claimed', {
                                                done: list.claimed_count,
                                                total: list.item_count,
                                            })
                                        "
                                    />
                                    <span class="text-fg-muted tabular-nums"
                                        >{{ list.claimed_count }}/{{
                                            list.item_count
                                        }}</span
                                    >
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else
                :icon="ListChecks"
                :title="t('lists.empty.title')"
                :text="t('lists.empty.text')"
            />
        </SectionBox>
    </div>
</template>
