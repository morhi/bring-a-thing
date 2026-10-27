<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    useConnectionStatus,
    useEcho,
    usePresenceChannel,
} from '@laravel/echo-vue';
import {
    ArrowLeft,
    CalendarCheck,
    CalendarDays,
    Check,
    Clock,
    Link2,
    ListChecks,
    Plus,
    Settings,
    Users,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { show as showEvent } from '@/actions/App/Http/Controllers/EventController';
import { show as showGroup } from '@/actions/App/Http/Controllers/GroupController';
import { show as showPoll } from '@/actions/App/Http/Controllers/PollController';
import {
    destroy as destroyListComment,
    store as storeListComment,
} from '@/actions/App/Http/Controllers/RosterCommentController';
import { edit as editRoster } from '@/actions/App/Http/Controllers/RosterController';
import {
    destroy as releaseClaim,
    store as storeClaim,
} from '@/actions/App/Http/Controllers/RosterItemClaimController';
import {
    destroy as destroyItemComment,
    store as storeItemComment,
} from '@/actions/App/Http/Controllers/RosterItemCommentController';
import {
    destroy as destroyItem,
    store as storeItem,
    update as updateItem,
} from '@/actions/App/Http/Controllers/RosterItemController';
import AvatarStack from '@/components/app/avatar-stack.vue';
import type {
    ClaimCandidate,
    ClaimEntry,
    ClaimId,
    ClaimRequest,
} from '@/components/app/claim-controls.vue';
import CommentThread from '@/components/app/comment-thread.vue';
import type { ThreadComment } from '@/components/app/comment-thread.vue';
import DatePicker from '@/components/app/date-picker.vue';
import EmptyState from '@/components/app/empty-state.vue';
import FormField from '@/components/app/form-field.vue';
import ItemRow from '@/components/app/item-row.vue';
import { formatDate } from '@/components/app/poll/dates';
import ProgressBar from '@/components/app/progress-bar.vue';
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
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOptGroup,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    attendanceDayLabel,
    groupItemsByDate,
    itemFields,
    relativeDay,
} from '@/lib/roster-items';
import { dashboard } from '@/routes';

defineOptions({ layout: AppLayout });

/**
 * List page: items with claims, comments and the attendance day (F-13 to F-21).
 */
const props = defineProps<{ page: App.Data.RosterShowData }>();

const { t, locale } = useI18n();
const inertiaPage = usePage();

const roster = computed(() => props.page.roster);
const permissions = computed(() => props.page.permissions);
const userId = computed(() => inertiaPage.props.auth.user?.id ?? null);

const back = computed(() => {
    if (roster.value.event) {
        return {
            href: showEvent(roster.value.event.slug).url,
            label: t('list.back.event', { event: roster.value.event.title }),
        };
    }

    if (roster.value.group) {
        return {
            href: showGroup(roster.value.group.slug).url,
            label: t('list.back.group', { group: roster.value.group.title }),
        };
    }

    return { href: dashboard().url, label: t('nav.backToDashboard') };
});

const listDate = computed(() =>
    roster.value.date
        ? formatDate(roster.value.date, locale.value, {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : null,
);

// The poll title rides in every option, because the closed select and screen readers show the option text alone
function attendanceOptionLabel(
    pollTitle: string,
    option: App.Data.AttendanceOptionData,
): string {
    return `${pollTitle}: ${attendanceDayLabel(option, locale.value)}`;
}

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? t('list.notFound'));
}

// Progress and grouping

const items = computed(() => props.page.items);
const doneCount = computed(
    () => items.value.filter((item) => item.is_fully_claimed).length,
);
const groups = computed(() => groupItemsByDate(items.value, locale.value));
const grouped = computed(
    () => groups.value.length > 1 || groups.value[0]?.key !== 'all',
);

// Attendance day (F-18): an item's own day overrides the list's, so only a differing day shows on the item

function attendanceWarningFor(
    day: App.Data.AttendanceDayData | null,
    personId: number | null,
    name: string | null,
): string | null {
    if (
        !day ||
        personId === null ||
        day.available_user_ids.includes(personId)
    ) {
        return null;
    }

    const date = attendanceDayLabel(
        { date: day.date, starts_at: null, label: null },
        locale.value,
    );
    const said = day.unavailable_user_ids.includes(personId);

    if (name === null) {
        return said
            ? t('list.attendance.youNo', { date })
            : t('list.attendance.youNone', { date });
    }

    return said
        ? t('list.attendance.otherNo', { name, date })
        : t('list.attendance.otherNone', { name, date });
}

function itemDay(
    item: App.Data.RosterItemData,
): { label: string; href: string } | null {
    const day = item.attendance_day;

    if (!day || day.option_id === roster.value.attendance_day?.option_id) {
        return null;
    }

    return {
        label: attendanceDayLabel(day, locale.value),
        href: showPoll(day.poll_slug).url,
    };
}

// Claims (F-15)

const claimErrors = reactive<Record<number, string>>({});
const claimingItem = ref<number | null>(null);

function claimsOf(item: App.Data.RosterItemData): ClaimEntry[] {
    return item.claims.map((claim) => {
        const isOwn = claim.user_id === userId.value;

        return {
            id: claim.user_id ?? claim.user_name,
            accountId: claim.user_id,
            name: claim.user_name,
            quantity: claim.quantity,
            isOwn,
            canRemove: isOwn || permissions.value.can_manage_claims,
        };
    });
}

function candidatesFor(item: App.Data.RosterItemData): ClaimCandidate[] {
    const taken = item.claims.map((claim) => claim.user_id);

    return props.page.claim_targets
        .filter(
            (target) =>
                target.user_id !== userId.value &&
                !taken.includes(target.user_id),
        )
        .map((target) => {
            const name = target.name ?? t('common.noName');

            return {
                id: target.user_id,
                name,
                warning: attendanceWarningFor(
                    item.attendance_day,
                    target.user_id,
                    name,
                ),
            };
        });
}

function ownWarning(item: App.Data.RosterItemData): string | null {
    return item.attendance_warning
        ? attendanceWarningFor(item.attendance_day, userId.value, null)
        : null;
}

function claim(item: App.Data.RosterItemData, request: ClaimRequest): void {
    delete claimErrors[item.id];
    claimingItem.value = item.id;

    const data: Record<string, number> = {};

    if (request.quantity !== null) {
        data.quantity = request.quantity;
    }

    if (request.accountId !== null) {
        data.user_id = Number(request.accountId);
    }

    router.post(storeClaim([roster.value.slug, item.slug]).url, data, {
        preserveScroll: true,
        onError: (errors) => {
            claimErrors[item.id] = Object.values(errors)[0] ?? '';
            showError(errors);
        },
        onFinish: () => (claimingItem.value = null),
    });
}

function release(item: App.Data.RosterItemData, claimId: ClaimId): void {
    claimingItem.value = item.id;

    router.delete(releaseClaim([roster.value.slug, item.slug]).url, {
        data: claimId === userId.value ? {} : { user_id: Number(claimId) },
        preserveScroll: true,
        onError: showError,
        onFinish: () => (claimingItem.value = null),
    });
}

// Comments (F-20): one thread for the list, one per item

const openThreads = reactive<Record<number, boolean>>({});
const drafts = reactive<Record<string, string>>({});
const commentErrors = reactive<Record<string, string>>({});
const postingThread = ref<string | null>(null);

function threadOf(comments: App.Data.CommentData[]): ThreadComment[] {
    return comments.map((comment) => ({
        id: comment.id,
        authorName: comment.user_name,
        createdAt: comment.created_at,
        body: comment.body,
        canDelete: comment.can_delete,
    }));
}

function postComment(key: string, url: string, body: string): void {
    delete commentErrors[key];
    postingThread.value = key;

    router.post(
        url,
        { body },
        {
            preserveScroll: true,
            onSuccess: () => (drafts[key] = ''),
            onError: (errors) =>
                (commentErrors[key] =
                    errors.body ?? Object.values(errors)[0] ?? ''),
            onFinish: () => (postingThread.value = null),
        },
    );
}

function deleteComment(url: string): void {
    router.delete(url, { preserveScroll: true, onError: showError });
}

// Item dialog (F-14, F-16, F-18)

const itemDialogOpen = ref(false);
const editingItem = ref<App.Data.RosterItemData | null>(null);
const itemForm = useForm({
    name: '',
    quantity: '' as string | number,
    unit: '',
    notes: '',
    date: null as string | null,
    attendance_poll_option_id: '' as string | number,
    custom_fields: {} as Record<number, string>,
});

const attendanceGroups = computed(() => {
    const byPoll = new Map<string, App.Data.AttendanceOptionData[]>();

    for (const option of props.page.attendance_options) {
        byPoll.set(option.poll_title, [
            ...(byPoll.get(option.poll_title) ?? []),
            option,
        ]);
    }

    return [...byPoll.entries()].map(([title, options]) => ({
        title,
        options,
    }));
});

function openItemDialog(item: App.Data.RosterItemData | null = null): void {
    editingItem.value = item;
    itemForm.clearErrors();
    itemForm.name = item?.name ?? '';
    itemForm.quantity = item?.quantity ?? '';
    itemForm.unit = item?.unit ?? '';
    itemForm.notes = item?.notes ?? '';
    itemForm.date = item?.date ?? null;
    itemForm.attendance_poll_option_id =
        item?.attendance_day &&
        item.attendance_day.option_id !== roster.value.attendance_day?.option_id
            ? item.attendance_day.option_id
            : '';
    itemForm.custom_fields = Object.fromEntries(
        props.page.custom_fields.map((field) => [
            field.id,
            item?.custom_field_values.find(
                (value) => value.custom_field_id === field.id,
            )?.value ?? '',
        ]),
    );
    itemDialogOpen.value = true;
}

function saveItem(): void {
    const hasQuantity = itemForm.quantity !== '' && itemForm.quantity !== null;

    itemForm.transform((data) => ({
        ...data,
        quantity: hasQuantity ? Number(data.quantity) : null,
        unit: hasQuantity && data.unit.trim() ? data.unit.trim() : null,
        notes: data.notes.trim() || null,
        attendance_poll_option_id:
            data.attendance_poll_option_id === ''
                ? null
                : Number(data.attendance_poll_option_id),
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => (itemDialogOpen.value = false),
    };

    if (editingItem.value) {
        itemForm.patch(
            updateItem([roster.value.slug, editingItem.value.slug]).url,
            options,
        );

        return;
    }

    itemForm.post(storeItem(roster.value.slug).url, options);
}

const deleteTarget = ref<App.Data.RosterItemData | null>(null);
const deleteOpen = ref(false);

function askDeleteItem(item: App.Data.RosterItemData): void {
    itemDialogOpen.value = false;
    deleteTarget.value = item;
    deleteOpen.value = true;
}

function confirmDeleteItem(): void {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(
        destroyItem([roster.value.slug, deleteTarget.value.slug]).url,
        { preserveScroll: true, onError: showError },
    );
    deleteOpen.value = false;
}

// Live updates and presence (F-21)

useEcho(
    `roster.${props.page.roster.id}`,
    ['.item.changed', '.claim.changed', '.comment.added', '.comment.deleted'],
    () => {
        router.reload({ only: ['page'] });
    },
);

type Viewer = { id: number; name: string };
const viewers = ref<Viewer[]>([]);
const { channel: presenceChannel } = usePresenceChannel(
    `presence.roster.${props.page.roster.id}`,
);

presenceChannel()
    .here((users: Viewer[]) => (viewers.value = users))
    .joining((user: Viewer) => {
        if (viewers.value.some((viewer) => viewer.id === user.id)) {
            return;
        }

        viewers.value = [...viewers.value, user];
    })
    .leaving(
        (user: Viewer) =>
            (viewers.value = viewers.value.filter(
                (viewer) => viewer.id !== user.id,
            )),
    );

const otherViewers = computed(() =>
    viewers.value.filter((viewer) => viewer.id !== userId.value),
);

// Section 8: after a lost connection the list refreshes once it is back
const connection = useConnectionStatus();
let wasConnected = false;

watch(connection, (status) => {
    if (status !== 'connected') {
        return;
    }

    if (wasConnected) {
        router.reload({ only: ['page'] });
    }

    wasConnected = true;
});

const buttonClass = 'h-(--control-height) px-4';
const badgeLinkClass =
    'border-border text-fg-muted hover:border-primary hover:text-primary inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[13px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus-ring';
const chipClass =
    'border-border text-fg-muted inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[13px] font-semibold';
const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) rounded-(--control-radius) px-3.5 text-[15px]';
</script>

<template>
    <Head :title="roster.title" />

    <div class="flex flex-col gap-10">
        <header class="flex flex-col gap-[18px]" data-feature="F-13">
            <Link
                :href="back.href"
                class="text-fg-muted hover:text-fg inline-flex w-fit items-center gap-1.5 rounded-sm text-sm font-semibold"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ back.label }}
            </Link>

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 flex-[1_1_320px] flex-col gap-2.5">
                    <h1 class="text-3xl break-words">{{ roster.title }}</h1>
                    <p
                        v-if="roster.description"
                        class="text-fg-muted max-w-[65ch] text-[17px] break-words whitespace-pre-line"
                    >
                        {{ roster.description }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <span v-if="listDate" :class="chipClass">
                            <CalendarDays class="size-3.5" aria-hidden="true" />
                            {{ listDate }}
                        </span>
                        <Badge
                            v-if="roster.date && roster.is_past"
                            class="bg-surface-muted text-fg-muted"
                            >{{ t('list.past') }}</Badge
                        >
                        <Badge
                            v-else-if="roster.date"
                            class="bg-primary-soft text-primary-soft-fg"
                            >{{ relativeDay(roster.date) }}</Badge
                        >
                        <Link
                            v-if="roster.event"
                            :href="showEvent(roster.event.slug).url"
                            :class="badgeLinkClass"
                        >
                            <CalendarDays class="size-3.5" aria-hidden="true" />
                            {{
                                t('list.placement.event', {
                                    event: roster.event.title,
                                })
                            }}
                        </Link>
                        <Link
                            v-else-if="roster.group"
                            :href="showGroup(roster.group.slug).url"
                            :class="badgeLinkClass"
                        >
                            <Users class="size-3.5" aria-hidden="true" />
                            {{
                                t('list.placement.group', {
                                    group: roster.group.title,
                                })
                            }}
                        </Link>
                        <span v-else :class="chipClass">
                            <ListChecks class="size-3.5" aria-hidden="true" />
                            {{ t('list.placement.standalone') }}
                        </span>
                        <Link
                            v-if="roster.attendance_day"
                            :href="
                                showPoll(roster.attendance_day.poll_slug).url
                            "
                            :class="badgeLinkClass"
                            data-feature="F-18"
                        >
                            <CalendarCheck
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{
                                t('list.attendance.day', {
                                    date: attendanceDayLabel(
                                        roster.attendance_day,
                                        locale,
                                    ),
                                })
                            }}
                        </Link>
                        <span
                            v-if="roster.share_url"
                            :class="chipClass"
                            data-feature="F-19"
                        >
                            <Link2 class="size-3.5" aria-hidden="true" />
                            {{ t('dash.lists.shared') }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="permissions.can_edit"
                        as-child
                        variant="outline"
                        :class="buttonClass"
                    >
                        <Link :href="editRoster(roster.slug).url">
                            <Settings aria-hidden="true" />
                            {{ t('list.edit.title') }}
                        </Link>
                    </Button>
                    <Button
                        v-if="permissions.can_add_items && items.length"
                        :class="buttonClass"
                        data-feature="F-14"
                        @click="openItemDialog()"
                    >
                        <Plus aria-hidden="true" />
                        {{ t('list.item.add') }}
                    </Button>
                </div>
            </div>

            <div
                v-if="items.length || otherViewers.length"
                class="flex flex-wrap items-center gap-x-6 gap-y-3"
            >
                <span
                    v-if="items.length"
                    class="text-fg-muted flex min-w-[220px] flex-[0_1_360px] items-center gap-2.5 text-sm tabular-nums"
                >
                    <ProgressBar
                        :done="doneCount"
                        :total="items.length"
                        :label="t('list.items.title')"
                    />
                    {{
                        t('dash.lists.claimed', {
                            done: doneCount,
                            total: items.length,
                        })
                    }}
                </span>
                <span
                    v-if="otherViewers.length"
                    class="text-fg-muted inline-flex items-center gap-2 text-sm"
                    data-feature="F-21"
                >
                    <AvatarStack :people="otherViewers" :max="4" />
                    {{
                        t('list.viewers', {
                            names: otherViewers
                                .map((viewer) => viewer.name)
                                .join(', '),
                        })
                    }}
                </span>
            </div>

            <div
                v-if="permissions.needs_acceptance && roster.event"
                class="border-warning bg-warning-soft text-fg flex gap-3 rounded-xl border p-4 text-sm"
                role="status"
                data-feature="F-15"
            >
                <Clock
                    class="text-warning mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div class="flex flex-col items-start gap-2.5">
                    <span>{{
                        t('list.pending.note', { event: roster.event.title })
                    }}</span>
                    <Button as-child variant="secondary" size="sm">
                        <Link :href="showEvent(roster.event.slug).url">{{
                            t('list.pending.openEvent')
                        }}</Link>
                    </Button>
                </div>
            </div>
            <p
                v-else-if="roster.event"
                class="text-fg-muted text-sm"
                data-feature="F-13"
            >
                {{ t('list.event.note', { event: roster.event.title }) }}
            </p>

            <div
                v-if="page.is_all_taken"
                class="bg-primary-soft text-primary-soft-fg flex gap-3 rounded-xl p-4 text-sm"
                role="status"
            >
                <Check class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                <span
                    ><b>{{ t('list.allTaken.title') }}</b>
                    {{ t('list.allTaken.text') }}</span
                >
            </div>
        </header>

        <section
            v-if="items.length"
            class="flex flex-col gap-6"
            aria-labelledby="items-title"
        >
            <h2 id="items-title" class="sr-only">
                {{ t('list.items.title') }}
            </h2>
            <div
                v-for="group in groups"
                :key="group.key"
                class="flex flex-col gap-3"
            >
                <h3
                    v-if="grouped"
                    class="text-fg-muted flex items-center gap-2 text-sm font-semibold"
                >
                    <CalendarDays class="size-4" aria-hidden="true" />
                    {{ group.label }}
                </h3>
                <ul class="flex flex-col gap-3">
                    <li v-for="item in group.items" :key="item.id">
                        <ItemRow
                            v-model:comments-open="openThreads[item.id]"
                            :item="{
                                id: item.id,
                                name: item.name,
                                quantity: item.quantity,
                                unit: item.unit,
                                notes: item.notes,
                                fields: itemFields(item, page.custom_fields),
                                attendanceDay: itemDay(item),
                            }"
                            :claims="claimsOf(item)"
                            :can-claim="item.can_claim"
                            :locked="permissions.needs_acceptance"
                            :candidates="
                                permissions.can_manage_claims
                                    ? candidatesFor(item)
                                    : []
                            "
                            :attendance-warning="ownWarning(item)"
                            :error="claimErrors[item.id] ?? null"
                            :processing="claimingItem === item.id"
                            :comment-count="item.comments.length"
                            can-comment
                            :can-edit="permissions.can_edit_items"
                            @claim="claim(item, $event)"
                            @release="release(item, $event)"
                            @edit="openItemDialog(item)"
                            @delete="askDeleteItem(item)"
                        >
                            <template #comments>
                                <CommentThread
                                    v-model="drafts[`item-${item.id}`]"
                                    :comments="threadOf(item.comments)"
                                    compact
                                    :error="
                                        commentErrors[`item-${item.id}`] ?? null
                                    "
                                    :processing="
                                        postingThread === `item-${item.id}`
                                    "
                                    @submit="
                                        postComment(
                                            `item-${item.id}`,
                                            storeItemComment([
                                                roster.slug,
                                                item.slug,
                                            ]).url,
                                            $event,
                                        )
                                    "
                                    @delete="
                                        deleteComment(
                                            destroyItemComment([
                                                roster.slug,
                                                item.slug,
                                                Number($event),
                                            ]).url,
                                        )
                                    "
                                />
                            </template>
                        </ItemRow>
                    </li>
                </ul>
            </div>
        </section>
        <EmptyState
            v-else
            :title="t('list.empty.title')"
            :text="
                permissions.can_add_items
                    ? t('list.empty.textAdd')
                    : t('list.empty.textWait')
            "
            :icon="ListChecks"
        >
            <Button
                v-if="permissions.can_add_items"
                size="lg"
                :class="buttonClass"
                data-feature="F-14"
                @click="openItemDialog()"
            >
                <Plus aria-hidden="true" />
                {{ t('list.empty.add') }}
            </Button>
        </EmptyState>

        <section
            class="flex flex-col gap-4"
            aria-labelledby="list-comments-title"
            data-feature="F-20"
        >
            <h2
                id="list-comments-title"
                class="flex items-center gap-2.5 text-xl"
            >
                {{ t('list.comments.title') }}
                <span
                    v-if="page.comments.length"
                    class="text-fg-soft font-sans text-sm font-semibold tabular-nums"
                    >{{ page.comments.length }}</span
                >
            </h2>
            <div class="box p-5 sm:p-7">
                <CommentThread
                    v-model="drafts.list"
                    :comments="threadOf(page.comments)"
                    :error="commentErrors.list ?? null"
                    :processing="postingThread === 'list'"
                    @submit="
                        postComment(
                            'list',
                            storeListComment(roster.slug).url,
                            $event,
                        )
                    "
                    @delete="
                        deleteComment(
                            destroyListComment([roster.slug, Number($event)])
                                .url,
                        )
                    "
                />
            </div>
        </section>

        <Dialog v-model:open="itemDialogOpen">
            <DialogContent
                class="box-float max-h-[calc(100dvh-2rem)] overflow-y-auto p-6 sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">{{
                        editingItem
                            ? t('list.item.editTitle')
                            : t('list.item.addTitle')
                    }}</DialogTitle>
                    <DialogDescription class="sr-only">{{
                        roster.title
                    }}</DialogDescription>
                </DialogHeader>
                <form
                    id="item-form"
                    class="flex flex-col gap-(--space-stack)"
                    novalidate
                    data-feature="F-14"
                    @submit.prevent="saveItem"
                >
                    <FormField
                        :label="t('list.item.name')"
                        control="item-name"
                        required
                        :error="itemForm.errors.name"
                    >
                        <template #default="{ describedBy, invalid }">
                            <Input
                                id="item-name"
                                v-model="itemForm.name"
                                required
                                :placeholder="t('list.item.namePlaceholder')"
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                                :class="inputClass"
                            />
                        </template>
                    </FormField>
                    <div class="grid gap-(--space-stack) sm:grid-cols-2">
                        <FormField
                            :label="t('list.item.quantity')"
                            control="item-quantity"
                            :hint="t('list.item.quantityHint')"
                            :error="itemForm.errors.quantity"
                        >
                            <template #default="{ describedBy, invalid }">
                                <Input
                                    id="item-quantity"
                                    v-model="itemForm.quantity"
                                    type="number"
                                    min="0"
                                    step="any"
                                    inputmode="decimal"
                                    :aria-describedby="describedBy"
                                    :aria-invalid="invalid || undefined"
                                    :class="inputClass"
                                />
                            </template>
                        </FormField>
                        <FormField
                            :label="t('list.item.unit')"
                            control="item-unit"
                            :error="itemForm.errors.unit"
                        >
                            <template #default="{ describedBy, invalid }">
                                <Input
                                    id="item-unit"
                                    v-model="itemForm.unit"
                                    :placeholder="
                                        t('list.item.unitPlaceholder')
                                    "
                                    :disabled="
                                        itemForm.quantity === '' ||
                                        itemForm.quantity === null
                                    "
                                    :aria-describedby="describedBy"
                                    :aria-invalid="invalid || undefined"
                                    :class="inputClass"
                                />
                            </template>
                        </FormField>
                    </div>
                    <FormField
                        :label="t('list.item.notes')"
                        control="item-notes"
                        :error="itemForm.errors.notes"
                    >
                        <template #default="{ describedBy, invalid }">
                            <Textarea
                                id="item-notes"
                                v-model="itemForm.notes"
                                rows="2"
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                            />
                        </template>
                    </FormField>
                    <FormField
                        :label="t('list.item.date')"
                        control="item-date"
                        :hint="t('list.item.dateHint')"
                        :error="itemForm.errors.date"
                    >
                        <template #default="{ describedBy, invalid }">
                            <DatePicker
                                id="item-date"
                                v-model="itemForm.date"
                                :described-by="describedBy"
                                :invalid="invalid"
                            />
                        </template>
                    </FormField>
                    <FormField
                        v-for="field in page.custom_fields"
                        :key="field.id"
                        :label="field.name"
                        :control="`item-field-${field.id}`"
                        data-feature="F-16"
                    >
                        <template #default="{ id, describedBy }">
                            <Input
                                :id="id"
                                v-model="itemForm.custom_fields[field.id]"
                                :aria-describedby="describedBy"
                                :class="inputClass"
                            />
                        </template>
                    </FormField>
                    <FormField
                        v-if="attendanceGroups.length"
                        :label="t('list.attendance.itemLabel')"
                        control="item-attendance"
                        :hint="t('list.attendance.itemHint')"
                        :error="itemForm.errors.attendance_poll_option_id"
                        data-feature="F-18"
                    >
                        <template #default="{ describedBy, invalid }">
                            <NativeSelect
                                id="item-attendance"
                                v-model="itemForm.attendance_poll_option_id"
                                class="w-full"
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                            >
                                <NativeSelectOption value="">{{
                                    t('list.attendance.sameAsList')
                                }}</NativeSelectOption>
                                <NativeSelectOptGroup
                                    v-for="poll in attendanceGroups"
                                    :key="poll.title"
                                    :label="poll.title"
                                >
                                    <NativeSelectOption
                                        v-for="option in poll.options"
                                        :key="option.id"
                                        :value="option.id"
                                        >{{
                                            attendanceOptionLabel(
                                                poll.title,
                                                option,
                                            )
                                        }}</NativeSelectOption
                                    >
                                </NativeSelectOptGroup>
                            </NativeSelect>
                        </template>
                    </FormField>
                    <p
                        v-if="itemForm.errors.custom_fields"
                        class="text-danger text-sm"
                        role="alert"
                    >
                        {{ itemForm.errors.custom_fields }}
                    </p>
                </form>
                <DialogFooter class="flex-wrap gap-2">
                    <Button
                        v-if="editingItem && permissions.can_edit_items"
                        type="button"
                        variant="ghost"
                        class="text-danger sm:mr-auto"
                        @click="askDeleteItem(editingItem)"
                    >
                        {{ t('list.item.delete') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="itemDialogOpen = false"
                        >{{ t('dialog.cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        form="item-form"
                        :disabled="itemForm.processing"
                    >
                        {{
                            editingItem
                                ? t('list.item.save')
                                : t('list.item.add')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog v-model:open="deleteOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{
                        t('list.item.deleteTitle')
                    }}</AlertDialogTitle>
                    <AlertDialogDescription>{{
                        t('list.item.deleteText', {
                            name: deleteTarget?.name ?? '',
                        })
                    }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{
                        t('dialog.cancel')
                    }}</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        @click="confirmDeleteItem"
                        >{{ t('list.item.delete') }}</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
