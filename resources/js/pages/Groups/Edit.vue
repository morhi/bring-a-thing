<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyGroup,
    invite as inviteMembers,
    removeMember,
    show as showGroup,
    update as updateGroup,
    updateMemberRole,
} from '@/actions/App/Http/Controllers/GroupController';
import {
    destroy as revokeInviteLink,
    regenerate as regenerateInviteLink,
    store as storeInviteLink,
} from '@/actions/App/Http/Controllers/GroupInviteLinkController';
import FormField from '@/components/app/form-field.vue';
import InviteLinkCard from '@/components/app/invite-link-card.vue';
import InviteField from '@/components/app/invite-field.vue';
import type { InviteSelection } from '@/components/app/invite-field.vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ page: App.Data.GroupSettingsData }>();

const { t } = useI18n();
const currentUserId = computed(() => usePage().props.auth.user?.id ?? null);

const roleLabel: Record<App.Enums.GroupRole, string> = {
    owner: 'group.member.owner',
    admin: 'group.member.admin',
    member: 'group.member.member',
};

function personName(member: App.Data.GroupMemberData): string {
    return member.name || member.email || t('common.noName');
}

function showRequestError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? t('group.requestFailed'), {
        duration: 6000,
    });
}

const visitOptions = { preserveScroll: true, onError: showRequestError };

// Name (owner only, F-03)
const nameForm = useForm({ name: props.page.name });

function saveName(): void {
    nameForm.patch(updateGroup(props.page.slug).url, {
        preserveScroll: true,
        onSuccess: () => nameForm.defaults({ name: nameForm.name }),
    });
}

// Option "Members can set the participation status for others" (owner only, F-03)
function setOption(checked: boolean | 'indeterminate'): void {
    router.patch(
        updateGroup(props.page.slug).url,
        { members_can_set_status: checked === true },
        visitOptions,
    );
}

function setRole(member: App.Data.GroupMemberData, role: string): void {
    router.patch(
        updateMemberRole({ group: props.page.slug, member: member.id }).url,
        { role },
        visitOptions,
    );
}

function canRemove(member: App.Data.GroupMemberData): boolean {
    return member.role !== 'owner' && member.id !== currentUserId.value;
}

// One confirmation dialog serves every destructive action on the page
type Confirmation = {
    title: string;
    text: string;
    action: string;
    details?: string[];
    run: () => void;
};

const confirmation = ref<Confirmation | null>(null);
const confirmOpen = ref(false);

function ask(entry: Confirmation): void {
    confirmation.value = entry;
    confirmOpen.value = true;
}

function confirmRemove(member: App.Data.GroupMemberData): void {
    ask({
        title: t('groupSettings.removeConfirm', { name: personName(member) }),
        text: t('groupSettings.removeText'),
        action: t('groupSettings.remove'),
        run: () =>
            router.delete(
                removeMember({ group: props.page.slug, member: member.id }).url,
                visitOptions,
            ),
    });
}

function confirmDelete(): void {
    ask({
        title: t('groupSettings.deleteTitle', { group: props.page.name }),
        text: t('groupSettings.deleteText'),
        action: t('groupSettings.delete'),
        details: [
            t('groupSettings.deleteMembers', { n: props.page.members.length }),
            t('groupSettings.deletePolls', { n: props.page.poll_count }),
            t('groupSettings.deleteEvents', { n: props.page.event_count }),
            t('groupSettings.deleteLists', { n: props.page.list_count }),
        ],
        run: () =>
            router.delete(destroyGroup(props.page.slug).url, {
                onError: showRequestError,
            }),
    });
}

// Invitations, same component as on the group page (F-04, F-05)
const invites = ref<InviteSelection[]>([]);
const inviteForm = useForm({
    invites: [] as { email: string; name: string | null }[],
});
const inviteError = computed(() => {
    const key = Object.keys(inviteForm.errors).find((name) =>
        name.startsWith('invites'),
    );

    return key ? (inviteForm.errors as Record<string, string>)[key] : null;
});

function sendInvites(): void {
    if (!invites.value.length) {
        return;
    }

    inviteForm.invites = invites.value.map((selection) => ({
        email: selection.email,
        name: selection.name ?? null,
    }));
    inviteForm.post(inviteMembers(props.page.slug).url, {
        preserveScroll: true,
        onSuccess: () => {
            invites.value = [];
            inviteForm.reset();
        },
    });
}

const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring h-(--control-height) rounded-(--control-radius) px-3.5 text-[15px]';
const buttonClass = 'h-(--control-height) px-4';
</script>

<template>
    <Head :title="t('groupSettings.title')" />

    <div class="flex flex-col gap-10">
        <header class="flex flex-col gap-2.5" data-feature="F-03">
            <Link
                :href="showGroup(page.slug).url"
                class="text-fg-muted hover:text-fg -mt-0.5 mb-1 inline-flex min-h-6 w-fit items-center gap-1.5 rounded-sm text-sm font-semibold"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('groupSettings.back', { group: page.name }) }}
            </Link>
            <h1 class="text-h1">{{ t('groupSettings.title') }}</h1>
            <p class="text-fg-muted text-lg break-words">{{ page.name }}</p>
        </header>

        <SectionBox
            id="gs-name-title"
            :title="t('groupSettings.name')"
            data-feature="F-03"
        >
            <form
                v-if="page.permissions.can_rename"
                class="max-w-field"
                novalidate
                @submit.prevent="saveName"
            >
                <FormField
                    :label="t('newGroup.name')"
                    control="gs-name"
                    required
                    :error="nameForm.errors.name"
                >
                    <template #default="{ describedBy, invalid }">
                        <div class="flex flex-wrap gap-2">
                            <Input
                                id="gs-name"
                                v-model="nameForm.name"
                                required
                                autocomplete="off"
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                                :class="[
                                    inputClass,
                                    'min-w-0 flex-[1_1_220px]',
                                ]"
                            />
                            <Button
                                type="submit"
                                size="lg"
                                :class="buttonClass"
                                :disabled="
                                    nameForm.processing || !nameForm.isDirty
                                "
                            >
                                {{ t('groupSettings.saveName') }}
                            </Button>
                        </div>
                    </template>
                </FormField>
            </form>
            <template v-else>
                <p class="font-display text-xl font-[640] break-words">
                    {{ page.name }}
                </p>
                <p class="text-fg-soft text-sm">
                    {{ t('groupSettings.nameOwnerOnly') }}
                </p>
            </template>
        </SectionBox>

        <InviteLinkCard
            id="gs-link"
            :url="page.invite_link.url"
            :create-url="storeInviteLink(page.slug).url"
            :regenerate-url="regenerateInviteLink(page.slug).url"
            :revoke-url="revokeInviteLink(page.slug).url"
            :hint="t('groupSettings.link.hint')"
            data-feature="F-04"
        />

        <SectionBox
            id="gs-options-title"
            :title="t('groupSettings.options')"
            data-feature="F-03"
        >
            <template v-if="!page.permissions.can_switch_option" #action>
                <p class="text-fg-soft text-sm">
                    {{ t('groupSettings.options.ownerOnly') }}
                </p>
            </template>
            <SectionBoxItem class="p-5 sm:p-6">
                <div class="flex items-start gap-3" data-feature="F-11">
                    <Checkbox
                        id="gs-set-status"
                        class="mt-0.5"
                        :model-value="page.members_can_set_status"
                        :disabled="!page.permissions.can_switch_option"
                        aria-describedby="gs-set-status-hint"
                        @update:model-value="setOption"
                    />
                    <span class="flex flex-col gap-0.5">
                        <label for="gs-set-status" class="font-semibold">{{
                            t('groupSettings.options.setStatus')
                        }}</label>
                        <span
                            id="gs-set-status-hint"
                            class="text-fg-muted text-sm"
                            >{{
                                t('groupSettings.options.setStatusHint')
                            }}</span
                        >
                    </span>
                </div>
            </SectionBoxItem>
        </SectionBox>

        <SectionBox
            id="gs-members-title"
            :title="t('group.members')"
            :count="page.members.length"
            data-feature="F-03"
        >
            <template #action>
                <p class="text-fg-soft text-sm">
                    {{
                        page.permissions.can_change_roles
                            ? t('groupSettings.rolesHintOwner')
                            : t('groupSettings.rolesHintAdmin')
                    }}
                </p>
            </template>
            <SectionBoxItem class="flex flex-col gap-4 p-5 sm:p-6">
                <ul class="flex flex-col">
                    <li
                        v-for="member in page.members"
                        :key="member.id"
                        class="flex flex-wrap items-center gap-3 py-2.5"
                    >
                        <UserAvatar :name="member.name" :email="member.email" />
                        <span class="flex min-w-0 flex-[1_1_160px] flex-col">
                            <span
                                class="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold"
                            >
                                <span v-if="member.name" class="break-words">{{
                                    member.name
                                }}</span>
                                <span v-else class="text-fg-soft font-normal">{{
                                    t('common.noName')
                                }}</span>
                                <span
                                    v-if="member.id === currentUserId"
                                    class="text-fg-soft text-sm font-normal"
                                    >({{ t('group.member.you') }})</span
                                >
                                <Badge
                                    v-if="member.is_pending"
                                    class="bg-warning-soft text-warning"
                                    >{{ t('group.member.pending') }}</Badge
                                >
                                <Badge
                                    v-if="member.joined_via_link"
                                    class="bg-info-soft text-info"
                                    data-feature="F-04"
                                    >{{ t('group.member.viaLink') }}</Badge
                                >
                            </span>
                            <span
                                v-if="member.email"
                                class="text-fg-soft truncate text-[13px]"
                                >{{ member.email }}</span
                            >
                        </span>
                        <span class="ml-auto flex flex-wrap items-center gap-2">
                            <template
                                v-if="
                                    member.role !== 'owner' &&
                                    page.permissions.can_change_roles
                                "
                            >
                                <label
                                    class="sr-only"
                                    :for="`gs-role-${member.id}`"
                                    >{{
                                        t('groupSettings.roleOf', {
                                            name: personName(member),
                                        })
                                    }}</label
                                >
                                <NativeSelect
                                    :id="`gs-role-${member.id}`"
                                    :model-value="member.role"
                                    class="min-w-[130px]"
                                    data-feature="F-03"
                                    @change="
                                        setRole(
                                            member,
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <NativeSelectOption value="admin">{{
                                        t('group.member.admin')
                                    }}</NativeSelectOption>
                                    <NativeSelectOption value="member">{{
                                        t('group.member.member')
                                    }}</NativeSelectOption>
                                </NativeSelect>
                            </template>
                            <span v-else class="text-fg-muted text-[13px]">{{
                                t(roleLabel[member.role])
                            }}</span>
                            <Button
                                v-if="canRemove(member)"
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="
                                    t('groupSettings.removeLabel', {
                                        name: personName(member),
                                    })
                                "
                                data-feature="F-04"
                                @click="confirmRemove(member)"
                            >
                                <X aria-hidden="true" />
                            </Button>
                        </span>
                    </li>
                </ul>
                <p class="text-fg-soft text-sm">
                    {{ t('groupSettings.ownerNote') }}
                </p>

                <div class="border-border border-t pt-4">
                    <InviteField
                        id="gs-invite"
                        v-model="invites"
                        :label="t('group.invite.title')"
                        :hint="t('group.invite.hint')"
                        :error="inviteError"
                        :submit-label="t('group.invite.button')"
                        :friends="page.friends"
                        :member-ids="page.members.map((member) => member.id)"
                        :member-emails="
                            page.members
                                .map((member) => member.email)
                                .filter((email): email is string => !!email)
                        "
                        :link="false"
                        :disabled="inviteForm.processing"
                        @submit="sendInvites"
                    />
                </div>
            </SectionBoxItem>
        </SectionBox>

        <SectionBox
            v-if="page.permissions.can_delete"
            id="gs-danger-title"
            :title="t('groupSettings.danger')"
            data-feature="F-03"
        >
            <SectionBoxItem
                class="border-danger/45! flex flex-col items-start gap-3 px-6 py-5 md:flex-row md:items-center md:justify-between md:gap-6"
            >
                <p class="text-fg-muted max-w-md">
                    {{ t('groupSettings.deleteHint') }}
                </p>
                <Button
                    type="button"
                    variant="destructive"
                    size="lg"
                    :class="buttonClass"
                    @click="confirmDelete"
                >
                    {{ t('groupSettings.delete') }}
                </Button>
            </SectionBoxItem>
        </SectionBox>

        <AlertDialog v-model:open="confirmOpen">
            <AlertDialogContent v-if="confirmation">
                <AlertDialogHeader>
                    <AlertDialogTitle>{{
                        confirmation.title
                    }}</AlertDialogTitle>
                    <AlertDialogDescription>{{
                        confirmation.text
                    }}</AlertDialogDescription>
                </AlertDialogHeader>
                <ul
                    v-if="confirmation.details"
                    class="flex flex-wrap gap-2"
                    data-feature="F-03"
                >
                    <li v-for="detail in confirmation.details" :key="detail">
                        <Badge variant="secondary">{{ detail }}</Badge>
                    </li>
                </ul>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{
                        t('dialog.cancel')
                    }}</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        @click="confirmation.run()"
                        >{{ confirmation.action }}</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
