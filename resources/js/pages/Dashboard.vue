<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarCheck,
    Check,
    ChevronRight,
    Clock,
    Link2,
    ListChecks,
    Plus,
    Vote,
} from '@lucide/vue';
import { computed } from 'vue';
import { show as showEvent } from '@/actions/App/Http/Controllers/EventController';
import {
    create as createGroup,
    show as showGroup,
} from '@/actions/App/Http/Controllers/GroupController';
import { show as showPoll } from '@/actions/App/Http/Controllers/PollController';
import {
    create as createList,
    show as showList,
} from '@/actions/App/Http/Controllers/RosterController';
import AvatarStack from '@/components/app/avatar-stack.vue';
import type { AvatarStackPerson } from '@/components/app/avatar-stack.vue';
import DateBlock from '@/components/app/date-block.vue';
import EmptyState from '@/components/app/empty-state.vue';
import { formatDate, toIso } from '@/components/app/poll/dates';
import ProgressBar from '@/components/app/progress-bar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatAmount, relativeDay } from '@/lib/roster-items';

defineOptions({ layout: AppLayout });

// Mirrors App.Data.DashboardData; the SFC compiler cannot resolve the global type itself
const props = defineProps<{
    waiting: App.Data.DashboardWaitingData[];
    upcoming: App.Data.DashboardEventData[];
    claims: App.Data.DashboardClaimData[];
    groups: App.Data.DashboardGroupData[];
    lists: App.Data.DashboardListData[];
    counts: App.Data.DashboardCountsData;
}>();

const { t, locale } = useI18n();
const user = computed(() => usePage().props.auth.user);

const greeting = computed(() => {
    const hour = new Date().getHours();
    const key = hour < 11 ? 'morning' : hour < 18 ? 'day' : 'evening';
    const name = user.value?.name?.split(' ')[0] ?? user.value?.email ?? '';

    return t(`dash.greeting.${key}`, { name });
});

const today = computed(() =>
    formatDate(toIso(new Date()), locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);

// Section 8: without groups and lists only the two entry points are worth showing
const firstRun = computed(
    () => props.groups.length === 0 && props.lists.length === 0,
);

const roleLabel: Record<App.Enums.GroupRole, string> = {
    owner: 'group.member.owner',
    admin: 'group.member.admin',
    member: 'group.member.member',
};

function shortDate(date: string): string {
    return formatDate(date, locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
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

        return `${item.group_name} · ${answered}`;
    }

    const day = item.date
        ? formatDate(item.date, locale.value, {
              weekday: 'short',
              day: 'numeric',
              month: 'short',
          })
        : '';

    return `${item.group_name} · ${day}${item.starts_at ? `, ${item.starts_at}` : ''}`;
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

const sectionHead =
    'flex flex-wrap items-center justify-between gap-x-4 gap-y-2';
const sectionTitle = 'flex items-center gap-2.5 text-xl';
const countClass = 'text-fg-soft font-sans text-sm font-semibold tabular-nums';
const cardClass =
    'box flex no-underline text-fg hover:text-fg transition-colors hover:border-border-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring';
</script>

<template>
    <Head :title="t('nav.dashboard')" />

    <div class="flex flex-col gap-10" data-feature="F-22">
        <header class="flex flex-col gap-1.5">
            <h1 class="text-[clamp(30px,2.6vw+18px,44px)] break-words">
                {{ greeting }}
            </h1>
            <p class="text-fg-soft text-[15px]">{{ today }}</p>
        </header>

        <section v-if="firstRun">
            <EmptyState
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
                <Button as-child size="lg" variant="secondary">
                    <Link :href="createList().url" class="no-underline">{{
                        t('dash.lists.new')
                    }}</Link>
                </Button>
            </EmptyState>
        </section>

        <template v-else>
            <section
                aria-labelledby="waiting-title"
                :class="
                    waiting.length
                        ? 'bg-primary-soft text-primary-soft-fg flex flex-col gap-3.5 rounded-(--card-radius) p-[22px] sm:px-7 sm:py-[26px]'
                        : 'flex flex-col gap-4'
                "
            >
                <h2
                    id="waiting-title"
                    class="flex items-center gap-2.5 text-xl"
                    :class="{ 'text-primary-soft-fg': waiting.length > 0 }"
                >
                    {{ t('dash.waiting') }}
                    <span
                        v-if="waiting.length"
                        class="bg-primary text-primary-fg inline-grid h-7 min-w-7 place-items-center rounded-full px-2 font-sans text-sm font-bold tabular-nums"
                        >{{ waiting.length }}</span
                    >
                </h2>
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
                            class="bg-surface-raised text-fg hover:text-fg hover:border-primary-ring focus-visible:outline-focus-ring flex h-full flex-wrap items-center gap-x-3.5 gap-y-2 rounded-xl border border-transparent px-4 py-3.5 no-underline transition hover:-translate-y-px focus-visible:outline-2 focus-visible:outline-offset-2 motion-reduce:transition-none motion-reduce:hover:translate-y-0"
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
                <EmptyState
                    v-else
                    :icon="Check"
                    :title="t('dash.waiting.done.title')"
                    :text="t('dash.waiting.done.text')"
                />
            </section>

            <div
                class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,420px),1fr))] gap-x-12 gap-y-10"
            >
                <section
                    class="flex min-w-0 flex-col gap-4"
                    aria-labelledby="upcoming-title"
                    data-feature="F-10"
                >
                    <h2 id="upcoming-title" :class="sectionTitle">
                        {{ t('dash.upcoming') }}
                        <span v-if="upcoming.length" :class="countClass">{{
                            upcoming.length
                        }}</span>
                    </h2>
                    <ul v-if="upcoming.length" class="flex flex-col gap-3">
                        <li v-for="event in upcoming" :key="event.slug">
                            <Link
                                :href="showEvent(event.slug).url"
                                :class="[
                                    cardClass,
                                    'items-start gap-4 p-[18px]',
                                ]"
                            >
                                <DateBlock
                                    :date="event.date"
                                    :fixed="event.is_fixed_from_poll"
                                />
                                <span
                                    class="flex min-w-0 flex-1 flex-col gap-1.5"
                                >
                                    <span
                                        class="font-display text-[17px] leading-tight font-[640] break-words"
                                        >{{ event.title }}</span
                                    >
                                    <span
                                        class="text-fg-muted flex flex-wrap gap-x-3 gap-y-1.5 text-sm"
                                    >
                                        <span class="sr-only">{{
                                            shortDate(event.date)
                                        }}</span>
                                        <span>{{ event.group_name }}</span>
                                        <span
                                            v-if="event.starts_at"
                                            class="inline-flex items-center gap-1"
                                        >
                                            <Clock
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                            {{ event.starts_at }}
                                        </span>
                                        <span
                                            v-if="event.location"
                                            class="break-words"
                                            >{{ event.location }}</span
                                        >
                                    </span>
                                    <span
                                        class="mt-1 flex flex-wrap items-center justify-between gap-2.5"
                                    >
                                        <AvatarStack
                                            v-if="event.accepted_count"
                                            :people="acceptedPeople(event)"
                                            :max="5"
                                        />
                                        <Badge
                                            class="bg-primary-soft text-primary-soft-fg ml-auto"
                                            >{{
                                                relativeDay(event.date)
                                            }}</Badge
                                        >
                                    </span>
                                </span>
                            </Link>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        :title="t('dash.upcoming.empty.title')"
                        :text="t('dash.upcoming.empty.text')"
                    />
                </section>

                <section
                    class="flex min-w-0 flex-col gap-4"
                    aria-labelledby="claims-title"
                    data-feature="F-15"
                >
                    <h2 id="claims-title" :class="sectionTitle">
                        {{ t('dash.claims') }}
                        <span v-if="claims.length" :class="countClass">{{
                            claims.length
                        }}</span>
                    </h2>
                    <ul
                        v-if="claims.length"
                        class="box divide-border flex flex-col divide-y px-5 py-1.5"
                    >
                        <li
                            v-for="(claim, index) in claims"
                            :key="`${claim.roster_slug}-${index}`"
                        >
                            <Link
                                :href="showList(claim.roster_slug).url"
                                class="group text-fg hover:text-fg focus-visible:outline-focus-ring flex items-center gap-3 rounded-sm py-3 no-underline focus-visible:outline-2 focus-visible:outline-offset-2"
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
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span
                                        class="group-hover:text-primary font-semibold break-words"
                                        >{{ claim.item_name }}</span
                                    >
                                    <span
                                        class="text-fg-muted text-sm break-words"
                                    >
                                        {{
                                            t('dash.claims.for', {
                                                list: claim.roster_title,
                                            })
                                        }}<template v-if="claim.date">
                                            ·
                                            {{
                                                shortDate(claim.date)
                                            }}</template
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
                    <EmptyState
                        v-else
                        :title="t('dash.claims.empty.title')"
                        :text="t('dash.claims.empty.text')"
                    />
                </section>
            </div>

            <section
                class="flex flex-col gap-4"
                aria-labelledby="groups-title"
                data-feature="F-03"
            >
                <div :class="sectionHead">
                    <h2 id="groups-title" :class="sectionTitle">
                        {{ t('dash.groups') }}
                        <span v-if="groups.length" :class="countClass">{{
                            groups.length
                        }}</span>
                    </h2>
                    <Button as-child variant="secondary" size="sm">
                        <Link :href="createGroup().url" class="no-underline"
                            ><Plus aria-hidden="true" />
                            {{ t('dash.groups.new') }}</Link
                        >
                    </Button>
                </div>
                <ul
                    v-if="groups.length"
                    class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,280px),1fr))] gap-3"
                >
                    <li v-for="group in groups" :key="group.slug">
                        <Link
                            :href="showGroup(group.slug).url"
                            :class="[cardClass, 'h-full flex-col gap-3.5 p-5']"
                        >
                            <span
                                class="flex items-start justify-between gap-3"
                            >
                                <span
                                    class="font-display min-w-0 text-lg font-[640] break-words"
                                    >{{ group.name }}</span
                                >
                                <Badge
                                    v-if="group.role !== 'member'"
                                    variant="outline"
                                    >{{ t(roleLabel[group.role]) }}</Badge
                                >
                            </span>
                            <span
                                class="text-fg-muted flex flex-wrap gap-x-4 gap-y-1 text-sm"
                            >
                                <span
                                    ><b
                                        class="text-fg font-semibold tabular-nums"
                                        >{{ group.member_count }}</b
                                    >
                                    {{ t('word.members') }}</span
                                >
                                <span v-if="group.open_poll_count"
                                    ><b
                                        class="text-fg font-semibold tabular-nums"
                                        >{{ group.open_poll_count }}</b
                                    >
                                    {{ t('word.openPolls') }}</span
                                >
                                <span v-if="group.upcoming_event_count"
                                    ><b
                                        class="text-fg font-semibold tabular-nums"
                                        >{{ group.upcoming_event_count }}</b
                                    >
                                    {{ t('word.upcoming') }}</span
                                >
                            </span>
                        </Link>
                    </li>
                </ul>
                <EmptyState
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
                </EmptyState>
            </section>

            <section
                class="flex flex-col gap-4"
                aria-labelledby="lists-title"
                data-feature="F-13"
            >
                <div :class="sectionHead">
                    <h2 id="lists-title" :class="sectionTitle">
                        {{ t('dash.lists') }}
                        <span v-if="lists.length" :class="countClass">{{
                            lists.length
                        }}</span>
                    </h2>
                    <Button as-child variant="secondary" size="sm">
                        <Link :href="createList().url" class="no-underline"
                            ><Plus aria-hidden="true" />
                            {{ t('dash.lists.new') }}</Link
                        >
                    </Button>
                </div>
                <ul
                    v-if="lists.length"
                    class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,380px),1fr))] gap-3"
                >
                    <li v-for="list in lists" :key="list.slug">
                        <Link
                            :href="showList(list.slug).url"
                            :class="[
                                cardClass,
                                'h-full items-center gap-3.5 px-[18px] py-4',
                            ]"
                        >
                            <span
                                class="bg-surface-muted text-fg-muted grid size-10 flex-none place-items-center rounded-xl"
                            >
                                <ListChecks class="size-5" aria-hidden="true" />
                            </span>
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="font-semibold break-words">{{
                                    list.title
                                }}</span>
                                <span class="text-fg-muted text-sm">
                                    <template v-if="list.date"
                                        >{{ shortDate(list.date) }} · </template
                                    >{{
                                        t('dash.lists.claimed', {
                                            done: list.claimed_count,
                                            total: list.item_count,
                                        })
                                    }}
                                </span>
                                <span
                                    class="flex flex-wrap items-center gap-2.5"
                                >
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
                        </Link>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    :title="t('dash.lists.empty.title')"
                    :text="t('dash.lists.empty.text')"
                >
                    <Button as-child variant="secondary">
                        <Link :href="createList().url" class="no-underline"
                            ><Plus aria-hidden="true" />
                            {{ t('dash.lists.new') }}</Link
                        >
                    </Button>
                </EmptyState>
            </section>
        </template>
    </div>
</template>
