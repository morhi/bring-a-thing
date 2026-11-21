<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useEcho, useEchoNotification } from '@laravel/echo-vue';
import {
    ArrowRight,
    CalendarCheck,
    Check,
    ChevronRight,
    ListChecks,
    Plus,
    Vote,
} from '@lucide/vue';
import { computed, onBeforeUnmount } from 'vue';
import {
    createWithoutGroup as createEventAny,
    show as showEvent,
} from '@/actions/App/Http/Controllers/EventController';
import { index as eventsIndex } from '@/actions/App/Http/Controllers/EventsController';
import {
    create as createGroup,
    show as showGroup,
} from '@/actions/App/Http/Controllers/GroupController';
import { index as listsIndex } from '@/actions/App/Http/Controllers/ListsController';
import {
    createWithoutGroup as createPollAny,
    show as showPoll,
} from '@/actions/App/Http/Controllers/PollController';
import { index as pollsIndex } from '@/actions/App/Http/Controllers/PollsController';
import {
    create as createList,
    show as showList,
} from '@/actions/App/Http/Controllers/RosterController';
import showWithoutGroup from '@/actions/App/Http/Controllers/WithoutGroupController';
import AvatarStack from '@/components/app/avatar-stack.vue';
import type { AvatarStackPerson } from '@/components/app/avatar-stack.vue';
import EmptyState from '@/components/app/empty-state.vue';
import ListRow from '@/components/app/list-row.vue';
import {
    dayOf,
    formatDate,
    localDay,
    timeOf,
} from '@/components/app/poll/dates';
import PollRow from '@/components/app/poll-row.vue';
import SectionBox from '@/components/app/section-box.vue';
import SectionBoxItem from '@/components/app/section-box-item.vue';
import SectionEmptyState from '@/components/app/section-empty-state.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatAmount, relativeDay } from '@/lib/roster-items';
import { rowLinkClass } from '@/lib/row-link';

defineOptions({ layout: AppLayout });

// Mirrors App.Data.DashboardData; the SFC compiler only resolves imported or local types, not the global namespace
const props = defineProps<{
    waiting: App.Data.DashboardWaitingData[];
    upcoming: App.Data.DashboardEventData[];
    claims: App.Data.DashboardClaimData[];
    polls: App.Data.DashboardPollData[];
    lists: App.Data.DashboardListData[];
    groups: App.Data.DashboardGroupData[];
    withoutGroup: App.Data.DashboardWithoutGroupData | null;
}>();

const { t, tc, locale } = useI18n();
const user = computed(() => usePage().props.auth.user);

// Live updates (F-21): broadcasts only say that something changed, so the page reloads its own props.
// A short delay folds a burst of broadcasts (a fix creating several events) into one request.
let reloadTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleReload(): void {
    if (reloadTimer !== null) {
        return;
    }

    reloadTimer = setTimeout(() => {
        reloadTimer = null;
        router.reload({
            only: [
                'waiting',
                'upcoming',
                'claims',
                'polls',
                'lists',
                'groups',
                'withoutGroup',
            ],
        });
    }, 300);
}

if (user.value) {
    useEcho(
        `App.Models.User.${user.value.id}`,
        '.dashboard.changed',
        scheduleReload,
    );
    useEchoNotification(
        `App.Models.User.${user.value.id}`,
        scheduleReload,
        'notification.created',
    );
}

onBeforeUnmount(() => {
    if (reloadTimer !== null) {
        clearTimeout(reloadTimer);
    }
});

const greeting = computed(() => {
    const hour = new Date().getHours();
    const key = hour < 11 ? 'morning' : hour < 18 ? 'day' : 'evening';
    const name = user.value?.name?.split(' ')[0] ?? user.value?.email ?? '';

    return t(`dash.greeting.${key}`, { name });
});

const today = computed(() =>
    formatDate(localDay(new Date().toISOString()), locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);

// With nothing to show in any section only the two entry points are worth showing
const firstRun = computed(
    () =>
        props.groups.length === 0 &&
        props.withoutGroup === null &&
        props.lists.length === 0 &&
        props.waiting.length === 0 &&
        props.upcoming.length === 0 &&
        props.claims.length === 0 &&
        props.polls.length === 0,
);

const roleLabel: Record<App.Enums.GroupRole, string> = {
    owner: 'group.member.owner',
    admin: 'group.member.admin',
    member: 'group.member.member',
};

function shortDate(date: string | null): string {
    if (!date) {
        return '';
    }

    return formatDate(date, locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

// Guests see no member count; the rest is limited to what they reach (F-26, F-27)
function groupCounts(
    group: App.Data.DashboardGroupData,
): { value: number; label: string }[] {
    const counts = [
        { value: group.open_poll_count, label: 'word.openPolls' },
        { value: group.upcoming_event_count, label: 'word.upcoming' },
        { value: group.active_list_count, label: 'word.activeLists' },
    ];

    if (group.member_count === null) {
        return counts;
    }

    return [{ value: group.member_count, label: 'word.members' }, ...counts];
}

// Polls, events and lists without a group, as one counted phrase each
const withoutGroupCounts = computed(() => {
    if (!props.withoutGroup) {
        return [];
    }

    return [
        tc('dash.withoutGroup.polls', props.withoutGroup.polls_count),
        tc('dash.withoutGroup.events', props.withoutGroup.events_count),
        tc('dash.withoutGroup.lists', props.withoutGroup.lists_count),
    ];
});

function weekdayDate(date: string | null): string {
    if (!date) {
        return '';
    }

    return formatDate(date, locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

function waitingHref(item: App.Data.DashboardWaitingData): string {
    return item.kind === 'poll'
        ? showPoll(item.slug).url
        : showEvent(item.slug).url;
}

function waitingMeta(item: App.Data.DashboardWaitingData): string {
    if (item.kind === 'poll') {
        const answered = item.answered
            ? t('dash.waiting.pollMeta', {
                  answered: item.answered,
                  total: item.total ?? 0,
              })
            : t('dash.waiting.pollMetaNone');

        return `${item.group_name ?? t('newList.standalone')} · ${answered}`;
    }

    const date = dayOf({ date: item.date, starts_at: item.starts_at });
    const day = date
        ? formatDate(date, locale.value, {
              weekday: 'short',
              day: 'numeric',
              month: 'short',
          })
        : '';

    const accepted = tc('dash.waiting.eventMeta', item.accepted_count ?? 0, {
        accepted: item.accepted_count ?? 0,
    });

    return `${item.group_name ?? t('newList.standalone')} · ${day}${item.starts_at ? `, ${timeOf(item.starts_at)}` : ''} · ${accepted}`;
}

function waitingFeature(item: App.Data.DashboardWaitingData): string {
    if (item.kind === 'event') {
        return 'F-11';
    }

    return item.source_event ? 'F-12' : 'F-06';
}

// Only the first names arrive; the rest still count towards the "+n"
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
    <Head :title="t('nav.dashboard')" />

    <div class="flex flex-col gap-10 min-[720px]:gap-14" data-feature="F-22">
        <header class="flex flex-col gap-1.5">
            <h1 class="text-h1 break-words">{{ greeting }}</h1>
            <p class="text-fg-soft text-[15px]">{{ today }}</p>
        </header>

        <EmptyState
            v-if="firstRun"
            :icon="ListChecks"
            :title="t('dash.empty.title')"
            :text="t('dash.empty.text')"
        >
            <Button as-child size="lg">
                <Link
                    :href="createGroup().url"
                    class="text-primary-fg hover:text-primary-fg no-underline"
                >
                    <Plus aria-hidden="true" /> {{ t('dash.groups.new') }}
                </Link>
            </Button>
            <Button as-child size="lg" variant="outline">
                <Link :href="createList().url" class="no-underline">{{
                    t('dash.lists.new')
                }}</Link>
            </Button>
        </EmptyState>

        <template v-else>
            <!-- The one block that asks for action keeps the only strong surface on the page -->
            <SectionBox
                id="waiting-title"
                :title="t('dash.waiting')"
                :count="waiting.length"
                collapsible
                :default-open="waiting.length > 0"
                :class="
                    waiting.length
                        ? 'bg-primary-soft text-primary-soft-fg [&_h2]:text-primary-soft-fg'
                        : ''
                "
            >
                <ul
                    v-if="waiting.length"
                    class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,380px),1fr))] gap-2.5"
                >
                    <li
                        v-for="item in waiting"
                        :key="`${item.kind}-${item.slug}`"
                    >
                        <Link
                            :href="waitingHref(item)"
                            :data-feature="waitingFeature(item)"
                            class="bg-surface-raised text-fg hover:text-fg hover:border-primary-ring flex h-full flex-wrap items-center gap-x-3.5 gap-y-2 rounded-xl border border-transparent px-4 py-3.5 no-underline transition hover:-translate-y-px motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                        >
                            <span
                                class="bg-primary-soft text-primary-soft-fg grid size-10 flex-none place-items-center rounded-xl"
                            >
                                <Vote
                                    v-if="item.kind === 'poll'"
                                    class="size-5"
                                    aria-hidden="true"
                                />
                                <CalendarCheck
                                    v-else
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <span
                                class="flex min-w-0 flex-[1_1_12rem] flex-col gap-0.5"
                            >
                                <span class="font-semibold break-words">{{
                                    item.title
                                }}</span>
                                <span class="text-fg-muted text-sm">{{
                                    waitingMeta(item)
                                }}</span>
                                <span
                                    v-if="item.source_event"
                                    class="text-fg-muted text-sm"
                                >
                                    {{
                                        t('group.polls.followUp', {
                                            event: item.source_event.title,
                                        })
                                    }}
                                </span>
                            </span>
                            <!-- Below the title on narrow screens, so the title keeps its width -->
                            <span
                                class="text-primary ml-auto text-sm font-semibold whitespace-nowrap"
                            >
                                {{
                                    item.kind === 'poll'
                                        ? t('dash.waiting.poll')
                                        : t('dash.waiting.event')
                                }}
                            </span>
                        </Link>
                    </li>
                </ul>
                <SectionEmptyState
                    v-else
                    :title="t('dash.waiting.caughtUp')"
                    :text="t('dash.waiting.none')"
                />
            </SectionBox>

            <SectionBox
                id="polls-title"
                :title="t('dash.polls')"
                :count="polls.length"
                collapsible
                :default-open="polls.length > 0"
                data-feature="F-06"
            >
                <template #action>
                    <Button as-child variant="ghost" size="sm">
                        <Link
                            :href="pollsIndex().url"
                            class="no-underline"
                            data-feature="F-31"
                            ><span class="sr-only @[30rem]:not-sr-only">{{
                                t('dash.polls.all')
                            }}</span>
                            <ArrowRight aria-hidden="true"
                        /></Link>
                    </Button>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="createPollAny().url" class="no-underline"
                            ><Plus aria-hidden="true" />
                            {{ t('dash.polls.new') }}</Link
                        >
                    </Button>
                </template>
                <ul
                    v-if="polls.length"
                    class="divide-border flex min-w-0 flex-col divide-y"
                >
                    <li v-for="poll in polls" :key="poll.slug">
                        <PollRow :poll="poll" />
                    </li>
                </ul>
                <SectionEmptyState
                    v-else
                    :title="t('dash.polls.empty.title')"
                    :text="t('dash.polls.empty')"
                />
            </SectionBox>

            <SectionBox
                id="claims-title"
                :title="t('dash.claims')"
                :count="claims.length"
                collapsible
                :default-open="claims.length > 0"
                data-feature="F-15"
            >
                <ul
                    v-if="claims.length"
                    class="divide-border flex min-w-0 flex-col divide-y"
                >
                    <li
                        v-for="(claim, index) in claims"
                        :key="`${claim.roster_slug}-${index}`"
                    >
                        <Link
                            :href="showList(claim.roster_slug).url"
                            :class="[
                                rowLinkClass,
                                'flex items-center gap-3 py-3',
                            ]"
                        >
                            <span
                                class="font-display text-primary min-w-12 text-[15px] font-bold tabular-nums"
                            >
                                <template v-if="claim.quantity !== null">{{
                                    formatAmount(
                                        claim.quantity,
                                        claim.unit,
                                        locale,
                                    )
                                }}</template>
                                <Check
                                    v-else
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </span>
                            <span
                                class="flex min-w-0 flex-1 flex-wrap items-baseline gap-x-4 gap-y-0.5"
                            >
                                <span
                                    class="group-hover:text-primary font-semibold break-words"
                                    >{{ claim.item_name }}</span
                                >
                                <span class="text-fg-muted text-sm break-words">
                                    {{
                                        t('dash.claims.for', {
                                            list: claim.roster_title,
                                        })
                                    }}<template v-if="claim.date">
                                        ·
                                        {{ shortDate(claim.date) }}</template
                                    >
                                </span>
                            </span>
                            <ChevronRight
                                class="text-fg-soft size-5 flex-none"
                                aria-hidden="true"
                            />
                        </Link>
                    </li>
                </ul>
                <SectionEmptyState
                    v-else
                    :title="t('dash.claims.empty.title')"
                    :text="t('dash.claims.empty.text')"
                />
            </SectionBox>

            <SectionBox
                id="upcoming-title"
                :title="t('dash.upcoming')"
                :count="upcoming.length"
                collapsible
                :default-open="upcoming.length > 0"
                data-feature="F-10"
            >
                <template #action>
                    <Button as-child variant="ghost" size="sm">
                        <Link
                            :href="eventsIndex().url"
                            class="no-underline"
                            data-feature="F-32"
                            ><span class="sr-only @[30rem]:not-sr-only">{{
                                t('dash.upcoming.all')
                            }}</span>
                            <ArrowRight aria-hidden="true"
                        /></Link>
                    </Button>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="createEventAny().url" class="no-underline"
                            ><Plus aria-hidden="true" />
                            {{ t('dash.upcoming.new') }}</Link
                        >
                    </Button>
                </template>
                <ul
                    v-if="upcoming.length"
                    class="divide-border @container flex min-w-0 flex-col divide-y"
                >
                    <li v-for="event in upcoming" :key="event.slug">
                        <Link
                            :href="showEvent(event.slug).url"
                            :class="[
                                rowLinkClass,
                                'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 py-2.5 @[760px]:grid-cols-[8.5rem_minmax(0,1fr)_minmax(0,12rem)_7rem_11rem_6.5rem]',
                            ]"
                        >
                            <span class="text-fg-muted text-sm tabular-nums">
                                <span class="text-fg font-semibold">{{
                                    weekdayDate(dayOf(event))
                                }}</span>
                                <template v-if="event.starts_at">
                                    · {{ timeOf(event.starts_at) }}</template
                                >
                            </span>
                            <Badge
                                class="bg-primary-soft text-primary-soft-fg justify-self-end @[760px]:order-last"
                                >{{ relativeDay(dayOf(event)) }}</Badge
                            >
                            <span
                                class="group-hover:text-primary col-span-2 min-w-0 font-semibold break-words @[760px]:col-span-1"
                                >{{ event.title }}</span
                            >
                            <span
                                class="text-fg-muted col-span-2 min-w-0 truncate text-sm @[760px]:col-span-1"
                                >{{ event.group_name ?? t('newList.standalone')
                                }}<template v-if="event.location">
                                    · {{ event.location }}</template
                                ></span
                            >
                            <span
                                class="text-primary inline-flex items-center gap-1 text-sm font-semibold"
                                data-feature="F-11"
                            >
                                <Check class="size-3.5" aria-hidden="true" />
                                {{ t('dash.upcoming.accepted') }}
                            </span>
                            <span
                                class="text-fg-muted flex items-center justify-end gap-2 text-sm tabular-nums"
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
                        </Link>
                    </li>
                </ul>
                <SectionEmptyState
                    v-else
                    :title="t('dash.upcoming.empty.title')"
                    :text="t('dash.upcoming.empty.text')"
                />
            </SectionBox>

            <SectionBox
                id="lists-title"
                :title="t('dash.lists')"
                :count="lists.length"
                collapsible
                :default-open="lists.length > 0"
                data-feature="F-13"
            >
                <template #action>
                    <Button as-child variant="ghost" size="sm">
                        <Link
                            :href="listsIndex().url"
                            class="no-underline"
                            data-feature="F-28"
                            ><span class="sr-only @[30rem]:not-sr-only">{{
                                t('dash.lists.all')
                            }}</span>
                            <ArrowRight aria-hidden="true"
                        /></Link>
                    </Button>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="createList().url" class="no-underline"
                            ><Plus aria-hidden="true" />
                            {{ t('dash.lists.new') }}</Link
                        >
                    </Button>
                </template>
                <ul
                    v-if="lists.length"
                    class="divide-border flex min-w-0 flex-col divide-y"
                >
                    <li v-for="list in lists" :key="list.slug">
                        <ListRow :list="list" />
                    </li>
                </ul>
                <SectionEmptyState
                    v-else
                    :title="t('dash.lists.empty.title')"
                    :text="t('dash.lists.empty')"
                />
            </SectionBox>

            <SectionBox
                id="groups-title"
                :title="t('dash.groups')"
                :count="groups.length"
                collapsible
                :default-open="groups.length > 0 || withoutGroup !== null"
                data-feature="F-03"
            >
                <template #action>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="createGroup().url" class="no-underline"
                            ><Plus aria-hidden="true" />
                            {{ t('dash.groups.new') }}</Link
                        >
                    </Button>
                </template>
                <ul
                    v-if="groups.length || withoutGroup"
                    class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,260px),1fr))] items-start gap-4"
                >
                    <li v-for="group in groups" :key="group.slug">
                        <SectionBoxItem
                            :as="Link"
                            :href="showGroup(group.slug).url"
                            class="flex flex-col gap-3.5 p-5"
                        >
                            <span
                                class="flex items-start justify-between gap-3"
                            >
                                <span
                                    class="font-display min-w-0 text-lg font-[640] break-words"
                                    >{{ group.name }}</span
                                >
                                <Badge
                                    v-if="group.is_guest"
                                    class="bg-info-soft text-info"
                                    data-feature="F-26"
                                    >{{ t('group.guest') }}</Badge
                                >
                                <Badge
                                    v-else-if="
                                        group.role && group.role !== 'member'
                                    "
                                    variant="outline"
                                    >{{ t(roleLabel[group.role]) }}</Badge
                                >
                            </span>
                            <!-- Every card lists its counts in the same order, so the cards read alike -->
                            <span
                                class="text-fg-muted flex flex-wrap gap-x-4 gap-y-1 text-sm"
                            >
                                <span
                                    v-for="count in groupCounts(group)"
                                    :key="count.label"
                                    ><b
                                        class="text-fg font-semibold tabular-nums"
                                        >{{ count.value }}</b
                                    >
                                    {{ tc(count.label, count.value) }}</span
                                >
                            </span>
                        </SectionBoxItem>
                    </li>
                    <li v-if="withoutGroup">
                        <SectionBoxItem
                            :as="Link"
                            :href="showWithoutGroup().url"
                            class="flex flex-col gap-3.5 border-dashed p-5"
                            data-feature="F-29"
                        >
                            <span class="flex flex-col gap-1">
                                <span
                                    class="font-display min-w-0 text-lg font-[640] break-words"
                                    >{{ t('dash.withoutGroup') }}</span
                                >
                                <span class="text-fg-muted text-sm">{{
                                    t('dash.withoutGroup.text')
                                }}</span>
                            </span>
                            <span
                                class="text-fg-muted flex flex-wrap gap-x-4 gap-y-1 text-sm"
                            >
                                <span
                                    v-for="count in withoutGroupCounts"
                                    :key="count"
                                    >{{ count }}</span
                                >
                            </span>
                        </SectionBoxItem>
                    </li>
                </ul>
                <SectionEmptyState
                    v-else
                    :title="t('dash.groups.empty.title')"
                    :text="t('dash.groups.empty.text')"
                >
                    <Button as-child>
                        <Link
                            :href="createGroup().url"
                            class="text-primary-fg hover:text-primary-fg no-underline"
                            ><Plus aria-hidden="true" />
                            {{ t('dash.groups.new') }}</Link
                        >
                    </Button>
                </SectionEmptyState>
            </SectionBox>
        </template>
    </div>
</template>
