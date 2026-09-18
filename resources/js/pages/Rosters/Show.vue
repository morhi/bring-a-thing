<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useEcho, usePresenceChannel } from '@laravel/echo-vue';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import RosterItemCard from '@/components/RosterItemCard.vue';
import CommentThread from '@/components/CommentThread.vue';
import { formatPollOption } from '@/lib/pollDate';
import type { Auth, Roster, RosterItem } from '@/types';
import {
    duplicate as duplicateRoster,
    edit as editRoster,
} from '@/actions/App/Http/Controllers/RosterController';
import { show as showGroup } from '@/actions/App/Http/Controllers/GroupController';
import {
    destroy as destroyItem,
    store as storeItem,
    update as updateItem,
} from '@/actions/App/Http/Controllers/RosterItemController';
import {
    destroy as destroyRosterComment,
    store as storeRosterComment,
} from '@/actions/App/Http/Controllers/RosterCommentController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    roster: Roster;
    canManage: boolean;
    canAddItems: boolean;
}>();

const page = usePage<{ auth: Auth }>();
const currentUserId = computed(() => page.props.auth.user?.id ?? null);
const confirm = useConfirm();

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString(undefined, {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function avatarLabel(
    name: string | null | undefined,
    email?: string | null,
): string {
    return (name ?? email ?? '?').charAt(0).toUpperCase();
}

function confirmDuplicate() {
    confirm.require({
        header: 'Duplicate roster?',
        message: `Create a new roster from "${props.roster.title}"? Things and custom fields are copied, claims are not.`,
        acceptLabel: 'Duplicate',
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () => router.post(duplicateRoster(props.roster).url),
    });
}

// --- Live items state, kept in sync via Reverb broadcasts ---
const items = ref<RosterItem[]>(props.roster.items ?? []);
watch(
    () => props.roster.items,
    (value) => {
        items.value = value ?? [];
    },
);

useEcho<{ item: RosterItem }>(
    `roster.${props.roster.id}`,
    '.item.saved',
    (e) => {
        const index = items.value.findIndex((item) => item.id === e.item.id);
        if (index === -1) {
            items.value = [...items.value, e.item];
        } else {
            items.value = items.value.map((item) =>
                item.id === e.item.id ? e.item : item,
            );
        }
    },
);

useEcho<{ id: number }>(`roster.${props.roster.id}`, '.item.deleted', (e) => {
    items.value = items.value.filter((item) => item.id !== e.id);
});

// --- Presence: who else is viewing this roster right now ---
type Viewer = { id: number; name: string };
const viewers = ref<Viewer[]>([]);
const { channel: presenceChannel } = usePresenceChannel(
    `presence.roster.${props.roster.id}`,
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

// --- Upcoming / past grouping; only split when at least one item carries an effective date ---
const hasDatedItems = computed(() =>
    items.value.some((item) => item.is_past !== null),
);
const upcomingItems = computed(() =>
    items.value.filter((item) => item.is_past !== true),
);
const pastItems = computed(() =>
    items.value.filter((item) => item.is_past === true),
);

// --- Add / edit item dialog ---
const showItemDialog = ref(false);
const editingItem = ref<RosterItem | null>(null);

const itemForm = useForm<{
    name: string;
    quantity: number | null;
    unit: string;
    notes: string;
    date: string | null;
    attendance_poll_option_id: number | null;
    custom_fields: Record<number, string>;
}>({
    name: '',
    quantity: null,
    unit: '',
    notes: '',
    date: null,
    attendance_poll_option_id: null,
    custom_fields: {},
});

function emptyCustomFieldValues(): Record<number, string> {
    return Object.fromEntries(
        (props.roster.custom_fields ?? []).map((field) => [field.id, '']),
    );
}

// --- Attendance-day link, offered only when the roster's group has an attendance poll ---
const attendanceDayOptions = computed(() =>
    (props.roster.group?.polls ?? []).flatMap((poll) =>
        (poll.options ?? []).map((option) => ({
            id: option.id,
            label: `${poll.title}: ${formatPollOption(option)}`,
        })),
    ),
);

function openAddItem() {
    editingItem.value = null;
    itemForm.reset();
    itemForm.clearErrors();
    itemForm.custom_fields = emptyCustomFieldValues();
    showItemDialog.value = true;
}

function openEditItem(item: RosterItem) {
    editingItem.value = item;
    itemForm.clearErrors();
    itemForm.name = item.name;
    itemForm.quantity = item.quantity !== null ? Number(item.quantity) : null;
    itemForm.unit = item.unit ?? '';
    itemForm.notes = item.notes ?? '';
    itemForm.date = item.date;
    itemForm.attendance_poll_option_id = item.attendance_poll_option_id;
    itemForm.custom_fields = Object.fromEntries(
        (props.roster.custom_fields ?? []).map((field) => [
            field.id,
            item.custom_field_values?.find(
                (value) => value.custom_field_id === field.id,
            )?.value ?? '',
        ]),
    );
    showItemDialog.value = true;
}

// Bridges itemForm.date's string type with PrimeVue's Date-only DatePicker
// typing; update-model-type="string" makes the runtime value a string
// regardless of what the component's types declare.
const itemDateModel = computed<Date>({
    get: () => itemForm.date as unknown as Date,
    set: (value) => {
        itemForm.date = value as unknown as string | null;
    },
});

function submitItem() {
    const onSuccess = () => {
        showItemDialog.value = false;
        editingItem.value = null;
    };

    if (editingItem.value) {
        itemForm.patch(
            updateItem({ roster: props.roster, item: editingItem.value }).url,
            { onSuccess },
        );
    } else {
        itemForm.post(storeItem(props.roster).url, { onSuccess });
    }
}

function confirmDeleteItem(item: RosterItem) {
    confirm.require({
        header: 'Remove thing?',
        message: `Remove "${item.name}" from this roster?`,
        acceptLabel: 'Remove',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () =>
            router.delete(destroyItem({ roster: props.roster, item }).url, {
                preserveScroll: true,
            }),
    });
}
</script>

<template>
    <Head :title="roster.title" />

    <div class="flex flex-col gap-6">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-2">
                <Link
                    v-if="roster.group"
                    :href="showGroup(roster.group).url"
                    class="text-surface-500 hover:text-surface-900 dark:hover:text-surface-0 text-sm"
                >
                    {{ roster.group.name }}
                </Link>
                <h1
                    class="text-surface-900 dark:text-surface-0 text-xl font-semibold"
                >
                    {{ roster.title }}
                </h1>
                <Tag v-if="roster.date" :value="formatDate(roster.date)" />
            </div>
            <div class="flex items-center gap-3">
                <div v-if="otherViewers.length" class="flex -space-x-2">
                    <Avatar
                        v-for="viewer in otherViewers.slice(0, 5)"
                        :key="viewer.id"
                        :label="avatarLabel(viewer.name)"
                        :title="viewer.name"
                        shape="circle"
                        size="small"
                        class="ring-surface-0 dark:ring-surface-900 ring-2"
                    />
                </div>
                <Button
                    label="Duplicate"
                    severity="secondary"
                    text
                    @click="confirmDuplicate"
                />
                <Link v-if="canManage" :href="editRoster(roster).url">
                    <Button label="Settings" severity="secondary" text />
                </Link>
            </div>
        </div>

        <p
            v-if="roster.description"
            class="text-surface-700 dark:text-surface-300 max-w-2xl text-sm"
        >
            {{ roster.description }}
        </p>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-medium">Things</h2>
                <Button
                    v-if="canAddItems"
                    label="Add thing"
                    size="small"
                    @click="openAddItem"
                />
            </div>

            <div
                v-if="items.length === 0"
                class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-8 text-center text-sm"
            >
                No things yet.
            </div>

            <template v-else-if="!hasDatedItems">
                <ul class="flex flex-col gap-4">
                    <li v-for="item in items" :key="item.id">
                        <RosterItemCard
                            :roster="roster"
                            :item="item"
                            :can-manage="canManage"
                            @edit="openEditItem"
                            @delete="confirmDeleteItem"
                        />
                    </li>
                </ul>
            </template>

            <template v-else>
                <div v-if="upcomingItems.length" class="mb-6">
                    <h3
                        class="text-surface-500 mb-3 text-sm font-medium tracking-wide uppercase"
                    >
                        Upcoming
                    </h3>
                    <ul class="flex flex-col gap-4">
                        <li v-for="item in upcomingItems" :key="item.id">
                            <RosterItemCard
                                :roster="roster"
                                :item="item"
                                :can-manage="canManage"
                                :date-label="
                                    item.date ? formatDate(item.date) : null
                                "
                                @edit="openEditItem"
                                @delete="confirmDeleteItem"
                            />
                        </li>
                    </ul>
                </div>
                <div v-if="pastItems.length">
                    <h3
                        class="text-surface-500 mb-3 text-sm font-medium tracking-wide uppercase"
                    >
                        Past
                    </h3>
                    <ul class="flex flex-col gap-4 opacity-60">
                        <li v-for="item in pastItems" :key="item.id">
                            <RosterItemCard
                                :roster="roster"
                                :item="item"
                                :can-manage="canManage"
                                :date-label="
                                    item.date ? formatDate(item.date) : null
                                "
                                @edit="openEditItem"
                                @delete="confirmDeleteItem"
                            />
                        </li>
                    </ul>
                </div>
            </template>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">Comments</h2>
            <CommentThread
                :roster-id="roster.id"
                commentable-type="roster"
                :commentable-id="roster.id"
                :comments="roster.comments ?? []"
                :store-url="storeRosterComment(roster).url"
                :destroy-url="
                    (comment) => destroyRosterComment([roster, comment]).url
                "
                :can-manage-parent="canManage"
            />
        </div>
    </div>

    <Dialog
        v-model:visible="showItemDialog"
        modal
        :header="editingItem ? 'Edit thing' : 'Add thing'"
        class="w-full max-w-md"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submitItem">
            <div class="flex flex-col gap-2">
                <label for="item-name" class="text-sm font-medium">
                    Name <span class="text-red-500">*</span>
                </label>
                <InputText
                    id="item-name"
                    v-model="itemForm.name"
                    autofocus
                    :invalid="!!itemForm.errors.name"
                />
                <small v-if="itemForm.errors.name" class="text-red-500">
                    {{ itemForm.errors.name }}
                </small>
            </div>

            <div class="flex gap-3">
                <div class="flex flex-1 flex-col gap-2">
                    <label for="item-quantity" class="text-sm font-medium">
                        Quantity
                    </label>
                    <InputNumber
                        id="item-quantity"
                        v-model="itemForm.quantity"
                        :min="0.01"
                        :max-fraction-digits="2"
                        :invalid="!!itemForm.errors.quantity"
                    />
                    <small v-if="itemForm.errors.quantity" class="text-red-500">
                        {{ itemForm.errors.quantity }}
                    </small>
                </div>
                <div class="flex flex-1 flex-col gap-2">
                    <label for="item-unit" class="text-sm font-medium"
                        >Unit</label
                    >
                    <InputText
                        id="item-unit"
                        v-model="itemForm.unit"
                        placeholder="kg, pieces, ..."
                        :invalid="!!itemForm.errors.unit"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="item-notes" class="text-sm font-medium"
                    >Notes</label
                >
                <Textarea
                    id="item-notes"
                    v-model="itemForm.notes"
                    rows="2"
                    :invalid="!!itemForm.errors.notes"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label for="item-date" class="text-sm font-medium">
                    Date
                </label>
                <DatePicker
                    id="item-date"
                    v-model="itemDateModel"
                    date-format="yy-mm-dd"
                    update-model-type="string"
                    show-icon
                    show-button-bar
                    :invalid="!!itemForm.errors.date"
                />
                <small class="text-surface-500">
                    Overrides the roster date.
                </small>
            </div>

            <div
                v-if="attendanceDayOptions.length > 0"
                class="flex flex-col gap-2"
            >
                <label
                    for="item-attendance-poll-option"
                    class="text-sm font-medium"
                >
                    Attendance day
                </label>
                <Select
                    id="item-attendance-poll-option"
                    v-model="itemForm.attendance_poll_option_id"
                    :options="attendanceDayOptions"
                    option-label="label"
                    option-value="id"
                    placeholder="Not linked"
                    show-clear
                    :invalid="!!itemForm.errors.attendance_poll_option_id"
                />
                <small class="text-surface-500">
                    Overrides the roster's link.
                </small>
                <small
                    v-if="itemForm.errors.attendance_poll_option_id"
                    class="text-red-500"
                >
                    {{ itemForm.errors.attendance_poll_option_id }}
                </small>
            </div>

            <div
                v-for="field in roster.custom_fields ?? []"
                :key="field.id"
                class="flex flex-col gap-2"
            >
                <label
                    :for="`item-field-${field.id}`"
                    class="text-sm font-medium"
                >
                    {{ field.name }}
                </label>
                <InputText
                    :id="`item-field-${field.id}`"
                    v-model="itemForm.custom_fields[field.id]"
                />
            </div>

            <div class="flex gap-3">
                <Button
                    type="submit"
                    :label="editingItem ? 'Save' : 'Add thing'"
                    :loading="itemForm.processing"
                />
                <Button
                    type="button"
                    label="Cancel"
                    severity="secondary"
                    text
                    @click="showItemDialog = false"
                />
            </div>
        </form>
    </Dialog>
</template>
