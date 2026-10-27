<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChevronRight,
    Grid3x3,
    ListChecks,
    Plus,
    Settings,
    Vote,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    create as createEvent,
    show as showEvent,
} from '@/actions/App/Http/Controllers/EventController';
import {
    edit as editGroup,
    invite as inviteMembers,
} from '@/actions/App/Http/Controllers/GroupController';
import { store as storeInviteLink } from '@/actions/App/Http/Controllers/GroupInviteLinkController';
import {
    create as createPoll,
    show as showPoll,
} from '@/actions/App/Http/Controllers/PollController';
import {
    create as createList,
    show as showList,
} from '@/actions/App/Http/Controllers/RosterController';
import DateBlock from '@/components/app/date-block.vue';
import EmptyState from '@/components/app/empty-state.vue';
import InviteField from '@/components/app/invite-field.vue';
import type { InviteSelection } from '@/components/app/invite-field.vue';
import { formatDate } from '@/components/app/poll/dates';
import ProgressBar from '@/components/app/progress-bar.vue';
import UserAvatar from '@/components/app/user-avatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

defineOptions({ layout: AppLayout });

const props = defineProps<{ page: App.Data.GroupShowData }>();

const { t, locale } = useI18n();
const currentUserId = computed(() => usePage().props.auth.user?.id ?? null);

const openPolls = computed(() =>
    props.page.polls.filter((poll) => !poll.is_closed),
);
const closedPolls = computed(() =>
    props.page.polls.filter((poll) => poll.is_closed),
);
const closedOpen = ref(false);
// Closed and fixed polls collapse behind one toggle below the open ones
const pollSections = computed(() => [
    { id: 'open-polls', closed: false, visible: true, polls: openPolls.value },
    {
        id: 'closed-polls',
        closed: true,
        visible: closedOpen.value,
        polls: closedPolls.value,
    },
]);
const upcomingEvents = computed(() =>
    props.page.events.filter((event) => !event.is_past),
);
const pastEvents = computed(() =>
    props.page.events.filter((event) => event.is_past),
);

// Section 8: a group without other members shows the invite form prominently
const alone = computed(() => props.page.members.length <= 1);
const memberIds = computed(() => props.page.members.map((member) => member.id));
const memberEmails = computed(() =>
    props.page.members
        .map((member) => member.email)
        .filter((email): email is string => !!email),
);

const roleLabel: Record<App.Enums.GroupRole, string> = {
    owner: 'group.member.owner',
    admin: 'group.member.admin',
    member: 'group.member.member',
};

const ownStatus: Record<
    App.Enums.ParticipationStatus,
    { label: string; class: string }
> = {
    accepted: {
        label: 'group.events.youAccepted',
        class: 'bg-success-soft text-success',
    },
    pending: {
        label: 'group.events.youPending',
        class: 'bg-warning-soft text-warning',
    },
    declined: {
        label: 'group.events.youDeclined',
        class: 'bg-danger-soft text-danger',
    },
};

function pollStatus(poll: App.Data.GroupPollData): {
    label: string;
    class: string;
} {
    if (!poll.is_closed) {
        return {
            label: t('group.polls.open'),
            class: 'bg-primary-soft text-primary-soft-fg',
        };
    }

    if (poll.is_fixed) {
        return {
            label: t('group.polls.fixed'),
            class: 'bg-success-soft text-success',
        };
    }

    return {
        label: t('group.polls.closed'),
        class: 'bg-surface-muted text-fg-muted',
    };
}

function eventWhen(event: App.Data.GroupEventData): string {
    const day = formatDate(event.date, locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });

    return event.starts_at ? `${day}, ${event.starts_at}` : day;
}

const invites = ref<InviteSelection[]>([]);
const inviteForm = useForm({
    invites: [] as { email: string; name: string | null }[],
});
const inviteError = computed(() => {
    const key = Object.keys(inviteForm.errors).find((name) =>
        name.startsWith('invites'),
    );

    return key ? (inviteForm.errors as Record<string, string>)[key] : null;
});
const inviteField = ref<InstanceType<typeof InviteField> | null>(null);

function sendInvites(): void {
    if (!invites.value.length) {
        return;
    }

    inviteForm.invites = invites.value.map((selection) => ({
        email: selection.email,
        name: selection.name ?? null,
    }));
    inviteForm.post(inviteMembers(props.page.slug).url, {
        preserveScroll: true,
        onSuccess: () => {
            invites.value = [];
            inviteForm.reset();
        },
    });
}

// "Copy invite link" creates the link on first use, then copies it (F-04)
function createLink(): void {
    router.post(
        storeInviteLink(props.page.slug).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                void inviteField.value?.copyLink(
                    props.page.invite_link?.url ?? null,
                );
            },
            onError: (errors) => {
                toast.error(
                    Object.values(errors)[0] ?? t('group.link.copyFailed'),
                );
            },
        },
    );
}

function copyFailed(url: string): void {
    toast.error(t('group.link.copyFailed'), {
        description: url,
        duration: 10000,
    });
}

const buttonClass = 'h-(--control-height) px-4';
const rowClass =
    'box flex items-center gap-3.5 px-4 py-3.5 transition-colors hover:border-border-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring sm:px-[22px] max-[560px]:flex-wrap';
</script>

<template>
    <Head :title="page.name" />

    <div class="flex flex-col gap-10">
        <header class="flex flex-col gap-[18px]" data-feature="F-03">
            <Link
                :href="dashboard().url"
                class="text-fg-muted hover:text-fg inline-flex w-fit items-center gap-1.5 rounded-sm text-sm font-semibold no-underline"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('nav.backToDashboard') }}
            </Link>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 flex-col gap-2">
                    <h1
                        class="flex flex-wrap items-center gap-3.5 text-3xl break-words"
                    >
                        {{ page.name }}
                        <Badge
                            v-if="page.own_role !== 'member'"
                            variant="outline"
                            class="font-sans"
                            >{{ t(roleLabel[page.own_role]) }}</Badge
                        >
                    </h1>
                    <p
                        class="text-fg-muted flex flex-wrap gap-x-[18px] gap-y-2.5 text-[15px]"
                    >
                        <span
                            ><b class="text-fg font-semibold tabular-nums">{{
                                page.members.length
                            }}</b>
                            {{ t('word.members') }}</span
                        >
                        <span v-if="openPolls.length"
                            ><b class="text-fg font-semibold tabular-nums">{{
                                openPolls.length
                            }}</b>
                            {{ t('word.openPolls') }}</span
                        >
                        <span v-if="upcomingEvents.length"
                            ><b class="text-fg font-semibold tabular-nums">{{
                                upcomingEvents.length
                            }}</b>
                            {{ t('word.upcoming') }}</span
                        >
                    </p>
                </div>
                <Button
                    v-if="page.permissions.can_manage_settings"
                    as-child
                    variant="ghost"
                    data-feature="F-03"
                >
                    <Link :href="editGroup(page.slug).url">
                        <Settings aria-hidden="true" />
                        {{ t('group.settings') }}
                    </Link>
                </Button>
            </div>
            <div class="flex flex-wrap gap-2.5">
                <Button
                    as-child
                    size="lg"
                    :class="buttonClass"
                    data-feature="F-06"
                >
                    <Link :href="createPoll(page.slug).url">
                        <Vote aria-hidden="true" /> {{ t('group.new.poll') }}
                    </Link>
                </Button>
                <Button
                    as-child
                    size="lg"
                    variant="secondary"
                    :class="buttonClass"
                    data-feature="F-10"
                >
                    <Link :href="createEvent(page.slug).url">
                        <CalendarDays aria-hidden="true" />
                        {{ t('group.new.event') }}
                    </Link>
                </Button>
                <Button
                    as-child
                    size="lg"
                    variant="secondary"
                    :class="buttonClass"
                    data-feature="F-13"
                >
                    <Link
                        :href="createList({ query: { group: page.slug } }).url"
                    >
                        <ListChecks aria-hidden="true" />
                        {{ t('group.new.list') }}
                    </Link>
                </Button>
            </div>
        </header>

        <div
            class="grid gap-10 min-[960px]:grid-cols-[minmax(0,1fr)_320px] min-[960px]:items-start min-[960px]:gap-12"
        >
            <div class="flex min-w-0 flex-col gap-10">
                <section
                    v-if="alone && page.permissions.can_invite"
                    class="box flex flex-col gap-4 p-5 sm:p-7"
                    aria-labelledby="alone-title"
                    data-feature="F-04"
                >
                    <div class="flex flex-col gap-1.5">
                        <h2 id="alone-title" class="text-xl">
                            {{ t('group.alone.title') }}
                        </h2>
                        <p class="text-fg-muted max-w-[60ch]">
                            {{ t('group.alone.text') }}
                        </p>
                    </div>
                    <InviteField
                        id="group-invite"
                        ref="inviteField"
                        v-model="invites"
                        :label="t('group.invite.title')"
                        :hint="t('group.invite.hint')"
                        :error="inviteError"
                        :submit-label="t('group.invite.button')"
                        :friends="page.friends"
                        :member-ids="memberIds"
                        :member-emails="memberEmails"
                        :link="page.invite_link !== null"
                        :link-url="page.invite_link?.url ?? null"
                        :disabled="inviteForm.processing"
                        @submit="sendInvites"
                        @create-link="createLink"
                        @copy-failed="copyFailed"
                    />
                </section>

                <section
                    class="flex flex-col gap-4"
                    aria-labelledby="polls-title"
                    data-feature="F-06"
                >
                    <h2
                        id="polls-title"
                        class="flex items-center gap-2.5 text-xl"
                    >
                        {{ t('group.polls') }}
                        <span
                            v-if="page.polls.length"
                            class="text-fg-soft font-sans text-sm font-semibold tabular-nums"
                            >{{ page.polls.length }}</span
                        >
                    </h2>
                    <div v-if="page.polls.length" class="flex flex-col gap-2.5">
                        <template
                            v-for="section in pollSections"
                            :key="section.id"
                        >
                            <button
                                v-if="section.closed && section.polls.length"
                                type="button"
                                class="text-fg-muted hover:text-fg inline-flex w-fit items-center gap-1.5 rounded-sm py-1 text-sm font-semibold"
                                :aria-expanded="closedOpen"
                                aria-controls="closed-polls"
                                data-feature="F-08"
                                @click="closedOpen = !closedOpen"
                            >
                                <ChevronRight
                                    class="size-4 transition-transform motion-reduce:transition-none"
                                    :class="closedOpen && 'rotate-90'"
                                    aria-hidden="true"
                                />
                                {{
                                    t('group.polls.closedSection', {
                                        n: closedPolls.length,
                                    })
                                }}
                            </button>
                            <ul
                                v-if="section.visible && section.polls.length"
                                :id="section.id"
                                class="flex flex-col gap-2.5"
                            >
                                <li
                                    v-for="poll in section.polls"
                                    :key="poll.slug"
                                >
                                    <Link
                                        :href="showPoll(poll.slug).url"
                                        :class="rowClass"
                                        :data-feature="
                                            poll.follow_up_event_title
                                                ? 'F-12'
                                                : poll.type === 'attendance'
                                                  ? 'F-07'
                                                  : 'F-06'
                                        "
                                    >
                                        <span
                                            class="grid size-11 flex-none place-items-center rounded-xl"
                                            :class="
                                                poll.is_closed
                                                    ? 'bg-surface-muted text-fg-muted'
                                                    : 'bg-primary-soft text-primary-soft-fg'
                                            "
                                        >
                                            <Grid3x3
                                                v-if="
                                                    poll.type === 'attendance'
                                                "
                                                class="size-[22px]"
                                                aria-hidden="true"
                                            />
                                            <Vote
                                                v-else
                                                class="size-[22px]"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span
                                            class="flex min-w-0 flex-1 flex-col gap-1"
                                        >
                                            <span
                                                class="font-display text-[17px] leading-tight font-[640] break-words"
                                                >{{ poll.title }}</span
                                            >
                                            <span
                                                class="text-fg-muted flex flex-wrap gap-x-3 gap-y-1.5 text-sm"
                                            >
                                                <span>{{
                                                    poll.type === 'attendance'
                                                        ? t(
                                                              'group.polls.attendance',
                                                          )
                                                        : t(
                                                              'group.polls.dateFinder',
                                                          )
                                                }}</span>
                                                <span>{{
                                                    t('group.polls.options', {
                                                        n: poll.option_count,
                                                    })
                                                }}</span>
                                                <Badge
                                                    v-if="
                                                        poll.follow_up_event_title
                                                    "
                                                    class="bg-primary-soft text-primary-soft-fg h-auto font-normal whitespace-normal"
                                                    >{{
                                                        t(
                                                            'group.polls.followUp',
                                                            {
                                                                event: poll.follow_up_event_title,
                                                            },
                                                        )
                                                    }}</Badge
                                                >
                                            </span>
                                        </span>
                                        <span
                                            class="flex flex-none items-center gap-3 max-[560px]:basis-full max-[560px]:pl-[58px]"
                                        >
                                            <span
                                                v-if="!poll.is_closed"
                                                class="text-fg-muted inline-flex items-center gap-1.5 text-sm tabular-nums"
                                            >
                                                <ProgressBar
                                                    class="w-16 flex-none"
                                                    :done="poll.answered_count"
                                                    :total="poll.audience_count"
                                                    :label="
                                                        t(
                                                            'group.polls.responses',
                                                            {
                                                                n: poll.answered_count,
                                                                total: poll.audience_count,
                                                            },
                                                        )
                                                    "
                                                />
                                                {{ poll.answered_count }}/{{
                                                    poll.audience_count
                                                }}
                                            </span>
                                            <Badge
                                                :class="pollStatus(poll).class"
                                                >{{
                                                    pollStatus(poll).label
                                                }}</Badge
                                            >
                                        </span>
                                    </Link>
                                </li>
                            </ul>
                        </template>
                    </div>
                    <EmptyState
                        v-else
                        :title="t('group.polls.empty.title')"
                        :text="t('group.polls.empty.text')"
                    >
                        <Button as-child size="lg" :class="buttonClass">
                            <Link :href="createPoll(page.slug).url">
                                <Plus aria-hidden="true" />
                                {{ t('group.new.poll') }}
                            </Link>
                        </Button>
                    </EmptyState>
                </section>

                <section
                    class="flex flex-col gap-4"
                    aria-labelledby="events-title"
                    data-feature="F-10"
                >
                    <h2
                        id="events-title"
                        class="flex items-center gap-2.5 text-xl"
                    >
                        {{ t('group.events') }}
                        <span
                            v-if="page.events.length"
                            class="text-fg-soft font-sans text-sm font-semibold tabular-nums"
                            >{{ page.events.length }}</span
                        >
                    </h2>
                    <template v-if="page.events.length">
                        <div
                            v-for="block in [
                                {
                                    key: 'upcoming',
                                    label: t('group.events.upcoming'),
                                    events: upcomingEvents,
                                },
                                {
                                    key: 'past',
                                    label: t('group.events.past'),
                                    events: pastEvents,
                                },
                            ].filter((entry) => entry.events.length)"
                            :key="block.key"
                            class="flex flex-col gap-2.5"
                        >
                            <h3
                                :id="`events-${block.key}`"
                                class="text-fg-soft pl-0.5 font-sans text-[13px] font-semibold tracking-normal"
                            >
                                {{ block.label }}
                            </h3>
                            <ul
                                class="flex flex-col gap-2.5"
                                :aria-labelledby="`events-${block.key}`"
                            >
                                <li
                                    v-for="event in block.events"
                                    :key="event.slug"
                                >
                                    <Link
                                        :href="showEvent(event.slug).url"
                                        :class="[
                                            rowClass,
                                            event.is_past && 'opacity-75',
                                        ]"
                                    >
                                        <DateBlock
                                            :date="event.date"
                                            :fixed="
                                                event.is_from_poll &&
                                                !event.is_past
                                            "
                                        />
                                        <span
                                            class="flex min-w-0 flex-1 flex-col gap-1"
                                        >
                                            <span
                                                class="font-display flex flex-wrap items-center gap-2 text-[17px] leading-tight font-[640] break-words"
                                            >
                                                {{ event.title }}
                                                <Badge
                                                    v-if="event.is_from_poll"
                                                    variant="outline"
                                                    class="font-sans"
                                                    data-feature="F-09"
                                                    >{{
                                                        t(
                                                            'group.events.fromPoll',
                                                        )
                                                    }}</Badge
                                                >
                                            </span>
                                            <span
                                                class="text-fg-muted flex flex-wrap gap-x-3 gap-y-1.5 text-sm"
                                            >
                                                <span>{{
                                                    eventWhen(event)
                                                }}</span>
                                                <span v-if="event.location">{{
                                                    event.location
                                                }}</span>
                                            </span>
                                            <span
                                                class="text-fg-muted flex flex-wrap gap-x-3 gap-y-1.5 text-sm"
                                            >
                                                <span>{{
                                                    t('group.events.accepted', {
                                                        n: event.accepted_count,
                                                    })
                                                }}</span>
                                                <span
                                                    v-if="
                                                        event.pending_count &&
                                                        !event.is_past
                                                    "
                                                    >{{
                                                        t(
                                                            'group.events.pending',
                                                            {
                                                                n: event.pending_count,
                                                            },
                                                        )
                                                    }}</span
                                                >
                                            </span>
                                        </span>
                                        <span
                                            v-if="
                                                event.own_status &&
                                                !event.is_past
                                            "
                                            class="flex flex-none items-center max-[560px]:basis-full max-[560px]:pl-[68px]"
                                            data-feature="F-11"
                                        >
                                            <Badge
                                                :class="
                                                    ownStatus[event.own_status]
                                                        .class
                                                "
                                                >{{
                                                    t(
                                                        ownStatus[
                                                            event.own_status
                                                        ].label,
                                                    )
                                                }}</Badge
                                            >
                                        </span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </template>
                    <EmptyState
                        v-else
                        :title="t('group.events.empty.title')"
                        :text="t('group.events.empty.text')"
                    >
                        <Button
                            as-child
                            size="lg"
                            variant="secondary"
                            :class="buttonClass"
                        >
                            <Link :href="createEvent(page.slug).url">
                                <Plus aria-hidden="true" />
                                {{ t('group.new.event') }}
                            </Link>
                        </Button>
                    </EmptyState>
                </section>

                <section
                    class="flex flex-col gap-4"
                    aria-labelledby="lists-title"
                    data-feature="F-13"
                >
                    <div
                        class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
                    >
                        <h2
                            id="lists-title"
                            class="flex items-center gap-2.5 text-xl"
                        >
                            {{ t('group.lists') }}
                            <span
                                v-if="page.lists.length"
                                class="text-fg-soft font-sans text-sm font-semibold tabular-nums"
                                >{{ page.lists.length }}</span
                            >
                        </h2>
                        <p class="text-fg-soft text-sm">
                            {{ t('group.lists.eventLists') }}
                        </p>
                    </div>
                    <ul
                        v-if="page.lists.length"
                        class="grid gap-2.5 sm:grid-cols-2"
                    >
                        <li v-for="list in page.lists" :key="list.slug">
                            <Link
                                :href="showList(list.slug).url"
                                :class="[rowClass, 'h-full items-start']"
                            >
                                <span
                                    class="bg-primary-soft text-primary-soft-fg grid size-10 flex-none place-items-center rounded-xl"
                                >
                                    <ListChecks
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span
                                    class="flex min-w-0 flex-1 flex-col gap-1"
                                >
                                    <span
                                        class="font-display text-[17px] leading-tight font-[640] break-words"
                                        >{{ list.title }}</span
                                    >
                                    <span class="text-fg-muted text-sm">
                                        <template v-if="list.date"
                                            >{{
                                                formatDate(list.date, locale, {
                                                    day: 'numeric',
                                                    month: 'short',
                                                    year: 'numeric',
                                                })
                                            }}
                                            ·
                                        </template>
                                        {{
                                            t('dash.lists.claimed', {
                                                done: list.claimed_count,
                                                total: list.item_count,
                                            })
                                        }}
                                    </span>
                                    <ProgressBar
                                        class="mt-1"
                                        :done="list.claimed_count"
                                        :total="list.item_count"
                                        :label="
                                            t('dash.lists.claimed', {
                                                done: list.claimed_count,
                                                total: list.item_count,
                                            })
                                        "
                                    />
                                </span>
                            </Link>
                        </li>
                    </ul>
                    <EmptyState
                        v-else
                        :title="t('group.lists.empty.title')"
                        :text="t('group.lists.empty.text')"
                    >
                        <Button
                            as-child
                            size="lg"
                            variant="secondary"
                            :class="buttonClass"
                        >
                            <Link
                                :href="
                                    createList({ query: { group: page.slug } })
                                        .url
                                "
                            >
                                <Plus aria-hidden="true" />
                                {{ t('group.new.list') }}
                            </Link>
                        </Button>
                    </EmptyState>
                </section>
            </div>

            <aside
                class="flex min-w-0 flex-col gap-5 min-[960px]:sticky min-[960px]:top-[calc(64px+24px+env(safe-area-inset-top,0px))]"
                aria-labelledby="members-title"
            >
                <section
                    class="box flex flex-col gap-4 p-5"
                    data-feature="F-04"
                >
                    <h2
                        id="members-title"
                        class="flex items-center gap-2.5 text-xl"
                    >
                        {{ t('group.members') }}
                        <span
                            class="text-fg-soft font-sans text-sm font-semibold tabular-nums"
                            >{{ page.members.length }}</span
                        >
                    </h2>
                    <ul class="flex flex-col">
                        <li
                            v-for="member in page.members"
                            :key="member.id"
                            class="flex items-center gap-3 py-2"
                        >
                            <UserAvatar
                                :name="member.name"
                                :email="member.email"
                            />
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span
                                    class="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold"
                                >
                                    <span
                                        v-if="member.name"
                                        class="break-words"
                                        >{{ member.name }}</span
                                    >
                                    <span
                                        v-else
                                        class="text-fg-soft font-normal"
                                        >{{ t('common.noName') }}</span
                                    >
                                    <span
                                        v-if="member.id === currentUserId"
                                        class="text-fg-soft text-sm font-normal"
                                        >({{ t('group.member.you') }})</span
                                    >
                                    <Badge
                                        v-if="member.is_pending"
                                        class="bg-warning-soft text-warning"
                                        >{{ t('group.member.pending') }}</Badge
                                    >
                                    <Badge
                                        v-if="member.joined_via_link"
                                        class="bg-info-soft text-info"
                                        data-feature="F-04"
                                        >{{ t('group.member.viaLink') }}</Badge
                                    >
                                </span>
                                <span
                                    v-if="member.email"
                                    class="text-fg-soft truncate text-[13px]"
                                    >{{ member.email }}</span
                                >
                            </span>
                            <span
                                v-if="member.role !== 'member'"
                                class="text-fg-muted flex-none text-[13px]"
                                >{{ t(roleLabel[member.role]) }}</span
                            >
                        </li>
                    </ul>

                    <div
                        v-if="page.permissions.can_invite && !alone"
                        class="border-border border-t pt-3"
                    >
                        <InviteField
                            id="group-invite"
                            ref="inviteField"
                            v-model="invites"
                            :label="t('group.invite.title')"
                            :hint="t('group.invite.hint')"
                            :error="inviteError"
                            :submit-label="t('group.invite.button')"
                            :friends="page.friends"
                            :member-ids="memberIds"
                            :member-emails="memberEmails"
                            :link="page.invite_link !== null"
                            :link-url="page.invite_link?.url ?? null"
                            :disabled="inviteForm.processing"
                            @submit="sendInvites"
                            @create-link="createLink"
                            @copy-failed="copyFailed"
                        />
                    </div>
                    <p
                        v-else-if="!page.permissions.can_invite"
                        class="text-fg-soft text-sm"
                    >
                        {{ t('group.memberNotice') }}
                    </p>
                </section>
            </aside>
        </div>
    </div>
</template>
