<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useConfirm } from 'primevue/useconfirm';
import ArrowLeft from '@primeicons/vue/arrow-left';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Friend } from '@/types';
import { edit as editSettings } from '@/actions/App/Http/Controllers/SettingsController';
import {
    destroy as destroyFriend,
    store as storeFriend,
} from '@/actions/App/Http/Controllers/FriendController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    friends: Friend[];
}>();

const confirm = useConfirm();

const form = useForm({ name: '', email: '' });

function submit() {
    form.post(storeFriend().url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function friendLabel(friend: Friend): string {
    return (
        friend.name ??
        friend.friend_user?.name ??
        friend.friend_user?.email ??
        ''
    );
}

function avatarLabel(friend: Friend): string {
    return friendLabel(friend).charAt(0).toUpperCase();
}

function confirmRemove(friend: Friend) {
    confirm.require({
        header: 'Remove friend?',
        message: `Remove ${friendLabel(friend)} from your friends?`,
        acceptLabel: 'Remove',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () =>
            router.delete(destroyFriend(friend).url, { preserveScroll: true }),
    });
}
</script>

<template>
    <Head title="Friends" />

    <div class="flex flex-col gap-6">
        <Link
            :href="editSettings().url"
            class="text-surface-500 hover:text-surface-900 dark:hover:text-surface-0 flex items-center gap-2 text-sm"
        >
            <ArrowLeft style="width: 0.875rem; height: 0.875rem" />
            Account settings
        </Link>

        <h1 class="text-surface-900 dark:text-surface-0 text-xl font-semibold">
            Friends
        </h1>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">Add a friend</h2>
            <form
                class="flex max-w-md items-start gap-3"
                @submit.prevent="submit"
            >
                <div class="flex flex-1 flex-col gap-2">
                    <label for="friend-name" class="text-sm font-medium">
                        Name
                    </label>
                    <InputText
                        id="friend-name"
                        v-model="form.name"
                        :invalid="!!form.errors.name"
                    />
                    <small v-if="form.errors.name" class="text-red-500">
                        {{ form.errors.name }}
                    </small>
                </div>
                <div class="flex flex-1 flex-col gap-2">
                    <label for="friend-email" class="text-sm font-medium">
                        Email address <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        id="friend-email"
                        v-model="form.email"
                        type="email"
                        :invalid="!!form.errors.email"
                    />
                    <small v-if="form.errors.email" class="text-red-500">
                        {{ form.errors.email }}
                    </small>
                </div>
                <Button type="submit" label="Add" :loading="form.processing" />
            </form>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">Your friends</h2>
            <div
                v-if="friends.length === 0"
                class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-8 text-center text-sm"
            >
                No friends saved yet. Anyone you invite to a group is saved here
                automatically.
            </div>
            <ul v-else class="flex flex-col gap-3">
                <li
                    v-for="friend in friends"
                    :key="friend.id"
                    class="flex items-center gap-3"
                >
                    <Avatar :label="avatarLabel(friend)" shape="circle" />
                    <div class="flex flex-col">
                        <span class="text-surface-900 dark:text-surface-0">
                            {{ friendLabel(friend) }}
                        </span>
                        <span
                            v-if="friend.friend_user?.email"
                            class="text-surface-500 text-xs"
                        >
                            {{ friend.friend_user.email }}
                        </span>
                    </div>
                    <Button
                        label="Remove"
                        severity="danger"
                        text
                        size="small"
                        class="ml-auto"
                        @click="confirmRemove(friend)"
                    />
                </li>
            </ul>
        </div>
    </div>
</template>
