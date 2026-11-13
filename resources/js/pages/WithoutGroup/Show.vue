<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarCheck } from '@lucide/vue';
import { computed } from 'vue';
import { show as showEvent } from '@/actions/App/Http/Controllers/EventController';
import DataRow from '@/components/app/data-row.vue';
import EmptyState from '@/components/app/empty-state.vue';
import ListRow from '@/components/app/list-row.vue';
import { formatDate } from '@/components/app/poll/dates';
import PollRow from '@/components/app/poll-row.vue';
import SectionBox from '@/components/app/section-box.vue';
import { Badge } from '@/components/ui/badge';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

defineOptions({ layout: AppLayout });

// Mirrors App.Data.WithoutGroupData
const props = defineProps<{
    events: App.Data.EventSummaryData[];
    polls: App.Data.DashboardPollData[];
    lists: App.Data.DashboardListData[];
}>();

const { t, locale } = useI18n();

const isEmpty = computed(
    () =>
        props.events.length === 0 &&
        props.polls.length === 0 &&
        props.lists.length === 0,
);

function shortDate(date: string): string {
    return formatDate(date, locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

// Weekday and start time fill the context column, since the date column only fits the date itself
function eventTime(event: App.Data.EventSummaryData): string {
    const weekday = formatDate(event.date, locale.value, { weekday: 'short' });

    return event.starts_at ? `${weekday}, ${event.starts_at}` : weekday;
}
</script>

<template>
    <Head :title="t('withoutGroup.title')" />

    <div class="flex flex-col gap-10" data-feature="F-29">
        <header class="flex flex-col gap-2.5">
            <Link
                :href="dashboard().url"
                class="text-fg-muted hover:text-fg -mt-0.5 mb-1 inline-flex min-h-6 w-fit items-center gap-1.5 rounded-sm text-sm font-semibold no-underline"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('nav.backToDashboard') }}
            </Link>
            <h1 class="text-h1">{{ t('withoutGroup.title') }}</h1>
            <p class="text-fg-muted max-w-[60ch]">
                {{ t('withoutGroup.lede') }}
            </p>
        </header>

        <EmptyState
            v-if="isEmpty"
            :title="t('withoutGroup.empty.title')"
            :text="t('withoutGroup.empty.text')"
        />

        <template v-else>
            <SectionBox
                v-if="events.length"
                id="without-group-events"
                :title="t('withoutGroup.events')"
                :count="events.length"
            >
                <ul class="divide-border flex min-w-0 flex-col divide-y">
                    <li v-for="event in events" :key="event.slug">
                        <DataRow
                            :href="showEvent(event.slug).url"
                            :icon="CalendarCheck"
                            count-class="w-0"
                            data-feature="F-10"
                        >
                            <template #title>{{ event.title }}</template>
                            <template #context>{{ eventTime(event) }}</template>
                            <template #date>{{
                                shortDate(event.date)
                            }}</template>
                            <template #badges>
                                <Badge v-if="event.is_past" variant="outline">{{
                                    t('event.past')
                                }}</Badge>
                            </template>
                        </DataRow>
                    </li>
                </ul>
            </SectionBox>

            <SectionBox
                v-if="polls.length"
                id="without-group-polls"
                :title="t('withoutGroup.polls')"
                :count="polls.length"
            >
                <ul class="divide-border flex min-w-0 flex-col divide-y">
                    <li v-for="poll in polls" :key="poll.slug">
                        <PollRow :poll="poll" />
                    </li>
                </ul>
            </SectionBox>

            <SectionBox
                v-if="lists.length"
                id="without-group-lists"
                :title="t('withoutGroup.lists')"
                :count="lists.length"
            >
                <ul class="divide-border flex min-w-0 flex-col divide-y">
                    <li v-for="list in lists" :key="list.slug">
                        <ListRow :list="list" />
                    </li>
                </ul>
            </SectionBox>
        </template>
    </div>
</template>
