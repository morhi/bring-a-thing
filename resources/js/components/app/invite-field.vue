<script setup lang="ts">
import { CheckIcon, LinkIcon, MailIcon, PlusIcon, XIcon } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import FormField from '@/components/app/form-field.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';
import { initials } from '@/lib/initials';

export type InviteFriend = {
    id: number | string;
    name: string | null;
    email: string;
};

export type InviteSelection = {
    accountId: number | string | null;
    email: string;
    name?: string | null;
};

type Row =
    | { kind: 'friend'; friend: InviteFriend; disabled: boolean }
    | { kind: 'email'; email: string; disabled: boolean };

/**
 * Collect people to invite by friend name or email address as removable chips (F-04, F-05).
 *
 * Makes no requests: the parent passes friends and current members, applies the selection
 * on `submit`, and owns the invite link. Copying works when `linkUrl` is set; without it the
 * button emits `createLink`, and the parent can call the exposed `copyLink()` once the link exists.
 * `linkAvailable` false (group not created yet) disables the button with an explanation.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        hint?: string | null;
        error?: string | null;
        friends?: InviteFriend[];
        memberIds?: (number | string)[];
        memberEmails?: string[];
        maxSuggestions?: number;
        submitLabel?: string | null;
        link?: boolean;
        linkUrl?: string | null;
        linkAvailable?: boolean;
        disabled?: boolean;
    }>(),
    {
        hint: null,
        error: null,
        friends: () => [],
        memberIds: () => [],
        memberEmails: () => [],
        maxSuggestions: 4,
        submitLabel: null,
        link: true,
        linkUrl: null,
        linkAvailable: true,
        disabled: false,
    },
);

const model = defineModel<InviteSelection[]>({ default: () => [] });

const emit = defineEmits<{
    submit: [];
    createLink: [];
    copied: [url: string];
    copyFailed: [url: string];
}>();

const { t } = useI18n();

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const query = ref('');
const open = ref(false);
const active = ref(0);
const copied = ref(false);
let copiedTimer: ReturnType<typeof setTimeout> | undefined;

const listId = computed(() => `${props.id}-list`);
const selectedIds = computed(() =>
    model.value
        .map((selection) => selection.accountId)
        .filter((id) => id !== null),
);
const selectedEmails = computed(() =>
    model.value.map((selection) => selection.email.toLowerCase()),
);
const memberEmailSet = computed(
    () => new Set(props.memberEmails.map((email) => email.toLowerCase())),
);

const rows = computed<Row[]>(() => {
    const term = query.value.trim().toLowerCase();

    if (!term) {
        return [];
    }

    const friendRows: Row[] = props.friends
        .filter(
            (friend) => friend.email && !selectedIds.value.includes(friend.id),
        )
        .filter(
            (friend) =>
                friend.email.toLowerCase().includes(term) ||
                (friend.name ?? '').toLowerCase().includes(term),
        )
        .slice(0, props.maxSuggestions)
        .map((friend) => ({
            kind: 'friend',
            friend,
            disabled: props.memberIds.includes(friend.id),
        }));

    const exactFriend = friendRows.some(
        (row) =>
            row.kind === 'friend' && row.friend.email.toLowerCase() === term,
    );

    if (
        !emailPattern.test(term) ||
        exactFriend ||
        selectedEmails.value.includes(term)
    ) {
        return friendRows;
    }

    return [
        {
            kind: 'email',
            email: term,
            disabled: memberEmailSet.value.has(term),
        },
        ...friendRows,
    ];
});

const expanded = computed(() => open.value && rows.value.length > 0);
const noMatch = computed(
    () => query.value.trim().length > 0 && rows.value.length === 0,
);
const hintText = computed(() =>
    noMatch.value ? t('invite.noMatch') : props.hint,
);

watch(rows, (value) => {
    const first = value.findIndex((row) => !row.disabled);
    active.value = first < 0 ? 0 : first;
});

function optionId(index: number): string {
    return `${props.id}-option-${index}`;
}

function rowKey(row: Row): string {
    return row.kind === 'friend'
        ? `friend-${row.friend.id}`
        : `email-${row.email}`;
}

function selectionName(selection: InviteSelection): string {
    return selection.name || selection.email;
}

function pick(row: Row): void {
    if (row.disabled) {
        return;
    }

    const selection: InviteSelection =
        row.kind === 'friend'
            ? {
                  accountId: row.friend.id,
                  email: row.friend.email,
                  name: row.friend.name,
              }
            : { accountId: null, email: row.email };

    model.value = [...model.value, selection];
    query.value = '';
    open.value = false;
}

function remove(selection: InviteSelection): void {
    model.value = model.value.filter((entry) => entry !== selection);
}

function removeAndRefocus(selection: InviteSelection): void {
    remove(selection);
    // The chip and its button are gone, so focus returns to the input rather than the page body.
    document.getElementById(props.id)?.focus();
}

function move(step: number): void {
    if (!rows.value.length) {
        return;
    }

    open.value = true;
    active.value =
        (active.value + step + rows.value.length) % rows.value.length;
}

function onKey(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        move(event.key === 'ArrowDown' ? 1 : -1);

        return;
    }

    if (event.key === 'Escape') {
        if (!open.value) {
            return;
        }

        event.preventDefault();
        open.value = false;

        return;
    }

    if (event.key === 'Backspace' && !query.value && model.value.length) {
        remove(model.value[model.value.length - 1]);

        return;
    }

    if (event.key !== 'Enter' && event.key !== ',') {
        return;
    }

    // Enter never submits the surrounding form from this input; it adds the active suggestion instead.
    event.preventDefault();
    const row = rows.value[active.value];

    if (!row) {
        return;
    }

    pick(row);
}

async function copyLink(url: string | null = props.linkUrl): Promise<void> {
    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
    } catch {
        emit('copyFailed', url);

        return;
    }

    copied.value = true;
    emit('copied', url);
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => (copied.value = false), 2000);
}

function onLinkClick(): void {
    if (!props.linkUrl) {
        emit('createLink');

        return;
    }

    void copyLink();
}

onBeforeUnmount(() => clearTimeout(copiedTimer));

defineExpose({ copyLink });
</script>

<template>
    <div class="flex min-w-0 flex-col gap-(--space-inner)" data-feature="F-04">
        <FormField :label="label" :control="id" :hint="hintText" :error="error">
            <template #default="{ describedBy, invalid }">
                <div class="relative">
                    <Input
                        :id="id"
                        v-model="query"
                        :placeholder="t('invite.placeholder')"
                        :disabled="disabled"
                        autocomplete="off"
                        spellcheck="false"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-haspopup="listbox"
                        :aria-expanded="expanded"
                        :aria-controls="listId"
                        :aria-activedescendant="
                            expanded ? optionId(active) : undefined
                        "
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid || undefined"
                        class="bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) max-w-(--field-max-width) rounded-(--control-radius) px-3.5 text-[15px]"
                        @input="open = true"
                        @focus="open = true"
                        @blur="open = false"
                        @keydown="onKey"
                    />
                    <ul
                        v-show="expanded"
                        :id="listId"
                        role="listbox"
                        :aria-label="t('invite.suggestions')"
                        class="box-float absolute top-[calc(100%+4px)] right-0 left-0 z-20 flex max-w-(--field-max-width) flex-col overflow-hidden rounded-(--radius-sm)! p-0"
                        data-feature="F-05"
                    >
                        <li
                            v-for="(row, index) in rows"
                            :id="optionId(index)"
                            :key="rowKey(row)"
                            role="option"
                            :aria-selected="index === active"
                            :aria-disabled="row.disabled || undefined"
                            class="flex min-h-(--control-height) cursor-pointer items-center gap-2.5 px-3 py-2 text-sm"
                            :class="[
                                index === active && 'bg-surface-muted',
                                row.disabled &&
                                    'text-fg-muted cursor-not-allowed',
                            ]"
                            @mousedown.prevent
                            @click="pick(row)"
                            @mousemove="active = index"
                        >
                            <template v-if="row.kind === 'friend'">
                                <Avatar size="sm">
                                    <AvatarFallback>{{
                                        initials(
                                            row.friend.name,
                                            row.friend.email,
                                        )
                                    }}</AvatarFallback>
                                </Avatar>
                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="truncate">{{
                                        row.friend.name || t('common.noName')
                                    }}</span>
                                    <span
                                        class="text-fg-soft truncate text-[13px]"
                                        >{{ row.friend.email }}</span
                                    >
                                </span>
                            </template>
                            <template v-else>
                                <span
                                    class="bg-primary-soft text-primary-soft-fg grid size-7 shrink-0 place-items-center rounded-full"
                                >
                                    <MailIcon
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span class="min-w-0 flex-1 truncate">{{
                                    t('invite.addEmail', { email: row.email })
                                }}</span>
                            </template>
                            <Badge v-if="row.disabled" variant="secondary">{{
                                t('invite.alreadyMember')
                            }}</Badge>
                        </li>
                    </ul>
                </div>
            </template>
        </FormField>

        <ul
            v-if="model.length"
            class="flex min-w-0 flex-wrap gap-2"
            :aria-label="t('invite.selected')"
        >
            <li
                v-for="selection in model"
                :key="selection.accountId ?? selection.email"
                class="bg-depth-2 border-depth-2-border inline-flex max-w-full min-w-0 items-center gap-1.5 rounded-full border py-0.5 pr-1 pl-1 text-sm font-medium"
            >
                <Avatar v-if="selection.accountId !== null" size="sm">
                    <AvatarFallback>{{
                        initials(selection.name, selection.email)
                    }}</AvatarFallback>
                </Avatar>
                <MailIcon
                    v-else
                    class="text-fg-soft ml-1.5 size-4 shrink-0"
                    aria-hidden="true"
                />
                <span class="min-w-0 truncate">{{
                    selectionName(selection)
                }}</span>
                <button
                    type="button"
                    :disabled="disabled"
                    :aria-label="
                        t('invite.remove', { name: selectionName(selection) })
                    "
                    class="text-fg-muted hover:bg-surface-sunken hover:text-fg focus-visible:bg-surface-sunken grid size-7 shrink-0 place-items-center rounded-full"
                    @click="removeAndRefocus(selection)"
                >
                    <XIcon class="size-3.5" aria-hidden="true" />
                </button>
            </li>
        </ul>

        <div v-if="submitLabel || link" class="flex flex-wrap gap-2">
            <Button
                v-if="submitLabel"
                type="button"
                size="lg"
                class="h-(--control-height) flex-auto px-4 md:flex-none"
                :disabled="disabled || !model.length"
                @click="emit('submit')"
            >
                <PlusIcon aria-hidden="true" /> {{ submitLabel }}
            </Button>
            <Button
                v-if="link"
                type="button"
                variant="secondary"
                size="lg"
                class="h-(--control-height) flex-auto px-4 md:flex-none"
                :disabled="disabled || !linkAvailable"
                @click="onLinkClick"
            >
                <CheckIcon v-if="copied" aria-hidden="true" />
                <LinkIcon v-else aria-hidden="true" />
                {{ copied ? t('group.link.copied') : t('group.link.copy') }}
            </Button>
            <span class="sr-only" aria-live="polite">{{
                copied ? t('group.link.copied') : ''
            }}</span>
        </div>

        <p v-if="link && !linkAvailable" class="text-fg-soft text-[13px]">
            {{ t('invite.linkLater') }}
        </p>
    </div>
</template>
