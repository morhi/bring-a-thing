<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useEcho, usePresenceChannel } from '@laravel/echo-vue';
import { ArrowLeft, Check, ChevronRight, Settings, Sparkles, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { show as showEvent } from '@/actions/App/Http/Controllers/EventController';
import { show as showGroup } from '@/actions/App/Http/Controllers/GroupController';
import { close as closePoll, edit as editPoll, fix as fixPoll, reopen as reopenPoll } from '@/actions/App/Http/Controllers/PollController';
import { store as storeResponse } from '@/actions/App/Http/Controllers/PollResponseController';
import AvatarStack from '@/components/app/avatar-stack.vue';
import DateBlock from '@/components/app/date-block.vue';
import EmptyState from '@/components/app/empty-state.vue';
import PollGrid from '@/components/app/poll-grid.vue';
import { describeOption, formatDate } from '@/components/app/poll/dates';
import type { PollAnswer, PollAnswers, PollGridOption, PollGridParticipant, PollKey, PollOptionTotals } from '@/components/app/poll/types';
import UserAvatar from '@/components/app/user-avatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ page: App.Data.PollShowData }>();

const { t, locale } = useI18n();
const currentUserId = computed(() => usePage().props.auth.user?.id ?? null);

const poll = computed(() => props.page.poll);
const permissions = computed(() => props.page.permissions);
const isDateFinder = computed(() => poll.value.type === 'date_finder');
const isOpen = computed(() => !poll.value.is_closed);

const backLink = computed(() => {
    if (poll.value.source_event) {
        return { href: showEvent(poll.value.source_event.slug).url, name: poll.value.source_event.title };
    }

    return { href: showGroup(poll.value.group_slug).url, name: poll.value.group_name };
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

// Option slugs are the grid's column keys
const gridOptions = computed<PollGridOption[]>(() =>
    poll.value.options.map((option) => ({
        id: option.slug,
        date: option.date,
        start: option.starts_at,
        end: option.ends_at,
        label: option.label,
        fixed: option.is_fixed,
    })),
);

const totals = computed<Record<string, PollOptionTotals>>(() =>
    Object.fromEntries(poll.value.options.map((option) => [option.slug, { yes: option.yes_count, maybe: option.maybe_count, no: option.no_count }])),
);

const answers = computed<PollAnswers>(() =>
    Object.fromEntries(props.page.respondents.map((respondent) => [String(respondent.user_id), respondent.answers as Record<string, PollAnswer>])),
);

const hasAnswered = (respondent: App.Data.PollRespondentData): boolean => Object.keys(respondent.answers).length > 0;

const answered = computed(() => props.page.respondents.filter(hasAnswered));
const missing = computed(() => props.page.respondents.filter((respondent) => !hasAnswered(respondent)));

// The own row always comes first so it can be answered; others only appear once they answered
const rows = computed<PollGridParticipant[]>(() => {
    const own = props.page.respondents.find((respondent) => respondent.user_id === currentUserId.value);
    const others = answered.value
        .filter((respondent) => respondent.user_id !== currentUserId.value)
        .sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''));

    return [...(own ? [own] : []), ...others].map((respondent) => ({ id: respondent.user_id, name: respondent.name }));
});

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

function close(): void {
    router.post(closePoll(poll.value.slug).url, {}, { preserveScroll: true, onError: showRequestError });
}

function reopen(): void {
    router.delete(reopenPoll(poll.value.slug).url, { preserveScroll: true, onError: showRequestError });
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
            accepted: props.page.respondents.filter((respondent) => respondent.answers[option.slug] === 'yes').map((respondent) => ({ id: respondent.user_id, name: respondent.name })),
            pending: props.page.respondents.filter((respondent) => respondent.answers[option.slug] === 'maybe').map((respondent) => ({ id: respondent.user_id, name: respondent.name })),
        })),
);

const fixLabel = computed(() => {
    if (selected.value.length === 0) {
        return t('poll.fix.confirmNone');
    }

    return selected.value.length === 1 ? t('poll.fix.confirmOne') : t('poll.fix.confirmMany', { n: selected.value.length });
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

function optionWhen(option: { date: string; starts_at: string | null; ends_at: string | null }): string {
    const day = formatDate(option.date, locale.value, { weekday: 'long', day: 'numeric', month: 'long' });

    if (!option.starts_at) {
        return day;
    }

    return option.ends_at ? `${day}, ${option.starts_at}–${option.ends_at}` : `${day}, ${option.starts_at}`;
}

// Live updates (F-21): every change reloads the page prop; a deleted poll sends people back
useEcho<{ deleted?: boolean }>(`poll.${props.page.poll.id}`, ['.poll.changed', '.poll.response.updated', '.poll.option.fixed'], (payload) => {
    if (payload.deleted) {
        router.visit(backLink.value.href);

        return;
    }

    router.reload({ only: ['page'] });
});

type Viewer = { id: number; name: string };
const viewers = ref<Viewer[]>([]);
const { channel: presenceChannel } = usePresenceChannel(`presence.poll.${props.page.poll.id}`);

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

const otherViewers = computed(() => viewers.value.filter((viewer) => viewer.id !== currentUserId.value));

const buttonClass = 'h-(--control-height) px-4';
</script>

<template>
    <Head :title="poll.title" />

    <div class="flex flex-col gap-10">
        <header class="flex flex-col gap-[18px]" :data-feature="featureId">
            <Link :href="backLink.href" class="text-fg-muted hover:text-fg inline-flex w-fit items-center gap-1.5 rounded-sm text-sm font-semibold">
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('poll.back', { name: backLink.name }) }}
            </Link>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 flex-col gap-2.5">
                    <h1 class="text-3xl break-words">{{ poll.title }}</h1>
                    <div class="text-fg-muted flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                        <Badge :variant="status.variant">{{ status.label }}</Badge>
                        <Badge variant="outline">{{ isDateFinder ? t('group.polls.dateFinder') : t('group.polls.attendance') }}</Badge>
                        <Badge variant="outline">{{ poll.uses_time_slots ? t('newPoll.mode.slots') : t('newPoll.mode.day') }}</Badge>
                        <Link :href="showGroup(poll.group_slug).url" class="hover:text-fg inline-flex items-center gap-1.5 rounded-sm font-semibold">
                            <Users class="size-4" aria-hidden="true" />
                            {{ poll.group_name }}
                        </Link>
                        <span class="inline-flex items-center gap-1.5">
                            <UserAvatar :name="poll.organizer_name" size="sm" />
                            {{ t('poll.by', { name: poll.organizer_name ?? t('common.noName') }) }}
                        </span>
                    </div>
                </div>
                <div v-if="permissions.can_edit || permissions.can_fix" class="flex flex-wrap gap-2.5" data-feature="F-08">
                    <Button v-if="isDateFinder && permissions.can_fix" size="lg" :class="buttonClass" data-feature="F-09" @click="openFix">
                        <Sparkles aria-hidden="true" />
                        {{ t('poll.actions.fix') }}
                    </Button>
                    <Button v-if="permissions.can_close" variant="secondary" size="lg" :class="buttonClass" @click="close">
                        {{ t('poll.actions.close') }}
                    </Button>
                    <Button v-if="permissions.can_reopen" variant="secondary" size="lg" :class="buttonClass" @click="reopen">
                        {{ t('poll.actions.reopen') }}
                    </Button>
                    <Button v-if="permissions.can_edit" as-child variant="ghost" size="lg" :class="buttonClass">
                        <Link :href="editPoll(poll.slug).url">
                            <Settings aria-hidden="true" />
                            {{ t('poll.edit.title') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <div v-if="poll.source_event" class="bg-primary-soft text-primary-soft-fg flex flex-wrap items-center gap-3 rounded-md px-3.5 py-3 text-sm" data-feature="F-12">
                <Users class="size-6 flex-none" aria-hidden="true" />
                <span class="flex min-w-0 flex-1 flex-col gap-px">
                    <b class="font-semibold">{{ t('newPoll.audienceEvent', { event: poll.source_event.title }) }}</b>
                    <span>{{ t('newPoll.audienceEventCount', { n: page.audience_count }) }}</span>
                </span>
                <Button as-child variant="secondary" size="sm">
                    <Link :href="showEvent(poll.source_event.slug).url">
                        {{ t('poll.openEvent') }}
                        <ChevronRight aria-hidden="true" />
                    </Link>
                </Button>
            </div>

            <div v-if="!isOpen" class="bg-success-soft flex gap-3 rounded-md px-5 py-3.5 text-[15px]" role="status" data-feature="F-08">
                <Check class="text-success mt-px size-5 flex-none" aria-hidden="true" />
                <span>{{ page.fixed_events.length ? t('poll.closedFixedNote') : t('poll.closedNote') }}</span>
            </div>
            <p v-else-if="!isDateFinder" class="text-fg-soft text-sm" data-feature="F-07">{{ t('poll.attendanceHint') }}</p>

            <div v-if="otherViewers.length" class="text-fg-muted flex flex-wrap items-center gap-2.5 text-sm" data-feature="F-21">
                <AvatarStack :people="otherViewers" :max="5" />
                <span>{{ t('poll.presence') }}</span>
            </div>
        </header>

        <section class="flex min-w-0 flex-col gap-4" aria-labelledby="grid-title" :data-feature="isDateFinder ? 'F-06' : 'F-07'">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="grid-title" class="flex items-center gap-2.5 text-xl">
                    {{ t('poll.grid') }}
                    <span v-if="answered.length" class="text-fg-soft font-sans text-sm font-semibold tabular-nums">{{ answered.length }}</span>
                </h2>
                <span class="text-fg-soft text-sm">{{ t('group.polls.responses', { n: answered.length, total: page.audience_count }) }}</span>
            </div>

            <PollGrid
                :id-prefix="`poll-${poll.slug}`"
                :options="gridOptions"
                :participants="rows"
                :answers="answers"
                :totals="totals"
                :best-option-ids="isDateFinder && isOpen ? page.best_option_slugs : []"
                :show-no-totals="isDateFinder && isOpen && page.show_no_counts"
                :current-participant-id="currentUserId"
                :closed="!permissions.can_respond"
                :busy="saving"
                @answer="answer"
            />

            <EmptyState v-if="!answered.length" :title="t('poll.empty.title')" :text="t('poll.empty.text')" />
            <div v-else-if="missing.length" class="text-fg-muted flex flex-wrap items-center gap-x-3 gap-y-2.5 text-sm">
                <span class="text-fg-soft font-semibold">{{ t('poll.missing') }}</span>
                <AvatarStack :people="missing.map((person) => ({ id: person.user_id, name: person.name }))" :max="6" />
                <span>{{ missing.map((person) => person.name ?? t('common.noName')).join(', ') }}</span>
            </div>
        </section>

        <section v-if="page.fixed_events.length" class="flex flex-col gap-4" aria-labelledby="fixed-title" data-feature="F-09">
            <h2 id="fixed-title" class="flex items-center gap-2.5 text-xl">
                {{ t('poll.events') }}
                <span class="text-fg-soft font-sans text-sm font-semibold tabular-nums">{{ page.fixed_events.length }}</span>
            </h2>
            <ul class="flex flex-col gap-2.5">
                <li v-for="event in page.fixed_events" :key="event.slug">
                    <Link
                        :href="showEvent(event.slug).url"
                        class="box hover:border-border-strong focus-visible:outline-focus-ring flex items-center gap-3.5 px-4 py-3.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 sm:px-[22px]"
                    >
                        <DateBlock :date="event.date" fixed />
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="truncate font-semibold">{{ event.title }}</span>
                            <span class="text-fg-muted text-sm">{{ optionWhen({ date: event.date, starts_at: event.starts_at, ends_at: null }) }}</span>
                        </span>
                        <ChevronRight class="text-fg-soft size-5 flex-none" aria-hidden="true" />
                    </Link>
                </li>
            </ul>
        </section>

        <Dialog v-model:open="fixOpen">
            <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ t('poll.fix.title') }}</DialogTitle>
                    <DialogDescription>{{ t('poll.fix.sub') }}</DialogDescription>
                </DialogHeader>

                <fieldset class="flex flex-col gap-2" data-feature="F-09">
                    <legend class="text-fg mb-2 text-sm font-semibold">{{ t('poll.fix.options') }}</legend>
                    <label
                        v-for="option in poll.options"
                        :key="option.slug"
                        :class="[
                            'box has-focus-visible:outline-focus-ring flex flex-wrap items-center gap-3 rounded-md px-3.5 py-2.5 has-focus-visible:outline-2 has-focus-visible:outline-offset-2',
                            option.is_fixed ? 'opacity-70' : 'has-checked:border-primary has-checked:bg-primary-soft cursor-pointer',
                        ]"
                    >
                        <input v-model="selected" type="checkbox" class="accent-primary size-5 flex-none" :value="option.slug" :disabled="option.is_fixed" />
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="font-semibold">{{ describeOption({ date: option.date, start: option.starts_at, end: option.ends_at, label: option.label }, locale) }}</span>
                            <span class="text-fg-muted text-[13px]">
                                {{ t('poll.totals.yes', { n: option.yes_count }) }}, {{ t('poll.totals.maybe', { n: option.maybe_count }) }}, {{ t('poll.totals.no', { n: option.no_count }) }}
                            </span>
                        </span>
                        <Badge v-if="!option.is_fixed && page.best_option_slugs.includes(option.slug)">{{ t('poll.best') }}</Badge>
                        <Badge v-if="option.is_fixed" variant="secondary">{{ t('poll.fix.alreadyFixed') }}</Badge>
                    </label>
                </fieldset>

                <div class="bg-surface-muted flex flex-col gap-3 rounded-md p-3.5" aria-live="polite">
                    <p v-if="!fixPreview.length" class="text-fg-muted">{{ t('poll.fix.previewNone') }}</p>
                    <template v-else>
                        <p>
                            <b>{{ fixPreview.length === 1 ? t('poll.fix.previewOne') : t('poll.fix.previewMany', { n: fixPreview.length }) }}</b>
                            {{ t('poll.fix.derived') }}
                        </p>
                        <ul class="flex flex-col gap-2.5">
                            <li v-for="entry in fixPreview" :key="entry.option.slug" class="flex items-start gap-3">
                                <DateBlock :date="entry.option.date" fixed />
                                <span class="flex min-w-0 flex-col gap-1">
                                    <span class="font-semibold">{{ poll.title }}</span>
                                    <span class="text-fg-muted text-[13px]">{{ optionWhen(entry.option) }}</span>
                                    <span class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13px]">
                                        <span v-if="entry.accepted.length" class="inline-flex items-center gap-2">
                                            <AvatarStack :people="entry.accepted" :max="5" />
                                            {{ t('group.events.accepted', { n: entry.accepted.length }) }}
                                        </span>
                                        <span v-if="entry.pending.length" class="inline-flex items-center gap-2">
                                            <AvatarStack :people="entry.pending" :max="5" />
                                            {{ t('group.events.pending', { n: entry.pending.length }) }}
                                        </span>
                                        <span v-if="!entry.accepted.length && !entry.pending.length" class="text-fg-soft">{{ t('poll.fix.noParticipants') }}</span>
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </template>
                </div>

                <DialogFooter>
                    <Button variant="outline" size="lg" :class="buttonClass" @click="fixOpen = false">{{ t('dialog.cancel') }}</Button>
                    <Button size="lg" :class="buttonClass" :disabled="!selected.length || fixing" data-feature="F-09" @click="confirmFix">{{ fixLabel }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
