<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    ArrowLeft,
    CalendarDays,
    Check,
    ChevronRight,
    Clock,
    Grid3x3,
    ListChecks,
    MapPin,
    MessageSquare,
    Pencil,
    Plus,
    Trash2,
    Users,
    Vote,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyComment,
    store as storeComment,
} from '@/actions/App/Http/Controllers/EventCommentController';
import {
    destroy as destroyEvent,
    edit as editEvent,
    show as showEvent,
} from '@/actions/App/Http/Controllers/EventController';
import { create as createFollowUpPoll } from '@/actions/App/Http/Controllers/EventFollowUpPollController';
import {
    destroy as removeParticipant,
    update as updateOwnParticipation,
    updateFor as updateParticipation,
} from '@/actions/App/Http/Controllers/EventParticipationController';
import { show as showGroup } from '@/actions/App/Http/Controllers/GroupController';
import { show as showPoll } from '@/actions/App/Http/Controllers/PollController';
import {
    create as createList,
    show as showList,
} from '@/actions/App/Http/Controllers/RosterController';
import CommentThread from '@/components/app/comment-thread.vue';
import type { ThreadComment } from '@/components/app/comment-thread.vue';
import DateBlock from '@/components/app/date-block.vue';
import EmptyState from '@/components/app/empty-state.vue';
import { formatDate } from '@/components/app/poll/dates';
import ProgressBar from '@/components/app/progress-bar.vue';
import UserAvatar from '@/components/app/user-avatar.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ page: App.Data.EventShowData }>();

const { t, locale } = useI18n();
const currentUserId = computed(() => usePage().props.auth.user?.id ?? null);

const event = computed(() => props.page.event);
const permissions = computed(() => props.page.permissions);
const isChild = computed(() => event.value.parent_event !== null);
// Only "accepted" and "pending" count as participants; everyone else sees the key details (F-11)
const isParticipant = computed(
    () =>
        props.page.own_status === 'accepted' ||
        props.page.own_status === 'pending',
);

const statusLabel: Record<App.Enums.ParticipationStatus, string> = {
    accepted: 'event.accepted',
    pending: 'event.pending',
    declined: 'event.declined',
};

const ownBadge = computed(() => {
    if (props.page.own_status === 'accepted') {
        return { label: t('group.events.youAccepted'), class: 'bg-success-soft text-success' };
    }

    if (props.page.own_status === 'declined') {
        return { label: t('group.events.youDeclined'), class: 'bg-danger-soft text-danger' };
    }

    return { label: t('group.events.youPending'), class: 'bg-warning-soft text-warning' };
});

const childBadge: Record<App.Enums.ParticipationStatus, { label: string; class: string }> = {
    accepted: { label: 'group.events.youAccepted', class: 'bg-success-soft text-success' },
    pending: { label: 'group.events.youPending', class: 'bg-warning-soft text-warning' },
    declined: { label: 'group.events.youDeclined', class: 'bg-danger-soft text-danger' },
};

function daysUntil(date: string): number {
    const [year, month, day] = date.split('-').map(Number);
    const today = new Date();

    return Math.round(
        (Date.UTC(year, month - 1, day) -
            Date.UTC(today.getFullYear(), today.getMonth(), today.getDate())) /
            86_400_000,
    );
}

const relativeDay = computed(() => {
    const days = daysUntil(event.value.date);

    if (days === 0) {
        return t('common.today');
    }

    if (days === 1) {
        return t('common.tomorrow');
    }

    return t('common.inDays', { n: days });
});

const timeLabel = computed(() => {
    if (!event.value.starts_at) {
        return '';
    }

    if (!event.value.ends_at) {
        return event.value.starts_at;
    }

    return t('event.timeRange', { start: event.value.starts_at, end: event.value.ends_at });
});

const endLabel = computed(() => {
    if (!event.value.end_date || event.value.end_date === event.value.date) {
        return '';
    }

    return formatDate(event.value.end_date, locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
});

function longDate(date: string): string {
    return formatDate(date, locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function childWhen(child: App.Data.GroupEventData): string {
    const day = formatDate(child.date, locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });

    return child.starts_at ? `${day}, ${child.starts_at}` : day;
}

function personName(name: string | null): string {
    return name || t('common.noName');
}

// Participation (F-11)

const managing = ref(false);
const canManage = computed(
    () =>
        !event.value.is_past &&
        (permissions.value.can_set_status_for_others || permissions.value.can_remove_participants),
);
const canRespond = computed(() => permissions.value.can_respond && !event.value.is_past);

const columns = computed(() => {
    const groups: { status: App.Enums.ParticipationStatus | null; label: string; people: App.Data.ParticipationData[] }[] = (
        ['accepted', 'pending', 'declined'] as const
    ).map((status) => ({
        status,
        label: t(statusLabel[status]),
        people: props.page.participants.filter((person) => person.status === status),
    }));
    const withoutStatus = props.page.participants.filter((person) => person.status === null);

    // People without a status only matter to those who can set one
    if (managing.value && withoutStatus.length) {
        groups.push({ status: null, label: t('event.noStatus'), people: withoutStatus });
    }

    return groups;
});

const responding = ref(false);

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? t('event.notFound'));
}

function respond(status: 'accepted' | 'declined', confirmRelease = false): void {
    if (!canRespond.value) {
        return;
    }

    router.put(
        updateOwnParticipation(event.value.slug).url,
        { status, confirm_release: confirmRelease },
        {
            preserveScroll: true,
            onStart: () => (responding.value = true),
            onFinish: () => (responding.value = false),
            onError: showError,
        },
    );
}

const declineOpen = ref(false);

// A decline with claims asks first, because it releases them (F-11, section 8)
function decline(): void {
    if (props.page.own_status === 'declined') {
        return;
    }

    if (props.page.own_claims.length) {
        declineOpen.value = true;

        return;
    }

    respond('declined');
}

function confirmDecline(): void {
    declineOpen.value = false;
    respond('declined', true);
}

function claimLabel(claim: App.Data.EventClaimData): string {
    if (claim.quantity === null) {
        return t('event.task');
    }

    return claim.unit ? `${claim.quantity} ${claim.unit}` : String(claim.quantity);
}

function setStatus(person: App.Data.ParticipationData, status: unknown): void {
    if (status !== 'accepted' && status !== 'pending' && status !== 'declined') {
        return;
    }

    router.put(
        updateParticipation([event.value.slug, person.user_id]).url,
        { status },
        { preserveScroll: true, onError: showError },
    );
}

const removeTarget = ref<App.Data.ParticipationData | null>(null);
const removeOpen = ref(false);

function askRemove(person: App.Data.ParticipationData): void {
    removeTarget.value = person;
    removeOpen.value = true;
}

function confirmRemove(): void {
    if (!removeTarget.value) {
        return;
    }

    router.delete(removeParticipant([event.value.slug, removeTarget.value.user_id]).url, {
        preserveScroll: true,
        onError: showError,
    });
    removeOpen.value = false;
}

// Delete (F-10)

const deleteOpen = ref(false);

function confirmDelete(): void {
    router.delete(destroyEvent(event.value.slug).url, { onError: showError });
    deleteOpen.value = false;
}

// Comments (F-20): collapsed by default

const commentsOpen = ref(false);
const commentForm = useForm({ body: '' });
const threadComments = computed<ThreadComment[]>(() =>
    props.page.comments.map((comment) => ({
        id: comment.id,
        authorName: comment.user_name,
        createdAt: comment.created_at,
        body: comment.body,
        canDelete: comment.can_delete,
    })),
);

function postComment(body: string): void {
    commentForm.body = body;
    commentForm.post(storeComment(event.value.slug).url, {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
}

function deleteComment(id: ThreadComment['id']): void {
    router.delete(destroyComment([event.value.slug, Number(id)]).url, {
        preserveScroll: true,
        onError: showError,
    });
}

// Live updates (F-21): participation, details and comments of this event
useEcho<{ deleted?: boolean }>(
    `event.${props.page.event.id}`,
    ['.event.updated', '.participation.changed', '.comment.added', '.comment.deleted'],
    (payload) => {
        if (payload.deleted) {
            router.visit(showGroup(props.page.event.group_slug).url);

            return;
        }

        router.reload({ only: ['page'] });
    },
);

const buttonClass = 'h-(--control-height) px-4';
const rowClass =
    'box flex items-center gap-3.5 px-4 py-3.5 transition-colors hover:border-border-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring sm:px-[22px] max-[560px]:flex-wrap';
const badgeLinkClass =
    'border-border text-fg-muted hover:border-primary hover:text-primary inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[13px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring';
</script>

<template>
    <Head :title="event.title" />

    <div class="flex flex-col gap-10">
        <header class="flex flex-col gap-[18px]" data-feature="F-10">
            <Link
                :href="showGroup(event.group_slug).url"
                class="text-fg-muted hover:text-fg inline-flex w-fit items-center gap-1.5 rounded-sm text-sm font-semibold"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('event.back', { group: event.group_name }) }}
            </Link>
            <div class="flex flex-wrap items-start gap-x-5 gap-y-4">
                <DateBlock :date="event.date" :fixed="event.origin_poll_slug !== null && !event.is_past" size="lg" />
                <div class="flex min-w-0 flex-[1_1_280px] flex-col gap-2.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge v-if="event.is_past" class="bg-surface-muted text-fg-muted">{{ t('event.past') }}</Badge>
                        <Badge v-else class="bg-primary-soft text-primary-soft-fg">{{ relativeDay }}</Badge>
                        <Link
                            v-if="event.origin_poll_slug"
                            :href="showPoll(event.origin_poll_slug).url"
                            :class="badgeLinkClass"
                            data-feature="F-09"
                        >
                            <Vote class="size-3.5" aria-hidden="true" />
                            {{ t('group.events.fromPoll') }}
                        </Link>
                        <Link
                            v-if="event.parent_event"
                            :href="showEvent(event.parent_event.slug).url"
                            :class="badgeLinkClass"
                            data-feature="F-12"
                        >
                            <CalendarDays class="size-3.5" aria-hidden="true" />
                            {{ t('event.parent', { event: event.parent_event.title }) }}
                        </Link>
                    </div>
                    <h1 class="text-3xl break-words">{{ event.title }}</h1>
                    <p class="text-fg-muted flex flex-wrap gap-x-[18px] gap-y-2 text-[15px]">
                        <span class="inline-flex items-center gap-1.5">
                            <CalendarDays class="text-fg-soft size-4" aria-hidden="true" />
                            {{ longDate(event.date) }}<template v-if="endLabel"> – {{ endLabel }}</template>
                        </span>
                        <span v-if="timeLabel" class="inline-flex items-center gap-1.5">
                            <Clock class="text-fg-soft size-4" aria-hidden="true" />
                            {{ timeLabel }}
                        </span>
                        <span v-if="event.location" class="inline-flex min-w-0 items-center gap-1.5 break-words">
                            <MapPin class="text-fg-soft size-4 flex-none" aria-hidden="true" />
                            {{ event.location }}
                        </span>
                        <Link
                            :href="showGroup(event.group_slug).url"
                            class="text-primary hover:text-primary-hover inline-flex items-center gap-1.5 font-semibold underline decoration-1 underline-offset-[3px]"
                        >
                            <Users class="size-4" aria-hidden="true" />
                            {{ event.group_name }}
                        </Link>
                        <span>{{ t('event.organizer', { name: personName(event.organizer_name) }) }}</span>
                    </p>
                    <p v-if="event.description" class="max-w-[65ch] text-[17px] whitespace-pre-line break-words">
                        {{ event.description }}
                    </p>
                </div>
                <div
                    v-if="permissions.can_edit || permissions.can_delete"
                    class="flex w-full flex-wrap gap-2 min-[720px]:ml-auto min-[720px]:w-auto"
                >
                    <Button v-if="permissions.can_edit" as-child variant="outline">
                        <Link :href="editEvent(event.slug).url">
                            <Pencil aria-hidden="true" />
                            {{ t('event.edit') }}
                        </Link>
                    </Button>
                    <Button v-if="permissions.can_delete" variant="destructive" @click="deleteOpen = true">
                        <Trash2 aria-hidden="true" />
                        {{ t('event.delete') }}
                    </Button>
                </div>
            </div>
        </header>

        <section class="box flex flex-col gap-5 p-5 sm:p-7" aria-labelledby="participation-title" data-feature="F-11">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="participation-title" class="text-xl">{{ t('event.participation') }}</h2>
                <span class="flex flex-wrap items-center gap-2">
                    <Badge :class="ownBadge.class">{{ ownBadge.label }}</Badge>
                    <Button
                        v-if="canManage"
                        variant="ghost"
                        size="sm"
                        :aria-pressed="managing"
                        @click="managing = !managing"
                    >
                        {{ managing ? t('event.manageDone') : t('event.manage') }}
                    </Button>
                </span>
            </div>

            <div class="bg-surface-muted flex flex-wrap items-center justify-between gap-x-4 gap-y-3 rounded-md px-5 py-4">
                <span class="flex-[1_1_200px] font-semibold">{{
                    event.is_past ? t('event.pastNote') : t('event.yourAnswer')
                }}</span>
                <div class="flex flex-wrap gap-2" role="group" :aria-label="t('event.yourAnswer')">
                    <Button
                        :variant="page.own_status === 'accepted' ? 'default' : 'outline'"
                        :class="buttonClass"
                        :aria-pressed="page.own_status === 'accepted'"
                        :disabled="!canRespond || responding"
                        @click="respond('accepted')"
                    >
                        <Check aria-hidden="true" />
                        {{ t('event.accept') }}
                    </Button>
                    <Button
                        variant="destructive"
                        :class="[buttonClass, page.own_status === 'declined' && 'border-danger']"
                        :aria-pressed="page.own_status === 'declined'"
                        :disabled="!canRespond || responding"
                        @click="decline"
                    >
                        <X aria-hidden="true" />
                        {{ t('event.decline') }}
                    </Button>
                </div>
            </div>

            <div
                v-if="page.accepted_count === 0"
                class="bg-info-soft text-info flex items-start gap-3 rounded-md px-4 py-3 text-sm"
                role="status"
            >
                <Users class="mt-0.5 size-5 flex-none" aria-hidden="true" />
                <span>
                    <b class="font-semibold">{{ t('event.noAcceptances.title') }}</b><br />
                    {{ t('event.noAcceptances.text') }}
                </span>
            </div>

            <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] gap-x-6 gap-y-5">
                <div
                    v-for="column in columns"
                    :key="column.status ?? 'none'"
                    class="flex min-w-0 flex-col gap-2.5"
                >
                    <h3 :id="`participants-${column.status ?? 'none'}`" class="flex items-baseline gap-2 text-base">
                        {{ column.label }}
                        <span class="text-fg-soft font-sans text-sm font-semibold tabular-nums">{{
                            column.people.length
                        }}</span>
                    </h3>
                    <ul
                        v-if="column.people.length"
                        class="flex flex-col gap-2"
                        :aria-labelledby="`participants-${column.status ?? 'none'}`"
                    >
                        <li
                            v-for="person in column.people"
                            :key="person.user_id"
                            class="flex min-h-9 min-w-0 items-center gap-2.5"
                        >
                            <UserAvatar :name="person.name" size="sm" aria-hidden="true" />
                            <span class="min-w-0 flex-auto truncate text-[15px]">
                                {{ personName(person.name) }}
                                <span v-if="person.user_id === currentUserId" class="text-fg-soft text-sm"
                                    >({{ t('group.member.you') }})</span
                                >
                            </span>
                            <span
                                v-if="managing && person.user_id !== currentUserId"
                                class="flex flex-none items-center gap-1"
                            >
                                <NativeSelect
                                    v-if="permissions.can_set_status_for_others"
                                    size="sm"
                                    class="w-[124px]"
                                    :model-value="person.status ?? ''"
                                    :aria-label="t('event.setStatus', { name: personName(person.name) })"
                                    @update:model-value="setStatus(person, $event)"
                                >
                                    <NativeSelectOption v-if="person.status === null" value="" disabled>{{
                                        t('event.noStatus')
                                    }}</NativeSelectOption>
                                    <NativeSelectOption
                                        v-for="status in ['accepted', 'pending', 'declined'] as const"
                                        :key="status"
                                        :value="status"
                                        >{{ t(statusLabel[status]) }}</NativeSelectOption
                                    >
                                </NativeSelect>
                                <Button
                                    v-if="permissions.can_remove_participants && person.status !== null"
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="t('event.remove', { name: personName(person.name) })"
                                    @click="askRemove(person)"
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-fg-soft text-sm">{{ t('event.none') }}</p>
                </div>
            </div>

            <p v-if="!isParticipant" class="text-fg-muted text-sm">{{ t('event.keyDetailsNote') }}</p>
        </section>

        <template v-if="isParticipant">
            <section class="flex flex-col gap-4" aria-labelledby="event-lists-title" data-feature="F-13">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="event-lists-title" class="flex items-center gap-2.5 text-xl">
                        {{ t('event.lists') }}
                        <span
                            v-if="page.lists.length"
                            class="text-fg-soft font-sans text-sm font-semibold tabular-nums"
                            >{{ page.lists.length }}</span
                        >
                    </h2>
                    <Button v-if="permissions.can_create_list && page.lists.length" as-child variant="outline">
                        <Link :href="createList({ query: { event: event.slug } }).url">
                            <Plus aria-hidden="true" />
                            {{ t('group.new.list') }}
                        </Link>
                    </Button>
                </div>
                <ul v-if="page.lists.length" class="grid gap-2.5 sm:grid-cols-2">
                    <li v-for="list in page.lists" :key="list.slug">
                        <Link :href="showList(list.slug).url" :class="[rowClass, 'h-full items-start']">
                            <span class="bg-primary-soft text-primary-soft-fg grid size-10 flex-none place-items-center rounded-xl">
                                <ListChecks class="size-5" aria-hidden="true" />
                            </span>
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="font-display text-[17px] leading-tight font-[640] break-words">{{
                                    list.title
                                }}</span>
                                <span class="text-fg-muted text-sm">{{
                                    t('dash.lists.claimed', { done: list.claimed_count, total: list.item_count })
                                }}</span>
                                <ProgressBar
                                    class="mt-1"
                                    :done="list.claimed_count"
                                    :total="list.item_count"
                                    :label="t('dash.lists.claimed', { done: list.claimed_count, total: list.item_count })"
                                />
                            </span>
                        </Link>
                    </li>
                </ul>
                <EmptyState v-else :title="t('event.lists.empty.title')" :text="t('event.lists.empty.text')">
                    <Button v-if="permissions.can_create_list" as-child size="lg" variant="secondary" :class="buttonClass">
                        <Link :href="createList({ query: { event: event.slug } }).url">
                            <Plus aria-hidden="true" />
                            {{ t('group.new.list') }}
                        </Link>
                    </Button>
                </EmptyState>
            </section>

            <section v-if="!isChild" class="flex flex-col gap-4" aria-labelledby="followups-title" data-feature="F-12">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="followups-title" class="flex items-center gap-2.5 text-xl">
                        {{ t('event.followUps') }}
                        <span
                            v-if="page.follow_up_polls.length"
                            class="text-fg-soft font-sans text-sm font-semibold tabular-nums"
                            >{{ page.follow_up_polls.length }}</span
                        >
                    </h2>
                    <Button
                        v-if="permissions.can_create_follow_up_poll && page.follow_up_polls.length"
                        as-child
                    >
                        <Link :href="createFollowUpPoll(event.slug).url">
                            <Vote aria-hidden="true" />
                            {{ t('event.followUps.new') }}
                        </Link>
                    </Button>
                </div>
                <p v-if="!permissions.can_create_follow_up_poll && !page.follow_up_polls.length" class="text-fg-muted text-sm">
                    {{ t('event.followUps.acceptFirst') }}
                </p>
                <ul v-else-if="page.follow_up_polls.length" class="flex flex-col gap-2.5">
                    <li v-for="poll in page.follow_up_polls" :key="poll.slug">
                        <Link :href="showPoll(poll.slug).url" :class="rowClass">
                            <span
                                class="grid size-11 flex-none place-items-center rounded-xl"
                                :class="poll.is_closed ? 'bg-surface-muted text-fg-muted' : 'bg-primary-soft text-primary-soft-fg'"
                            >
                                <Grid3x3 v-if="poll.type === 'attendance'" class="size-[22px]" aria-hidden="true" />
                                <Vote v-else class="size-[22px]" aria-hidden="true" />
                            </span>
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="font-display text-[17px] leading-tight font-[640] break-words">{{
                                    poll.title
                                }}</span>
                                <span class="text-fg-muted flex flex-wrap gap-x-3 gap-y-1.5 text-sm">
                                    <span>{{
                                        poll.type === 'attendance' ? t('group.polls.attendance') : t('group.polls.dateFinder')
                                    }}</span>
                                    <span>{{ t('group.polls.options', { n: poll.option_count }) }}</span>
                                </span>
                            </span>
                            <span class="flex flex-none items-center gap-3 max-[560px]:basis-full max-[560px]:pl-[58px]">
                                <span
                                    v-if="!poll.is_closed"
                                    class="text-fg-muted inline-flex items-center gap-1.5 text-sm tabular-nums"
                                >
                                    <ProgressBar
                                        class="w-16 flex-none"
                                        :done="poll.answered_count"
                                        :total="poll.audience_count"
                                        :label="t('group.polls.responses', { n: poll.answered_count, total: poll.audience_count })"
                                    />
                                    {{ poll.answered_count }}/{{ poll.audience_count }}
                                </span>
                                <Badge v-if="!poll.is_closed" class="bg-primary-soft text-primary-soft-fg">{{
                                    t('group.polls.open')
                                }}</Badge>
                                <Badge v-else-if="poll.is_fixed" class="bg-success-soft text-success">{{
                                    t('group.polls.fixed')
                                }}</Badge>
                                <Badge v-else class="bg-surface-muted text-fg-muted">{{ t('group.polls.closed') }}</Badge>
                            </span>
                        </Link>
                    </li>
                </ul>
                <EmptyState v-else :title="t('event.followUps.empty.title')" :text="t('event.followUps.empty.text')">
                    <Button as-child size="lg" :class="buttonClass">
                        <Link :href="createFollowUpPoll(event.slug).url">
                            <Plus aria-hidden="true" />
                            {{ t('event.followUps.new') }}
                        </Link>
                    </Button>
                </EmptyState>
            </section>

            <section v-if="!isChild" class="flex flex-col gap-4" aria-labelledby="children-title" data-feature="F-12">
                <h2 id="children-title" class="flex items-center gap-2.5 text-xl">
                    {{ t('event.children') }}
                    <span
                        v-if="page.child_events.length"
                        class="text-fg-soft font-sans text-sm font-semibold tabular-nums"
                        >{{ page.child_events.length }}</span
                    >
                </h2>
                <ul v-if="page.child_events.length" class="flex flex-col gap-2.5">
                    <li v-for="child in page.child_events" :key="child.slug">
                        <Link :href="showEvent(child.slug).url" :class="[rowClass, child.is_past && 'opacity-75']" data-feature="F-09">
                            <DateBlock :date="child.date" :fixed="child.is_from_poll && !child.is_past" />
                            <span class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="font-display text-[17px] leading-tight font-[640] break-words">{{
                                    child.title
                                }}</span>
                                <span class="text-fg-muted flex flex-wrap gap-x-3 gap-y-1.5 text-sm">
                                    <span>{{ childWhen(child) }}</span>
                                    <span v-if="child.location">{{ child.location }}</span>
                                </span>
                                <span class="text-fg-muted flex flex-wrap gap-x-3 gap-y-1.5 text-sm">
                                    <span>{{ t('group.events.accepted', { n: child.accepted_count }) }}</span>
                                    <span v-if="child.pending_count && !child.is_past">{{
                                        t('group.events.pending', { n: child.pending_count })
                                    }}</span>
                                </span>
                            </span>
                            <span
                                v-if="child.own_status && !child.is_past"
                                class="flex flex-none items-center max-[560px]:basis-full max-[560px]:pl-[68px]"
                            >
                                <Badge :class="childBadge[child.own_status].class">{{
                                    t(childBadge[child.own_status].label)
                                }}</Badge>
                            </span>
                        </Link>
                    </li>
                </ul>
                <EmptyState v-else :title="t('event.children.empty.title')" :text="t('event.children.empty.text')" />
            </section>
        </template>

        <section
            v-if="permissions.can_comment"
            class="box flex flex-col gap-4 p-5 sm:px-7"
            aria-labelledby="event-comments-title"
            data-feature="F-20"
        >
            <h2 id="event-comments-title" class="text-lg">
                <button
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-sm text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring"
                    :aria-expanded="commentsOpen"
                    aria-controls="event-comments-body"
                    @click="commentsOpen = !commentsOpen"
                >
                    <MessageSquare class="text-fg-soft size-5" aria-hidden="true" />
                    <span class="min-w-0 flex-1">{{ t('event.comments.toggle', { n: page.comments.length }) }}</span>
                    <ChevronRight
                        class="text-fg-soft size-4 transition-transform motion-reduce:transition-none"
                        :class="commentsOpen && 'rotate-90'"
                        aria-hidden="true"
                    />
                </button>
            </h2>
            <div v-if="commentsOpen" id="event-comments-body" class="border-border border-t pt-4">
                <CommentThread
                    v-model="commentForm.body"
                    :comments="threadComments"
                    :error="commentForm.errors.body ?? null"
                    :processing="commentForm.processing"
                    @submit="postComment"
                    @delete="deleteComment"
                />
            </div>
        </section>

        <AlertDialog v-model:open="declineOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('event.decline.title') }}</AlertDialogTitle>
                    <AlertDialogDescription>{{ t('event.decline.text') }}</AlertDialogDescription>
                </AlertDialogHeader>
                <ul class="flex flex-col gap-2" data-feature="F-11">
                    <li
                        v-for="(claim, index) in page.own_claims"
                        :key="`${claim.roster_slug}-${index}`"
                        class="flex items-center gap-3"
                    >
                        <Badge variant="outline" class="tabular-nums">{{ claimLabel(claim) }}</Badge>
                        <span class="flex min-w-0 flex-col">
                            <span class="font-semibold break-words">{{ claim.item_name }}</span>
                            <span class="text-fg-muted text-sm">{{ t('event.inList', { list: claim.roster_title }) }}</span>
                        </span>
                    </li>
                </ul>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('dialog.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction variant="destructive" @click="confirmDecline">{{
                        t('event.decline.confirm')
                    }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <AlertDialog v-model:open="removeOpen">
            <AlertDialogContent v-if="removeTarget">
                <AlertDialogHeader>
                    <AlertDialogTitle>{{
                        t('event.removeTitle', { name: personName(removeTarget.name) })
                    }}</AlertDialogTitle>
                    <AlertDialogDescription>{{ t('event.removeText') }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('dialog.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction variant="destructive" @click="confirmRemove">{{
                        t('event.removeConfirm')
                    }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <AlertDialog v-if="page.delete_counts" v-model:open="deleteOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('event.delete.title') }}</AlertDialogTitle>
                    <AlertDialogDescription>{{ t('event.delete.text') }}</AlertDialogDescription>
                </AlertDialogHeader>
                <ul class="flex flex-wrap gap-2" data-feature="F-10">
                    <li><Badge variant="outline">{{ t('event.delete.lists', { n: page.delete_counts.lists }) }}</Badge></li>
                    <li>
                        <Badge variant="outline">{{
                            t('event.delete.polls', { n: page.delete_counts.follow_up_polls })
                        }}</Badge>
                    </li>
                    <li>
                        <Badge variant="outline">{{
                            t('event.delete.children', { n: page.delete_counts.child_events })
                        }}</Badge>
                    </li>
                </ul>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('dialog.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction variant="destructive" @click="confirmDelete">{{
                        t('event.delete.confirm')
                    }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
