<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Friend, Group, GroupMember } from '@/types';
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
</template>
