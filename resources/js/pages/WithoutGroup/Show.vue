<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/app/empty-state.vue';
import ListRow from '@/components/app/list-row.vue';
import EventRow from '@/components/app/event-row.vue';
import PollRow from '@/components/app/poll-row.vue';
import SectionBox from '@/components/app/section-box.vue';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

defineOptions({ layout: AppLayout });

// Mirrors App.Data.WithoutGroupData
const props = defineProps<{
    lists: App.Data.DashboardListData[];
    polls: App.Data.DashboardPollData[];
    events: App.Data.DashboardEventData[];
}>();

const isEmpty = computed(
    () => !props.lists.length && !props.polls.length && !props.events.length,
);

const { t } = useI18n();
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

        <SectionBox
            v-if="polls.length"
            id="without-group-polls"
            :title="t('withoutGroup.polls')"
            :count="polls.length"
            data-feature="F-06"
        >
            <ul class="divide-border flex min-w-0 flex-col divide-y">
                <li v-for="poll in polls" :key="poll.slug">
                    <PollRow :poll="poll" />
                </li>
            </ul>
        </SectionBox>

        <SectionBox
            v-if="events.length"
            id="without-group-events"
            :title="t('withoutGroup.events')"
            :count="events.length"
            data-feature="F-10"
        >
            <ul class="divide-border flex min-w-0 flex-col divide-y">
                <li v-for="event in events" :key="event.slug">
                    <EventRow :event="event" />
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

        <EmptyState
            v-if="isEmpty"
            :title="t('withoutGroup.empty.title')"
            :text="t('withoutGroup.empty.text')"
        />
    </div>
</template>
