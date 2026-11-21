<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useEcho, usePresenceChannel } from '@laravel/echo-vue';
import {
    ArrowLeft,
    CalendarDays,
    Check,
    ChevronDown,
    ChevronRight,
    Clock,
    LogOut,
    MessageSquare,
    Pencil,
    Settings,
    Sparkles,
    Table2,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { show as showEvent } from '@/actions/App/Http/Controllers/EventController';
import { show as showGroup } from '@/actions/App/Http/Controllers/GroupController';
import {
    destroy as revokeInviteLink,
    regenerate as regenerateInviteLink,
    store as storeInviteLink,
} from '@/actions/App/Http/Controllers/PollInviteLinkController';
import {
    destroy as destroyComment,
    store as storeComment,
} from '@/actions/App/Http/Controllers/PollCommentController';
import {
    close as closePoll,
    edit as editPoll,
    fix as fixPoll,
    reopen as reopenPoll,
    withdraw as withdrawFromPoll,
} from '@/actions/App/Http/Controllers/PollController';
import {
    fillOpen,
    store as storeResponse,
    storeFor as storeResponseFor,
} from '@/actions/App/Http/Controllers/PollResponseController';
import { dashboard } from '@/routes';
import CommentThread from '@/components/app/comment-thread.vue';
import type { ThreadComment } from '@/components/app/comment-thread.vue';
import AvatarStack from '@/components/app/avatar-stack.vue';
import DateBlock from '@/components/app/date-block.vue';
import EmptyState from '@/components/app/empty-state.vue';
import ShareDialog from '@/components/app/share-dialog.vue';
import PollCalendar from '@/components/app/poll-calendar.vue';
import PollGrid from '@/components/app/poll-grid.vue';
import NoteDialog from '@/components/app/poll/note-dialog.vue';
import {
    dayOf,
    describeOption,
    formatDate,
    formatInstant,
    timeOf,
} from '@/components/app/poll/dates';
import type {
    PollAnswer,
    PollAnswers,
    PollGridOption,
    PollGridParticipant,
    PollKey,
    PollNotes,
    PollOptionNote,
    PollOptionTotals,
} from '@/components/app/poll/types';
import SectionBox from '@/components/app/section-box.vue';
import SectionBoxItem from '@/components/app/section-box-item.vue';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ page: App.Data.PollShowData }>();

const { t, tc, locale } = useI18n();
const currentUserId = computed(() => usePage().props.auth.user?.id ?? null);

const poll = computed(() => props.page.poll);
const permissions = computed(() => props.page.permissions);
const isDateFinder = computed(() => poll.value.type === 'date_finder');
const isOpen = computed(() => !poll.value.is_closed);

// The deadline shows in the viewer's zone; "passed" comes from the server clock, the same one auto-close uses (F-08)
const deadlineLabel = computed(() => {
    if (!poll.value.answer_deadline) {
        return null;
    }

    if (poll.value.has_passed_deadline) {
        return t('poll.deadlinePassed');
    }

    return t('poll.deadline', {
        date: formatInstant(
            poll.value.answer_deadline,
            locale.value,
            { weekday: 'long', day: 'numeric', month: 'long' },
            true,
        ),
    });
});

const backLink = computed(() => {
    if (poll.value.source_event) {
        return {
            href: showEvent(poll.value.source_event.slug).url,
            name: poll.value.source_event.title,
        };
    }

    if (!poll.value.group_slug) {
        return { href: dashboard().url, name: null };
    }

    if (!permissions.value.can_view_group) {
        return null;
    }

    return {
        href: showGroup(poll.value.group_slug).url,
        name: poll.value.group_name ?? '',
    };
});

const status = computed(() => {
    if (isOpen.value) {
        return { label: t('group.polls.open'), variant: 'default' as const };
    }

    if (poll.value.options.some((option) => option.is_fixed)) {
        return { label: t('group.polls.fixed'), variant: 'secondary' as const };
    }

    return { label: t('group.polls.closed'), variant: 'outline' as const };
});

const featureId = computed(() => {
    if (poll.value.source_event) {
        return 'F-12';
    }

    return isDateFinder.value ? 'F-06' : 'F-07';
});

// Option slugs are the grid's column keys; slots group by their start day in the viewer's zone
const gridOptions = computed<PollGridOption[]>(() =>
    poll.value.options.map((option) => ({
        id: option.slug,
        date: dayOf(option),
        start: timeOf(option.starts_at),
        end: timeOf(option.ends_at),
        label: option.label,
        fixed: option.is_fixed,
    })),
);

const totals = computed<Record<string, PollOptionTotals>>(() =>
    Object.fromEntries(
        poll.value.options.map((option) => [
            option.slug,
            {
                yes: option.yes_count,
                maybe: option.maybe_count,
                no: option.no_count,
            },
        ]),
    ),
);

const answers = computed<PollAnswers>(() =>
    Object.fromEntries(
        props.page.respondents.map((respondent) => [
            String(respondent.user_id),
            respondent.answers as Record<string, PollAnswer>,
        ]),
    ),
);

const notes = computed<PollNotes>(() =>
    Object.fromEntries(
        props.page.respondents.map((respondent) => [
            String(respondent.user_id),
            respondent.notes,
        ]),
    ),
);

// The calendar only shows the own answers, so other people's notes gather per option there
const otherNotes = computed<Record<string, PollOptionNote[]>>(() => {
    const byOption: Record<string, PollOptionNote[]> = {};

    props.page.respondents
        .filter((respondent) => respondent.user_id !== currentUserId.value)
        .forEach((respondent) => {
            Object.entries(respondent.notes).forEach(([slug, note]) => {
                (byOption[slug] ??= []).push({
                    name: respondent.name ?? t('common.noName'),
                    note,
                });
            });
        });

    return byOption;
});

// The note dialog edits the own note on one option (F-06)
const noteDialogOpen = ref(false);
const noteOptionId = ref<PollKey | null>(null);
const noteOption = computed(
    () =>
        gridOptions.value.find(
            (option) => String(option.id) === String(noteOptionId.value),
        ) ?? null,
);

function editNote(optionId: PollKey): void {
    noteOptionId.value = optionId;
    noteDialogOpen.value = true;
}

const hasAnswered = (respondent: App.Data.PollRespondentData): boolean =>
    Object.keys(respondent.answers).length > 0;

const answered = computed(() => props.page.respondents.filter(hasAnswered));
const missing = computed(() =>
    props.page.respondents.filter((respondent) => !hasAnswered(respondent)),
);

// "Edit answers" (F-06): editors set answers for every audience member, including those without any answer
const editingAnswers = ref(false);

const editableRespondentIds = computed(() =>
    editingAnswers.value && permissions.value.can_edit_answers
        ? props.page.respondents
              .filter((respondent) => respondent.in_audience)
              .map((respondent) => respondent.user_id)
        : [],
);

function startEditingAnswers(): void {
    editingAnswers.value = true;
    view.value = 'table';
}

function chooseView(next: GridView): void {
    view.value = next;

    if (next !== 'table') {
        editingAnswers.value = false;
    }
}

// The own row always comes first so it can be answered; others only appear once they answered, or all of them while editing
const rows = computed<PollGridParticipant[]>(() => {
    const own = props.page.respondents.find(
        (respondent) => respondent.user_id === currentUserId.value,
    );
    const shown = editableRespondentIds.value.length
        ? props.page.respondents.filter(
              (respondent) => respondent.in_audience || hasAnswered(respondent),
          )
        : answered.value;
    const others = shown
        .filter((respondent) => respondent.user_id !== currentUserId.value)
        .sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''));

    return [...(own ? [own] : []), ...others].map((respondent) => ({
        id: respondent.user_id,
        name: respondent.name,
    }));
});

const ownRespondent = computed(() =>
    props.page.respondents.find(
        (respondent) => respondent.user_id === currentUserId.value,
    ),
);
const isRespondent = computed(() => ownRespondent.value !== undefined);
const hasOpenOptions = computed(() =>
    poll.value.options.some(
        (option) => !(option.slug in (ownRespondent.value?.answers ?? {})),
    ),
);

const fillChoices: { value: PollAnswer; swatch: string }[] = [
    { value: 'yes', swatch: 'bg-primary' },
    { value: 'maybe', swatch: 'bg-warning' },
    { value: 'no', swatch: 'bg-danger' },
];

// The calendar sits next to the table so people can hold the options against their own calendar
type GridView = 'table' | 'calendar';
const views: { value: GridView; icon: typeof Table2 }[] = [
    { value: 'table', icon: Table2 },
    { value: 'calendar', icon: CalendarDays },
];
const view = ref<GridView>('table');

const saving = ref(false);

function showRequestError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? t('group.requestFailed'));
}

function answer(optionId: PollKey, value: PollAnswer): void {
    if (!permissions.value.can_respond) {
        toast.error(t('poll.closedNote'));

        return;
    }

    // Only the page prop reloads, so answering clicks through the grid without a toast per cell
    router.post(
        storeResponse({ poll: poll.value.slug, option: String(optionId) }).url,
        { status: value },
        {
            only: ['page'],
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                saving.value = true;
            },
            onFinish: () => {
                saving.value = false;
            },
            onError: showRequestError,
        },
    );
}

function fillOpenOptions(value: PollAnswer): void {
    if (!permissions.value.can_respond) {
        toast.error(t('poll.closedNote'));

        return;
    }

    router.post(
        fillOpen({ poll: poll.value.slug }).url,
        { status: value },
        {
            only: ['page'],
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                saving.value = true;
            },
            onFinish: () => {
                saving.value = false;
            },
            onError: showRequestError,
        },
    );
}

function answerFor(
    participantId: PollKey,
    optionId: PollKey,
    value: PollAnswer,
): void {
    router.post(
        storeResponseFor({
            poll: poll.value.slug,
            option: String(optionId),
            member: Number(participantId),
        }).url,
        { status: value },
        {
            only: ['page'],
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                saving.value = true;
            },
            onFinish: () => {
                saving.value = false;
            },
            onError: showRequestError,
        },
    );
}

function close(): void {
    router.post(
        closePoll(poll.value.slug).url,
        {},
        { preserveScroll: true, onError: showRequestError },
    );
}

function reopen(): void {
    router.delete(reopenPoll(poll.value.slug).url, {
        preserveScroll: true,
        onError: showRequestError,
    });
}

// Withdrawing (F-08): the poll is gone for this person afterwards, so live reloads stop
const withdrawOpen = ref(false);
const withdrawing = ref(false);

function withdraw(): void {
    withdrawing.value = true;
    router.delete(withdrawFromPoll(poll.value.slug).url, {
        onError: (errors) => {
            withdrawing.value = false;
            showRequestError(errors);
        },
    });
}

// Fixing dates (F-09)
const fixOpen = ref(false);
const selected = ref<string[]>([]);

function openFix(): void {
    selected.value = [];
    fixOpen.value = true;
}

const fixPreview = computed(() =>
    poll.value.options
        .filter((option) => selected.value.includes(option.slug))
        .map((option) => ({
            option,
            accepted: props.page.respondents
                .filter(
                    (respondent) => respondent.answers[option.slug] === 'yes',
                )
                .map((respondent) => ({
                    id: respondent.user_id,
                    name: respondent.name,
                })),
            pending: props.page.respondents
                .filter(
                    (respondent) => respondent.answers[option.slug] === 'maybe',
                )
                .map((respondent) => ({
                    id: respondent.user_id,
                    name: respondent.name,
                })),
        })),
);

const fixLabel = computed(() => {
    if (selected.value.length === 0) {
        return t('poll.fix.confirmNone');
    }

    return selected.value.length === 1
        ? t('poll.fix.confirmOne')
        : t('poll.fix.confirmMany', { n: selected.value.length });
});

const fixing = ref(false);

function confirmFix(): void {
    if (selected.value.length === 0) {
        return;
    }

    router.post(
        fixPoll(poll.value.slug).url,
        { options: selected.value },
        {
            preserveScroll: true,
            onStart: () => {
                fixing.value = true;
            },
            onFinish: () => {
                fixing.value = false;
            },
            onSuccess: () => {
                fixOpen.value = false;
            },
            onError: showRequestError,
        },
    );
}

function optionWhen(option: {
    date: string | null;
    starts_at: string | null;
    ends_at: string | null;
}): string {
    const day = formatDate(dayOf(option), locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

    if (!option.starts_at) {
        return day;
    }

    return option.ends_at
        ? `${day}, ${timeOf(option.starts_at)}–${timeOf(option.ends_at)}`
        : `${day}, ${timeOf(option.starts_at)}`;
}

// Comments (F-20): collapsed by default, like on events
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
    commentForm.post(storeComment(props.page.poll.slug).url, {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
}

function deleteComment(id: ThreadComment['id']): void {
    router.delete(destroyComment([props.page.poll.slug, Number(id)]).url, {
        preserveScroll: true,
        onError: (errors) =>
            toast.error(Object.values(errors)[0], { duration: 6000 }),
    });
}

// Live updates (F-21): every change reloads the page prop; a deleted poll sends people back
useEcho<{ deleted?: boolean }>(
    `poll.${props.page.poll.id}`,
    [
        '.poll.changed',
        '.poll.response.updated',
        '.poll.option.fixed',
        '.comment.added',
        '.comment.deleted',
    ],
    (payload) => {
        if (withdrawing.value) {
            return;
        }

        if (payload.deleted) {
            router.visit(backLink.value?.href ?? dashboard().url);

            return;
        }

        router.reload({ only: ['page'] });
    },
);

type Viewer = { id: number; name: string };
const viewers = ref<Viewer[]>([]);
const { channel: presenceChannel } = usePresenceChannel(
    `presence.poll.${props.page.poll.id}`,
);

presenceChannel()
    .here((users: Viewer[]) => {
        viewers.value = users;
    })
    .joining((user: Viewer) => {
        if (!viewers.value.some((viewer) => viewer.id === user.id)) {
            viewers.value = [...viewers.value, user];
        }
    })
    .leaving((user: Viewer) => {
        viewers.value = viewers.value.filter((viewer) => viewer.id !== user.id);
    });

const otherViewers = computed(() =>
    viewers.value.filter((viewer) => viewer.id !== currentUserId.value),
);

const buttonClass = 'h-(--control-height) px-4';
</script>

<template>
    <Head :title="poll.title" />

    <div class="flex flex-col gap-10">
        <header class="flex flex-col gap-[18px]" :data-feature="featureId">
            <Link
                v-if="backLink"
                :href="backLink.href"
                class="text-fg-muted hover:text-fg -my-0.5 inline-flex min-h-6 w-fit items-center gap-1.5 rounded-sm text-sm font-semibold"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{
                    backLink.name === null
                        ? t('nav.backToDashboard')
                        : t('poll.back', { name: backLink.name })
                }}
            </Link>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 flex-col gap-2.5">
                    <h1 class="text-h1 break-words">{{ poll.title }}</h1>
                    <div
                        class="text-fg-muted flex flex-wrap items-center gap-x-3 gap-y-2 text-sm"
                    >
                        <Badge :variant="status.variant">{{
                            status.label
                        }}</Badge>
                        <Badge variant="outline">{{
                            isDateFinder
                                ? t('group.polls.dateFinder')
                                : t('group.polls.attendance')
                        }}</Badge>
                        <Badge variant="outline">{{
                            poll.uses_time_slots
                                ? t('newPoll.mode.slots')
                                : t('newPoll.mode.day')
                        }}</Badge>
                        <Link
                            v-if="permissions.can_view_group && poll.group_slug"
                            :href="showGroup(poll.group_slug).url"
                            class="text-primary hover:text-primary-hover inline-flex items-center gap-1.5 rounded-sm font-semibold underline-offset-[3px] hover:underline"
                        >
                            <Users class="size-4" aria-hidden="true" />
                            {{ poll.group_name }}
                        </Link>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 font-semibold"
                        >
                            <Users class="size-4" aria-hidden="true" />
                            {{ poll.group_name ?? t('newList.standalone') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <UserAvatar :name="poll.organizer_name" size="sm" />
                            {{
                                t('poll.by', {
                                    name:
                                        poll.organizer_name ??
                                        t('common.noName'),
                                })
                            }}
                        </span>
                        <span
                            v-if="deadlineLabel"
                            class="inline-flex items-center gap-1.5"
                            :class="poll.has_passed_deadline && 'text-warning'"
                            data-feature="F-08"
                        >
                            <Clock class="size-4" aria-hidden="true" />
                            {{ deadlineLabel }}
                        </span>
                    </div>
                </div>
                <div
                    v-if="
                        permissions.can_edit ||
                        permissions.can_fix ||
                        page.invite_link ||
                        permissions.can_withdraw
                    "
                    class="flex flex-wrap gap-2.5"
                    data-feature="F-08"
                >
                    <Button
                        type="button"
                        v-if="isDateFinder && permissions.can_fix"
                        size="lg"
                        :class="buttonClass"
                        data-feature="F-09"
                        @click="openFix"
                    >
                        <Sparkles aria-hidden="true" />
                        {{ t('poll.actions.fix') }}
                    </Button>
                    <Button
                        type="button"
                        v-if="permissions.can_close"
                        variant="secondary"
                        size="lg"
                        :class="buttonClass"
                        @click="close"
                    >
                        {{ t('poll.actions.close') }}
                    </Button>
                    <Button
                        type="button"
                        v-if="permissions.can_reopen"
                        variant="secondary"
                        size="lg"
                        :class="buttonClass"
                        @click="reopen"
                    >
                        {{ t('poll.actions.reopen') }}
                    </Button>
                    <Button
                        v-if="permissions.can_edit"
                        as-child
                        variant="ghost"
                        size="lg"
                        :class="buttonClass"
                    >
                        <Link :href="editPoll(poll.slug).url">
                            <Settings aria-hidden="true" />
                            {{ t('poll.edit.title') }}
                        </Link>
                    </Button>
                    <ShareDialog
                        v-if="page.invite_link"
                        id="poll-link"
                        :url="page.invite_link.url"
                        :create-url="storeInviteLink(poll.slug).url"
                        :regenerate-url="regenerateInviteLink(poll.slug).url"
                        :revoke-url="revokeInviteLink(poll.slug).url"
                        :hint="t('entityLink.pollHint')"
                        data-feature="F-27"
                    />
                    <Button
                        v-if="permissions.can_withdraw"
                        type="button"
                        variant="ghost"
                        size="lg"
                        :class="buttonClass"
                        @click="withdrawOpen = true"
                    >
                        <LogOut aria-hidden="true" />
                        {{ t('poll.withdraw') }}
                    </Button>
                </div>
            </div>

            <div
                v-if="poll.source_event"
                class="bg-primary-soft text-primary-soft-fg flex flex-wrap items-center gap-x-3.5 gap-y-3 rounded-md px-3.5 py-3 text-sm"
                data-feature="F-12"
            >
                <Users class="size-6 flex-none" aria-hidden="true" />
                <span class="flex min-w-0 flex-1 flex-col gap-px">
                    <b class="font-semibold">{{
                        t(
                            page.answers_without_acceptance
                                ? 'poll.audienceEventDirect'
                                : 'newPoll.audienceEvent',
                            { event: poll.source_event.title },
                        )
                    }}</b>
                    <span>{{
                        tc('newPoll.audienceEventCount', page.audience_count)
                    }}</span>
                </span>
                <Button as-child variant="secondary" size="sm" class="ml-auto">
                    <Link :href="showEvent(poll.source_event.slug).url">
                        {{ t('poll.openEvent') }}
                        <ChevronRight aria-hidden="true" />
                    </Link>
                </Button>
            </div>

            <div
                v-if="!isOpen"
                class="box flex gap-3 px-5 py-3.5 text-[15px]"
                role="status"
                data-feature="F-08"
            >
                <Check
                    class="text-success mt-px size-5 flex-none"
                    aria-hidden="true"
                />
                <span>{{
                    page.fixed_events.length
                        ? t('poll.closedFixedNote')
                        : t('poll.closedNote')
                }}</span>
            </div>
            <p
                v-else-if="!isDateFinder"
                class="text-fg-soft text-sm"
                data-feature="F-07"
            >
                {{ t('poll.attendanceHint') }}
            </p>

            <div
                v-if="otherViewers.length"
                class="text-fg-muted flex flex-wrap items-center gap-2.5 text-sm"
                data-feature="F-21"
            >
                <AvatarStack :people="otherViewers" :max="5" />
                <span>{{ t('poll.presence') }}</span>
            </div>
        </header>

        <SectionBox
            id="grid-title"
            :title="t('poll.grid')"
            :count="answered.length"
            :data-feature="isDateFinder ? 'F-06' : 'F-07'"
            stack-actions
        >
            <template #meta>
                <span class="text-fg-soft text-sm">{{
                    t('group.polls.responses', {
                        n: answered.length,
                        total: page.audience_count,
                    })
                }}</span>
            </template>
            <template #action>
                <DropdownMenu v-if="permissions.can_respond && isRespondent">
                    <DropdownMenuTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-[38px] self-center px-3.5 text-[13px] font-semibold max-sm:order-2 max-sm:w-full"
                            :disabled="saving || !hasOpenOptions"
                        >
                            {{ t('poll.answer.fillOpen') }}
                            <ChevronDown class="size-4" aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="min-w-48">
                        <DropdownMenuLabel>{{
                            t('poll.answer.fillOpenWith')
                        }}</DropdownMenuLabel>
                        <DropdownMenuItem
                            v-for="choice in fillChoices"
                            :key="choice.value"
                            @select="fillOpenOptions(choice.value)"
                        >
                            <span
                                :class="[
                                    'size-2.5 shrink-0 rounded-full',
                                    choice.swatch,
                                ]"
                                aria-hidden="true"
                            />
                            {{ t(`poll.answer.${choice.value}`) }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <div
                    class="bg-surface-sunken inline-flex gap-0.5 rounded-full p-[3px] max-sm:order-1 max-sm:w-full"
                    role="group"
                    :aria-label="t('poll.view')"
                >
                    <button
                        v-for="option in views"
                        :key="option.value"
                        type="button"
                        :aria-pressed="view === option.value"
                        class="text-fg-muted hover:text-fg aria-pressed:bg-surface-raised aria-pressed:text-fg focus-visible:outline-focus-ring inline-flex min-h-8 items-center gap-1.5 rounded-full px-2.5 text-[13px] font-semibold aria-pressed:shadow-sm max-sm:flex-1 max-sm:justify-center"
                        @click="chooseView(option.value)"
                    >
                        <component
                            :is="option.icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                        {{ t(`poll.view.${option.value}`) }}
                    </button>
                </div>
                <Button
                    v-if="permissions.can_edit_answers"
                    type="button"
                    :variant="editingAnswers ? 'default' : 'outline'"
                    size="sm"
                    class="h-[38px] self-center px-3.5 text-[13px] font-semibold max-sm:order-3 max-sm:self-start"
                    :aria-pressed="editingAnswers"
                    data-feature="F-06"
                    @click="
                        editingAnswers
                            ? (editingAnswers = false)
                            : startEditingAnswers()
                    "
                >
                    <Check
                        v-if="editingAnswers"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <Pencil v-else class="size-4" aria-hidden="true" />
                    {{
                        editingAnswers
                            ? t('poll.editAnswers.done')
                            : t('poll.editAnswers')
                    }}
                </Button>
            </template>

            <PollCalendar
                v-if="view === 'calendar'"
                :id-prefix="`poll-${poll.slug}`"
                :mode="poll.uses_time_slots ? 'slots' : 'day'"
                :options="gridOptions"
                :answers="page.own_answers"
                :totals="totals"
                :best-option-ids="
                    isDateFinder && isOpen ? page.best_option_slugs : []
                "
                :show-answers="isRespondent"
                :closed="!permissions.can_respond"
                :busy="saving"
                :notes="ownRespondent?.notes ?? {}"
                :other-notes="otherNotes"
                @answer="answer"
                @edit-note="editNote"
            />
            <PollGrid
                v-if="view === 'table'"
                :id-prefix="`poll-${poll.slug}`"
                :options="gridOptions"
                :participants="rows"
                :answers="answers"
                :totals="totals"
                :best-option-ids="
                    isDateFinder && isOpen ? page.best_option_slugs : []
                "
                :show-no-totals="isDateFinder && isOpen && page.show_no_counts"
                :current-participant-id="currentUserId"
                :closed="!permissions.can_respond"
                :busy="saving"
                :editable-participant-ids="editableRespondentIds"
                :notes="notes"
                @answer="answer"
                @answer-for="answerFor"
                @edit-note="editNote"
            />
            <NoteDialog
                v-model:open="noteDialogOpen"
                :poll-slug="poll.slug"
                :option="noteOption"
                :note="
                    noteOptionId === null
                        ? null
                        : (ownRespondent?.notes[String(noteOptionId)] ?? null)
                "
                :max-length="page.note_max_length"
            />

            <EmptyState
                v-if="!answered.length"
                :title="t('poll.empty.title')"
                :text="t('poll.empty.text')"
            />
            <div
                v-else-if="missing.length"
                class="text-fg-muted flex flex-wrap items-center gap-x-3 gap-y-2.5 text-sm"
            >
                <span class="text-fg-soft font-semibold">{{
                    t('poll.missing')
                }}</span>
                <AvatarStack
                    :people="
                        missing.map((person) => ({
                            id: person.user_id,
                            name: person.name,
                        }))
                    "
                    :max="6"
                />
                <span>{{
                    missing
                        .map((person) => person.name ?? t('common.noName'))
                        .join(', ')
                }}</span>
            </div>
        </SectionBox>

        <SectionBox
            v-if="page.fixed_events.length"
            id="fixed-title"
            :title="t('poll.events')"
            :count="page.fixed_events.length"
            data-feature="F-09"
        >
            <ul class="flex flex-col gap-3">
                <li v-for="event in page.fixed_events" :key="event.slug">
                    <SectionBoxItem
                        :as="Link"
                        :href="showEvent(event.slug).url"
                        class="flex items-center gap-4 px-4 py-3.5 sm:px-[22px]"
                    >
                        <DateBlock :date="dayOf(event)" fixed />
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span
                                class="font-display text-[17px] leading-tight font-[640] break-words"
                                >{{ event.title }}</span
                            >
                            <span class="text-fg-muted text-sm">{{
                                optionWhen({
                                    date: event.date,
                                    starts_at: event.starts_at,
                                    ends_at: null,
                                })
                            }}</span>
                        </span>
                        <ChevronRight
                            class="text-fg-soft size-5 flex-none"
                            aria-hidden="true"
                        />
                    </SectionBoxItem>
                </li>
            </ul>
        </SectionBox>

        <!-- One heading only: the disclosure button is the section's title, so no SectionBox title row -->
        <section
            class="box flex flex-col gap-4 p-3.5 shadow-(--box-shadow) sm:px-[26px] sm:py-6"
            aria-labelledby="poll-comments-title"
            data-feature="F-20"
        >
            <h2 id="poll-comments-title" class="text-h2">
                <button
                    type="button"
                    class="focus-visible:outline-focus-ring flex min-h-6 w-full items-center gap-2.5 rounded-sm text-left focus-visible:outline-2 focus-visible:outline-offset-2"
                    :aria-expanded="commentsOpen"
                    aria-controls="poll-comments-body"
                    @click="commentsOpen = !commentsOpen"
                >
                    <MessageSquare
                        class="text-fg-soft size-5 flex-none"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">{{
                        t('poll.comments.toggle', { n: page.comments.length })
                    }}</span>
                    <ChevronRight
                        class="text-fg-soft size-4 flex-none transition-transform motion-reduce:transition-none"
                        :class="commentsOpen && 'rotate-90'"
                        aria-hidden="true"
                    />
                </button>
            </h2>
            <div
                v-if="commentsOpen"
                id="poll-comments-body"
                class="border-border border-t pt-4"
            >
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

        <Dialog v-model:open="fixOpen">
            <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ t('poll.fix.title') }}</DialogTitle>
                    <DialogDescription>{{
                        t('poll.fix.sub')
                    }}</DialogDescription>
                </DialogHeader>

                <fieldset class="flex flex-col gap-2" data-feature="F-09">
                    <legend class="text-fg mb-2 text-sm font-semibold">
                        {{ t('poll.fix.options') }}
                    </legend>
                    <label
                        v-for="option in poll.options"
                        :key="option.slug"
                        :class="[
                            'box has-focus-visible:outline-focus-ring flex flex-wrap items-center gap-3 rounded-md px-3.5 py-2.5 has-focus-visible:outline-2 has-focus-visible:outline-offset-2',
                            option.is_fixed
                                ? 'opacity-70'
                                : 'has-checked:border-primary has-checked:bg-primary-soft cursor-pointer',
                        ]"
                    >
                        <input
                            v-model="selected"
                            type="checkbox"
                            class="accent-primary size-6 flex-none"
                            :value="option.slug"
                            :disabled="option.is_fixed"
                        />
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="font-semibold">{{
                                describeOption(
                                    {
                                        date: dayOf(option),
                                        start: timeOf(option.starts_at),
                                        end: timeOf(option.ends_at),
                                        label: option.label,
                                    },
                                    locale,
                                )
                            }}</span>
                            <span class="text-fg-muted text-[13px]">
                                {{
                                    t('poll.totals.yes', {
                                        n: option.yes_count,
                                    })
                                }},
                                {{
                                    t('poll.totals.maybe', {
                                        n: option.maybe_count,
                                    })
                                }},
                                {{
                                    t('poll.totals.no', { n: option.no_count })
                                }}
                            </span>
                        </span>
                        <Badge
                            v-if="
                                !option.is_fixed &&
                                page.best_option_slugs.includes(option.slug)
                            "
                            >{{ t('poll.best') }}</Badge
                        >
                        <Badge v-if="option.is_fixed" variant="secondary">{{
                            t('poll.fix.alreadyFixed')
                        }}</Badge>
                    </label>
                </fieldset>

                <div
                    class="bg-surface-muted flex flex-col gap-3 rounded-md p-3.5"
                    aria-live="polite"
                >
                    <p v-if="!fixPreview.length" class="text-fg-muted">
                        {{ t('poll.fix.previewNone') }}
                    </p>
                    <template v-else>
                        <p>
                            <b>{{
                                fixPreview.length === 1
                                    ? t('poll.fix.previewOne')
                                    : t('poll.fix.previewMany', {
                                          n: fixPreview.length,
                                      })
                            }}</b>
                            {{ t('poll.fix.derived') }}
                        </p>
                        <ul class="flex flex-col gap-2.5">
                            <li
                                v-for="entry in fixPreview"
                                :key="entry.option.slug"
                                class="flex items-start gap-3"
                            >
                                <DateBlock :date="dayOf(entry.option)" fixed />
                                <span class="flex min-w-0 flex-col gap-1">
                                    <span class="font-semibold">{{
                                        poll.title
                                    }}</span>
                                    <span class="text-fg-muted text-[13px]">{{
                                        optionWhen(entry.option)
                                    }}</span>
                                    <span
                                        class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13px]"
                                    >
                                        <span
                                            v-if="entry.accepted.length"
                                            class="inline-flex items-center gap-2"
                                        >
                                            <AvatarStack
                                                :people="entry.accepted"
                                                :max="5"
                                            />
                                            {{
                                                t('group.events.accepted', {
                                                    n: entry.accepted.length,
                                                })
                                            }}
                                        </span>
                                        <span
                                            v-if="entry.pending.length"
                                            class="inline-flex items-center gap-2"
                                        >
                                            <AvatarStack
                                                :people="entry.pending"
                                                :max="5"
                                            />
                                            {{
                                                t('group.events.pending', {
                                                    n: entry.pending.length,
                                                })
                                            }}
                                        </span>
                                        <span
                                            v-if="
                                                !entry.accepted.length &&
                                                !entry.pending.length
                                            "
                                            class="text-fg-soft"
                                            >{{
                                                t('poll.fix.noParticipants')
                                            }}</span
                                        >
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </template>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        :class="buttonClass"
                        @click="fixOpen = false"
                        >{{ t('dialog.cancel') }}</Button
                    >
                    <Button
                        type="button"
                        size="lg"
                        :class="buttonClass"
                        :disabled="!selected.length || fixing"
                        data-feature="F-09"
                        @click="confirmFix"
                        >{{ fixLabel }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog v-model:open="withdrawOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{
                        t('poll.withdraw.title', { poll: poll.title })
                    }}</AlertDialogTitle>
                    <AlertDialogDescription>{{
                        t('poll.withdraw.text')
                    }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{
                        t('dialog.cancel')
                    }}</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        @click="withdraw()"
                        >{{ t('poll.withdraw') }}</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
