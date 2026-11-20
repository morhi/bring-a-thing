<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Check, Plus } from '@lucide/vue';
import {
    createWithoutGroup as createEvent,
    show as showEvent,
} from '@/actions/App/Http/Controllers/EventController';
import { index as eventsIndex } from '@/actions/App/Http/Controllers/EventsController';
import AvatarStack from '@/components/app/avatar-stack.vue';
import type { AvatarStackPerson } from '@/components/app/avatar-stack.vue';
import EmptyState from '@/components/app/empty-state.vue';
import { dayOf, formatDate, timeOf } from '@/components/app/poll/dates';
import SectionBox from '@/components/app/section-box.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

defineOptions({ layout: AppLayout });

// Mirrors App.Data.EventsIndexData
const props = defineProps<{
    events: App.Data.DashboardEventData[];
    filters: App.Data.EventsFiltersData;
}>();

const { t, locale } = useI18n();

const filterControls: {
    key: keyof App.Data.EventsFiltersData;
    label: string;
}[] = [
    { key: 'mine', label: 'events.filter.mine' },
    { key: 'all', label: 'events.filter.all' },
];

/**
 * Reload the page with one filter switched; the backend applies the filters.
 */
function setFilter(
    key: keyof App.Data.EventsFiltersData,
    value: boolean,
): void {
    const filters = { ...props.filters, [key]: value };
    // Unset filters stay out of the URL, so the default page keeps a clean address
    const query = Object.fromEntries(
        Object.entries(filters)
            .filter(([, active]) => active)
            .map(([name]) => [name, 1]),
    );

    router.get(eventsIndex.url({ query }), undefined, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function shortDate(date: string): string {
    if (!date) {
        return '';
    }

    return formatDate(date, locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function acceptedPeople(
    event: App.Data.DashboardEventData,
): AvatarStackPerson[] {
    return Array.from(
        { length: Math.max(event.accepted_count, event.accepted_names.length) },
        (_, index) => ({
            id: index,
            name: event.accepted_names[index] ?? null,
        }),
    );
}
</script>

<template>
    <Head :title="t('events.title')" />

    <div class="flex flex-col gap-10" data-feature="F-32">
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
                    <h1 class="text-h1">{{ t('events.title') }}</h1>
                    <p class="text-fg-muted max-w-[60ch]">
                        {{ t('events.lede') }}
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="createEvent().url" class="no-underline"
                        ><Plus aria-hidden="true" /> {{ t('events.new') }}</Link
                    >
                </Button>
            </div>
        </header>

        <SectionBox
            id="events-table-title"
            :title="t('dash.upcoming')"
            :count="events.length"
        >
            <template #action>
                <div
                    role="group"
                    :aria-label="t('events.filters')"
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
            <!-- Narrow cards stack the events as rows; the table only shows where its columns fit -->
            <div v-if="events.length" class="@container min-w-0">
                <ul
                    class="divide-border flex min-w-0 flex-col divide-y @[720px]:hidden"
                >
                    <li v-for="event in events" :key="event.slug">
                        <Link
                            :href="showEvent(event.slug).url"
                            class="flex min-w-0 flex-col gap-1 py-2.5 no-underline"
                        >
                            <span class="text-fg-muted text-sm tabular-nums">
                                <span class="text-fg font-semibold">{{
                                    shortDate(dayOf(event))
                                }}</span>
                                <template v-if="event.starts_at">
                                    · {{ timeOf(event.starts_at) }}</template
                                >
                            </span>
                            <span class="text-fg font-semibold break-words">{{
                                event.title
                            }}</span>
                            <span class="text-fg-muted truncate text-sm"
                                >{{ event.group_name ?? t('newList.standalone')
                                }}<template v-if="event.location">
                                    · {{ event.location }}</template
                                ></span
                            >
                        </Link>
                    </li>
                </ul>
                <table
                    class="hidden w-full border-collapse text-sm @[720px]:table"
                >
                    <thead>
                        <tr class="text-fg-muted text-left">
                            <th scope="col" class="py-2 pr-4 font-semibold">
                                {{ t('events.column.date') }}
                            </th>
                            <th scope="col" class="py-2 pr-4 font-semibold">
                                {{ t('events.column.event') }}
                            </th>
                            <th scope="col" class="py-2 pr-4 font-semibold">
                                {{ t('events.column.group') }}
                            </th>
                            <th scope="col" class="py-2 font-semibold">
                                {{ t('events.column.going') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        <tr
                            v-for="event in events"
                            :key="event.slug"
                            class="align-middle"
                        >
                            <td
                                class="text-fg-muted py-3 pr-4 whitespace-nowrap tabular-nums"
                            >
                                <span class="text-fg font-semibold">{{
                                    shortDate(dayOf(event))
                                }}</span>
                                <template v-if="event.starts_at">
                                    · {{ timeOf(event.starts_at) }}</template
                                >
                            </td>
                            <td class="py-3 pr-4">
                                <Link
                                    :href="showEvent(event.slug).url"
                                    class="text-fg hover:text-primary font-semibold break-words no-underline"
                                    >{{ event.title }}</Link
                                >
                            </td>
                            <td class="text-fg-muted py-3 pr-4">
                                <span
                                    :title="
                                        event.group_name ??
                                        t('newList.standalone')
                                    "
                                    class="block max-w-56 truncate whitespace-nowrap"
                                    >{{
                                        event.group_name ??
                                        t('newList.standalone')
                                    }}<template v-if="event.location">
                                        · {{ event.location }}</template
                                    ></span
                                >
                            </td>
                            <td class="py-3">
                                <span
                                    class="text-fg-muted flex items-center gap-2 tabular-nums"
                                >
                                    <AvatarStack
                                        v-if="event.accepted_count"
                                        :people="acceptedPeople(event)"
                                        :max="4"
                                    />
                                    {{
                                        t('dash.upcoming.going', {
                                            n: event.accepted_count,
                                        })
                                    }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else
                :icon="CalendarDays"
                :title="t('events.empty.title')"
                :text="t('events.empty.text')"
            />
        </SectionBox>
    </div>
</template>
