<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useEcho, useEchoNotification } from '@laravel/echo-vue';
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
import { computed, onBeforeUnmount } from 'vue';
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
import SectionBox from '@/components/app/section-box.vue';
import SectionBoxItem from '@/components/app/section-box-item.vue';
import { formatDate, toIso } from '@/components/app/poll/dates';
import ProgressBar from '@/components/app/progress-bar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatAmount, relativeDay } from '@/lib/roster-items';

defineOptions({ layout: AppLayout });

// Mirrors App.Data.DashboardData; the SFC compiler only resolves imported or local types, not the global namespace
const props = defineProps<{
    waiting: App.Data.DashboardWaitingData[];
    upcoming: App.Data.DashboardEventData[];
    claims: App.Data.DashboardClaimData[];
    groups: App.Data.DashboardGroupData[];
    lists: App.Data.DashboardListData[];
    counts: App.Data.DashboardCountsData;
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
                'groups',
                'lists',
                'counts',
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

    const accepted = tc('dash.waiting.eventMeta', item.accepted_count ?? 0, {
        accepted: item.accepted_count ?? 0,
    });

    return `${item.group_name} · ${day}${item.starts_at ? `, ${item.starts_at}` : ''} · ${accepted}`;
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
            <Button as-child size="lg" variant="secondary">
                <Link :href="createList().url" class="no-underline">{{
                    t('dash.lists.new')
                }}</Link>
            </Button>
        </EmptyState>

        <template v-else>
            <!-- The one block that asks for action keeps the only strong surface on the page -->
            <section
                v-if="waiting.length"
                aria-labelledby="waiting-title"
                class="bg-primary-soft text-primary-soft-fg flex flex-col gap-3.5 rounded-(--card-radius) p-[22px] min-[720px]:px-7 min-[720px]:py-[26px]"
            >
                <h2
                    id="waiting-title"
                    class="text-h2 text-primary-soft-fg flex items-center gap-2.5"
                >
                    {{ t('dash.waiting') }}
                    <span
                        class="bg-primary text-primary-fg inline-grid h-7 min-w-7 place-items-center rounded-full px-2 font-sans text-sm font-bold tabular-nums"
                        >{{ waiting.length }}</span
                    >
                </h2>
                <ul
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
            </section>

            <SectionBox v-else id="waiting-title" :title="t('dash.waiting')">
                <EmptyState
                    :icon="Check"
                    :title="t('dash.waiting.done.title')"
                    :text="t('dash.waiting.done.text')"
                />
            </SectionBox>

            <div
                class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,420px),1fr))] gap-x-12 gap-y-10"
            >
                <SectionBox
                    id="upcoming-title"
                    :title="t('dash.upcoming')"
                    :count="upcoming.length"
                    data-feature="F-10"
                >
                    <ul v-if="upcoming.length" class="flex flex-col gap-3">
                        <li v-for="event in upcoming" :key="event.slug">
                            <SectionBoxItem
                                :as="Link"
                                :href="showEvent(event.slug).url"
                                class="flex items-start gap-4 p-[18px]"
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
                            </SectionBoxItem>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        :title="t('dash.upcoming.empty.title')"
                        :text="t('dash.upcoming.empty.text')"
                    />
                </SectionBox>

                <SectionBox
                    id="claims-title"
                    :title="t('dash.claims')"
                    :count="claims.length"
                    data-feature="F-15"
                >
                    <ul
                        v-if="claims.length"
                        class="box divide-border flex min-w-0 flex-col divide-y px-6 py-1.5"
                    >
                        <li
                            v-for="(claim, index) in claims"
                            :key="`${claim.roster_slug}-${index}`"
                        >
                            <Link
                                :href="showList(claim.roster_slug).url"
                                class="group text-fg hover:text-fg flex items-center gap-3 rounded-sm py-3 no-underline"
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
                </SectionBox>
            </div>

            <SectionBox
                id="groups-title"
                :title="t('dash.groups')"
                :count="groups.length"
                data-feature="F-03"
            >
                <template #action>
                    <Button as-child variant="secondary" size="sm">
                        <Link :href="createGroup().url" class="no-underline"
                            ><Plus aria-hidden="true" />
                            {{ t('dash.groups.new') }}</Link
                        >
                    </Button>
                </template>
                <ul
                    v-if="groups.length"
                    class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,260px),1fr))] gap-4"
                >
                    <li v-for="group in groups" :key="group.slug">
                        <SectionBoxItem
                            :as="Link"
                            :href="showGroup(group.slug).url"
                            class="flex h-full flex-col gap-3.5 p-5"
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
                        </SectionBoxItem>
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
            </SectionBox>

            <SectionBox
                id="lists-title"
                :title="t('dash.lists')"
                :count="lists.length"
                data-feature="F-13"
            >
                <template #action>
                    <Button as-child variant="secondary" size="sm">
                        <Link :href="createList().url" class="no-underline"
                            ><Plus aria-hidden="true" />
                            {{ t('dash.lists.new') }}</Link
                        >
                    </Button>
                </template>
                <ul
                    v-if="lists.length"
                    class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,300px),1fr))] gap-4"
                >
                    <li v-for="list in lists" :key="list.slug">
                        <SectionBoxItem
                            :as="Link"
                            :href="showList(list.slug).url"
                            class="flex h-full items-center gap-3.5"
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
                        </SectionBoxItem>
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
            </SectionBox>
        </template>
    </div>
</template>
