<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Group, GroupMember } from '@/types';
import {
    edit as editGroup,
    invite as inviteMember,
    removeMember,
} from '@/actions/App/Http/Controllers/GroupController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    group: Group;
    canManage: boolean;
}>();

const confirm = useConfirm();

const inviteForm = useForm({ name: '', email: '' });

function submitInvite() {
    inviteForm.post(inviteMember(props.group).url, {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset(),
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
                        v-else-if="!member.pivot.accepted_at"
                        severity="warn"
                        value="Invitation pending"
                    />
                    <Button
                        v-if="canManage && member.pivot.role !== 'owner'"
                        label="Remove"
                        severity="danger"
                        text
                        size="small"
                        class="ml-auto"
                        @click="confirmRemove(member)"
                    />
                </li>
            </ul>
        </div>

        <div
            v-if="canManage"
            class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm"
        >
            <h2 class="mb-4 text-lg font-medium">Invite a member</h2>
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
    </div>
</template>
