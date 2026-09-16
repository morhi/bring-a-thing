<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Times from '@primeicons/vue/times';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateOnly, formatTimeOnly } from '@/lib/pollDate';
import type {
    Friend,
    Group,
    GroupMember,
    PollGranularity,
    PollType,
} from '@/types';
import {
    edit as editGroup,
    invite as inviteMember,
    removeMember,
    updateMemberRole,
} from '@/actions/App/Http/Controllers/GroupController';
import {
    show as showRoster,
    store as storeRoster,
} from '@/actions/App/Http/Controllers/RosterController';
import {
    show as showPoll,
    store as storePoll,
} from '@/actions/App/Http/Controllers/PollController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    group: Group;
    canManage: boolean;
    canManageMembers: boolean;
    isOwner: boolean;
    friends: Friend[];
}>();

const confirm = useConfirm();

const inviteForm = useForm({ name: '', email: '' });

function friendLabel(friend: Friend): string {
    return (
        friend.name ??
        friend.friend_user?.name ??
        friend.friend_user?.email ??
        ''
    );
}

/** Friends who aren't already members (or pending invitees) of this group, for the "add from friends" picker. */
const pickableFriends = computed(() => {
    const memberEmails = new Set(
        (props.group.members ?? []).map((member) => member.email),
    );

    return props.friends
        .filter((friend) => !memberEmails.has(friend.friend_user?.email ?? ''))
        .map((friend) => ({
            id: friend.id,
            label: friendLabel(friend),
            email: friend.friend_user?.email ?? '',
            name: friend.name ?? friend.friend_user?.name ?? '',
        }));
});

const pickedFriendId = ref<number | null>(null);

const friendInviteForm = useForm({ name: '', email: '' });

function submitFriendInvite() {
    const friend = pickableFriends.value.find(
        (candidate) => candidate.id === pickedFriendId.value,
    );

    if (!friend) {
        return;
    }

    friendInviteForm.name = friend.name;
    friendInviteForm.email = friend.email;
    friendInviteForm.post(inviteMember(props.group).url, {
        preserveScroll: true,
        onSuccess: () => {
            pickedFriendId.value = null;
        },
    });
}

function submitInvite() {
    inviteForm.post(inviteMember(props.group).url, {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset(),
    });
}

const showCreateRoster = ref(false);

const createRosterForm = useForm({
    title: '',
    description: '',
    group_id: props.group.id,
});

function submitCreateRoster() {
    createRosterForm.post(storeRoster().url, {
        onSuccess: () => {
            createRosterForm.reset('title', 'description');
            showCreateRoster.value = false;
        },
    });
}

type PollOptionRow = {
    date: Date | null;
    starts_at: Date | null;
    ends_at: Date | null;
    label: string;
};

function emptyOptionRow(): PollOptionRow {
    return { date: null, starts_at: null, ends_at: null, label: '' };
}

const showCreatePoll = ref(false);

const createPollForm = useForm({
    title: '',
    type: 'date_finder' as PollType,
    granularity: 'day' as PollGranularity,
    options: [emptyOptionRow()],
    starts_on: null as Date | null,
    ends_on: null as Date | null,
});

const pollTypeOptions = [
    { label: 'Date finder', value: 'date_finder' },
    { label: 'Attendance', value: 'attendance' },
];

const pollGranularityOptions = [
    { label: 'Day', value: 'day' },
    { label: 'Hour', value: 'hour' },
];

const usesExplicitPollOptions = computed(
    () =>
        createPollForm.type === 'date_finder' ||
        createPollForm.granularity === 'hour',
);

function addPollOptionRow() {
    createPollForm.options.push(emptyOptionRow());
}

function removePollOptionRow(index: number) {
    createPollForm.options.splice(index, 1);
}

function submitCreatePoll() {
    createPollForm
        .transform((data) => ({
            title: data.title,
            type: data.type,
            granularity: data.granularity,
            ...(usesExplicitPollOptions.value
                ? {
                      options: data.options.map((option) => ({
                          date: option.date
                              ? formatDateOnly(option.date)
                              : null,
                          starts_at: option.starts_at
                              ? formatTimeOnly(option.starts_at)
                              : null,
                          ends_at: option.ends_at
                              ? formatTimeOnly(option.ends_at)
                              : null,
                          label: option.label || null,
                      })),
                  }
                : {
                      starts_on: data.starts_on
                          ? formatDateOnly(data.starts_on)
                          : null,
                      ends_on: data.ends_on
                          ? formatDateOnly(data.ends_on)
                          : null,
                  }),
        }))
        .post(storePoll(props.group).url, {
            onSuccess: () => {
                createPollForm.reset();
                createPollForm.options = [emptyOptionRow()];
                showCreatePoll.value = false;
            },
        });
}

function confirmRemove(member: GroupMember) {
    confirm.require({
        header: 'Remove member?',
        message: `Remove ${member.name ?? member.email} from ${props.group.name}?`,
        acceptLabel: 'Remove',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () =>
            router.delete(removeMember({ group: props.group, member }).url, {
                preserveScroll: true,
            }),
    });
}

function avatarLabel(name: string | null, email: string): string {
    return (name ?? email).charAt(0).toUpperCase();
}

function toggleAdmin(member: GroupMember) {
    const role = member.pivot.role === 'admin' ? 'member' : 'admin';

    router.patch(
        updateMemberRole({ group: props.group, member }).url,
        { role },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="group.name" />

    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1
                class="text-surface-900 dark:text-surface-0 text-xl font-semibold"
            >
                {{ group.name }}
            </h1>
            <Link v-if="canManage" :href="editGroup(group).url">
                <Button label="Settings" severity="secondary" text />
            </Link>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">Members</h2>
            <ul class="flex flex-col gap-3">
                <li
                    v-for="member in group.members"
                    :key="member.id"
                    class="flex items-center gap-3"
                >
                    <Avatar
                        :label="avatarLabel(member.name, member.email)"
                        shape="circle"
                    />
                    <div class="flex flex-col">
                        <span class="text-surface-900 dark:text-surface-0">
                            {{ member.name ?? member.email }}
                        </span>
                        <span
                            v-if="member.name"
                            class="text-surface-500 text-xs"
                        >
                            {{ member.email }}
                        </span>
                    </div>
                    <Tag
                        v-if="member.pivot.role === 'owner'"
                        severity="info"
                        value="Owner"
                    />
                    <Tag
                        v-else-if="member.pivot.role === 'admin'"
                        severity="success"
                        value="Admin"
                    />
                    <Tag
                        v-if="!member.pivot.accepted_at"
                        severity="warn"
                        value="Invitation pending"
                    />
                    <Button
                        v-if="isOwner && member.pivot.role !== 'owner'"
                        :label="
                            member.pivot.role === 'admin'
                                ? 'Remove admin'
                                : 'Make admin'
                        "
                        severity="secondary"
                        text
                        size="small"
                        class="ml-auto"
                        @click="toggleAdmin(member)"
                    />
                    <Button
                        v-if="canManageMembers && member.pivot.role !== 'owner'"
                        label="Remove"
                        severity="danger"
                        text
                        size="small"
                        :class="{ 'ml-auto': !isOwner }"
                        @click="confirmRemove(member)"
                    />
                </li>
            </ul>
        </div>

        <div
            v-if="canManageMembers"
            class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm"
        >
            <h2 class="mb-4 text-lg font-medium">Invite a member</h2>

            <div v-if="pickableFriends.length > 0" class="mb-4">
                <label class="mb-2 block text-sm font-medium">
                    Add from friends
                </label>
                <div class="flex max-w-md items-start gap-3">
                    <Select
                        v-model="pickedFriendId"
                        :options="pickableFriends"
                        option-label="label"
                        option-value="id"
                        placeholder="Choose a friend..."
                        class="flex-1"
                    >
                        <template #option="{ option }">
                            {{ option.label }}
                            <span
                                v-if="option.email"
                                class="text-surface-500 text-xs"
                            >
                                ({{ option.email }})
                            </span>
                        </template>
                    </Select>
                    <Button
                        label="Add"
                        severity="secondary"
                        :disabled="pickedFriendId === null"
                        :loading="friendInviteForm.processing"
                        @click="submitFriendInvite"
                    />
                </div>
            </div>

            <label class="mb-2 block text-sm font-medium">
                Invite by email
            </label>
            <form
                class="flex max-w-md items-start gap-3"
                @submit.prevent="submitInvite"
            >
                <div class="flex flex-1 flex-col gap-2">
                    <InputText
                        v-model="inviteForm.name"
                        placeholder="Name"
                        :invalid="!!inviteForm.errors.name"
                    />
                    <small v-if="inviteForm.errors.name" class="text-red-500">
                        {{ inviteForm.errors.name }}
                    </small>
                </div>
                <div class="flex flex-1 flex-col gap-2">
                    <InputText
                        v-model="inviteForm.email"
                        type="email"
                        placeholder="Email address"
                        :invalid="!!inviteForm.errors.email"
                    />
                    <small v-if="inviteForm.errors.email" class="text-red-500">
                        {{ inviteForm.errors.email }}
                    </small>
                </div>
                <Button
                    type="submit"
                    label="Invite"
                    :loading="inviteForm.processing"
                />
            </form>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-medium">Rosters</h2>
                <Button
                    label="New roster"
                    size="small"
                    @click="showCreateRoster = true"
                />
            </div>
            <div
                v-if="!group.rosters || group.rosters.length === 0"
                class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-8 text-center text-sm"
            >
                No rosters attached to this group yet.
            </div>
            <ul v-else class="flex flex-col gap-3">
                <li v-for="roster in group.rosters" :key="roster.id">
                    <Link
                        :href="showRoster(roster).url"
                        class="border-surface-200 dark:border-surface-700 text-surface-900 dark:text-surface-0 block rounded-lg border p-4 hover:shadow"
                    >
                        {{ roster.title }}
                    </Link>
                </li>
            </ul>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-medium">Polls</h2>
                <Button
                    label="New poll"
                    size="small"
                    @click="showCreatePoll = true"
                />
            </div>
            <div
                v-if="!group.polls || group.polls.length === 0"
                class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-8 text-center text-sm"
            >
                No polls attached to this group yet.
            </div>
            <ul v-else class="flex flex-col gap-3">
                <li v-for="poll in group.polls" :key="poll.id">
                    <Link
                        :href="showPoll(poll).url"
                        class="border-surface-200 dark:border-surface-700 text-surface-900 dark:text-surface-0 block rounded-lg border p-4 hover:shadow"
                    >
                        {{ poll.title }}
                        <Tag
                            :value="
                                poll.type === 'date_finder'
                                    ? 'Date finder'
                                    : 'Attendance'
                            "
                            severity="secondary"
                            class="ml-2"
                        />
                    </Link>
                </li>
            </ul>
        </div>
    </div>

    <Dialog
        v-model:visible="showCreateRoster"
        modal
        header="New roster"
        class="w-full max-w-sm"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submitCreateRoster">
            <div class="flex flex-col gap-2">
                <label for="roster-title" class="text-sm font-medium">
                    Title
                </label>
                <InputText
                    id="roster-title"
                    v-model="createRosterForm.title"
                    autofocus
                    :invalid="!!createRosterForm.errors.title"
                />
                <small
                    v-if="createRosterForm.errors.title"
                    class="text-red-500"
                >
                    {{ createRosterForm.errors.title }}
                </small>
            </div>
            <div class="flex flex-col gap-2">
                <label for="roster-description" class="text-sm font-medium">
                    Description (optional)
                </label>
                <Textarea
                    id="roster-description"
                    v-model="createRosterForm.description"
                    rows="3"
                    :invalid="!!createRosterForm.errors.description"
                />
                <small
                    v-if="createRosterForm.errors.description"
                    class="text-red-500"
                >
                    {{ createRosterForm.errors.description }}
                </small>
            </div>
            <Button
                type="submit"
                label="Create"
                :loading="createRosterForm.processing"
            />
        </form>
    </Dialog>

    <Dialog
        v-model:visible="showCreatePoll"
        modal
        header="New poll"
        class="w-full max-w-lg"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submitCreatePoll">
            <div class="flex flex-col gap-2">
                <label for="poll-title" class="text-sm font-medium">
                    Title
                </label>
                <InputText
                    id="poll-title"
                    v-model="createPollForm.title"
                    autofocus
                    :invalid="!!createPollForm.errors.title"
                />
                <small v-if="createPollForm.errors.title" class="text-red-500">
                    {{ createPollForm.errors.title }}
                </small>
            </div>

            <div class="flex gap-4">
                <div class="flex flex-1 flex-col gap-2">
                    <label for="poll-type" class="text-sm font-medium">
                        Type
                    </label>
                    <Select
                        id="poll-type"
                        v-model="createPollForm.type"
                        :options="pollTypeOptions"
                        option-label="label"
                        option-value="value"
                    />
                </div>
                <div class="flex flex-1 flex-col gap-2">
                    <label for="poll-granularity" class="text-sm font-medium">
                        Granularity
                    </label>
                    <Select
                        id="poll-granularity"
                        v-model="createPollForm.granularity"
                        :options="pollGranularityOptions"
                        option-label="label"
                        option-value="value"
                    />
                </div>
            </div>

            <template v-if="usesExplicitPollOptions">
                <div class="flex flex-col gap-3">
                    <label class="text-sm font-medium">
                        {{
                            createPollForm.granularity === 'hour'
                                ? 'Candidate day/time slots'
                                : 'Candidate days'
                        }}
                    </label>
                    <div
                        v-for="(option, index) in createPollForm.options"
                        :key="index"
                        class="border-surface-200 dark:border-surface-700 flex flex-col gap-2 rounded-lg border p-3"
                    >
                        <div class="flex items-start gap-2">
                            <DatePicker
                                v-model="option.date"
                                placeholder="Date"
                                date-format="yy-mm-dd"
                                show-icon
                                class="flex-1"
                            />
                            <Button
                                severity="danger"
                                text
                                :disabled="createPollForm.options.length <= 1"
                                @click="removePollOptionRow(index)"
                            >
                                <Times class="h-3 w-3" />
                            </Button>
                        </div>
                        <div
                            v-if="createPollForm.granularity === 'hour'"
                            class="flex items-start gap-2"
                        >
                            <DatePicker
                                v-model="option.starts_at"
                                time-only
                                placeholder="Start time"
                                class="flex-1"
                            />
                            <DatePicker
                                v-model="option.ends_at"
                                time-only
                                placeholder="End time"
                                class="flex-1"
                            />
                            <InputText
                                v-model="option.label"
                                placeholder="Label (e.g. Lunch)"
                                class="flex-1"
                            />
                        </div>
                    </div>
                    <Button
                        label="Add option"
                        severity="secondary"
                        text
                        size="small"
                        @click="addPollOptionRow"
                    />
                    <small
                        v-if="createPollForm.errors.options"
                        class="text-red-500"
                    >
                        {{ createPollForm.errors.options }}
                    </small>
                </div>
            </template>

            <template v-else>
                <div class="flex gap-4">
                    <div class="flex flex-1 flex-col gap-2">
                        <label for="poll-starts-on" class="text-sm font-medium">
                            From
                        </label>
                        <DatePicker
                            id="poll-starts-on"
                            v-model="createPollForm.starts_on"
                            date-format="yy-mm-dd"
                            show-icon
                            :invalid="!!createPollForm.errors.starts_on"
                        />
                        <small
                            v-if="createPollForm.errors.starts_on"
                            class="text-red-500"
                        >
                            {{ createPollForm.errors.starts_on }}
                        </small>
                    </div>
                    <div class="flex flex-1 flex-col gap-2">
                        <label for="poll-ends-on" class="text-sm font-medium">
                            To
                        </label>
                        <DatePicker
                            id="poll-ends-on"
                            v-model="createPollForm.ends_on"
                            date-format="yy-mm-dd"
                            show-icon
                            :invalid="!!createPollForm.errors.ends_on"
                        />
                        <small
                            v-if="createPollForm.errors.ends_on"
                            class="text-red-500"
                        >
                            {{ createPollForm.errors.ends_on }}
                        </small>
                    </div>
                </div>
            </template>

            <Button
                type="submit"
                label="Create"
                :loading="createPollForm.processing"
            />
        </form>
    </Dialog>
</template>
