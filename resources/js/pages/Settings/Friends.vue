<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PlusIcon, UsersIcon, XIcon } from '@lucide/vue';
import { ref } from 'vue';
import { destroy as destroyFriend, store as storeFriend } from '@/actions/App/Http/Controllers/FriendController';
import EmptyState from '@/components/app/empty-state.vue';
import FormField from '@/components/app/form-field.vue';
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
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

defineOptions({ layout: AppLayout });

defineProps<{
    friends: App.Data.FriendsData;
}>();

const { t } = useI18n();

const form = useForm({ email: '' });
const removing = ref<App.Data.FriendData | null>(null);
const confirmOpen = ref(false);

const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) min-w-0 flex-1 rounded-(--control-radius) px-3.5 text-[15px]';

function labelOf(friend: App.Data.FriendData): string {
    return friend.name || friend.email || t('common.noName');
}

function sharedGroupsLabel(friend: App.Data.FriendData): string {
    if (!friend.shared_groups.length) {
        return t('friends.noSharedGroups');
    }

    return t('friends.sharedGroups', { groups: friend.shared_groups.join(', ') });
}

function add(): void {
    form.post(storeFriend.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function askRemove(friend: App.Data.FriendData): void {
    removing.value = friend;
    confirmOpen.value = true;
}

function confirmRemove(): void {
    const friend = removing.value;

    if (!friend) {
        return;
    }

    router.delete(destroyFriend.url(friend.slug), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('friends.title')" />

    <div class="flex flex-col gap-10">
        <header class="flex flex-col gap-2.5" data-feature="F-05">
            <Link :href="dashboard().url" class="text-fg-muted hover:text-fg mb-1.5 inline-flex w-fit items-center gap-1.5 rounded-sm text-sm font-semibold no-underline">
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('nav.backToDashboard') }}
            </Link>
            <h1 class="flex items-center gap-2.5 text-3xl">
                {{ t('friends.title') }}
                <span v-if="friends.items.length" class="bg-surface-sunken text-fg-muted rounded-full px-2.5 py-0.5 font-sans text-sm font-semibold tabular-nums">{{
                    friends.items.length
                }}</span>
            </h1>
            <p class="text-fg-muted max-w-[60ch]">{{ t('friends.lede') }}</p>
        </header>

        <div class="grid gap-10 min-[960px]:grid-cols-[minmax(0,1fr)_340px] min-[960px]:items-start min-[960px]:gap-12">
            <section class="flex min-w-0 flex-col gap-4" aria-labelledby="friends-list-title" data-feature="F-05">
                <h2 id="friends-list-title" class="text-xl">{{ t('friends.list') }}</h2>

                <ul v-if="friends.items.length" class="box divide-border flex flex-col divide-y px-5 py-1">
                    <li v-for="friend in friends.items" :key="friend.slug" class="flex min-w-0 items-center gap-3 py-3">
                        <UserAvatar :name="friend.name" :email="friend.email" />
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="flex flex-wrap items-center gap-2 font-semibold">
                                <span v-if="friend.name">{{ friend.name }}</span>
                                <span v-else class="text-fg-soft">{{ t('common.noName') }}</span>
                                <span v-if="friend.invitation_pending" class="bg-warning-soft text-warning rounded-full px-2 py-px text-xs font-semibold">{{
                                    t('friends.pending')
                                }}</span>
                            </span>
                            <span v-if="friend.email" class="text-fg-soft text-[13px] [overflow-wrap:anywhere]">{{ friend.email }}</span>
                            <span class="text-fg-muted text-[13px]">{{ sharedGroupsLabel(friend) }}</span>
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-lg"
                            class="shrink-0 rounded-full"
                            :aria-label="t('friends.remove', { name: labelOf(friend) })"
                            @click="askRemove(friend)"
                        >
                            <XIcon aria-hidden="true" />
                        </Button>
                    </li>
                </ul>
                <EmptyState v-else :icon="UsersIcon" :title="t('friends.empty.title')" :text="t('friends.empty.text')" />
            </section>

            <aside class="min-w-0 min-[960px]:sticky min-[960px]:top-[calc(64px+24px+env(safe-area-inset-top,0px))]" aria-labelledby="friends-add-title">
                <form class="box flex flex-col gap-4 p-5 sm:p-6" novalidate data-feature="F-05" @submit.prevent="add">
                    <h2 id="friends-add-title" class="text-xl">{{ t('friends.add.title') }}</h2>
                    <FormField :label="t('friends.add.email')" control="friend-email" required :hint="t('friends.add.hint')" :error="form.errors.email">
                        <template #default="{ id, describedBy, invalid }">
                            <div class="flex gap-2">
                                <Input
                                    :id="id"
                                    v-model.trim="form.email"
                                    type="email"
                                    autocomplete="off"
                                    required
                                    :placeholder="t('friends.add.placeholder')"
                                    :aria-describedby="describedBy"
                                    :aria-invalid="invalid || undefined"
                                    :class="inputClass"
                                />
                                <Button type="submit" size="icon-lg" class="size-(--control-height) shrink-0 rounded-full" :disabled="form.processing">
                                    <PlusIcon aria-hidden="true" />
                                    <span class="sr-only">{{ t('friends.add.button') }}</span>
                                </Button>
                            </div>
                        </template>
                    </FormField>
                </form>
            </aside>
        </div>

        <AlertDialog v-model:open="confirmOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('friends.remove.title', { name: removing ? labelOf(removing) : '' }) }}</AlertDialogTitle>
                    <AlertDialogDescription>{{ t('friends.remove.text') }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('dialog.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction variant="destructive" @click="confirmRemove">{{ t('friends.remove.confirm') }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
