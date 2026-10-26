<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    Link2,
    ListChecks,
    LogIn,
    Plus,
    X,
} from '@lucide/vue';
import { computed, nextTick, ref, useTemplateRef } from 'vue';
import { toast } from 'vue-sonner';
import { store as storeSharedClaim } from '@/actions/App/Http/Controllers/SharedRosterClaimController';
import { store as storeSharedItem } from '@/actions/App/Http/Controllers/SharedRosterItemController';
import type {
    ClaimEntry,
    ClaimRequest,
} from '@/components/app/claim-controls.vue';
import EmptyState from '@/components/app/empty-state.vue';
import FormField from '@/components/app/form-field.vue';
import ItemRow from '@/components/app/item-row.vue';
import LoginForm from '@/components/app/login-form.vue';
import type { LoginPendingClaim } from '@/components/app/login-form.vue';
import { formatDate } from '@/components/app/poll/dates';
import ProgressBar from '@/components/app/progress-bar.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/use-i18n';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';
import { groupItemsByDate, itemFields, relativeDay } from '@/lib/roster-items';

defineOptions({ layout: WebsiteLayout });

/**
 * Shared list for people with the share link (F-19).
 *
 * Guests see the list without other people's email addresses; claiming or adding
 * opens the login on the same page, and the pending claim completes after it.
 */
const props = defineProps<{ roster: App.Data.SharedRosterData }>();

const { t, locale } = useI18n();
const page = usePage();

const loggedIn = computed(() => page.props.auth.user !== null);
const items = computed(() => props.roster.items);
const doneCount = computed(
    () => items.value.filter((item) => item.is_fully_claimed).length,
);
const groups = computed(() => groupItemsByDate(items.value, locale.value));
const grouped = computed(
    () => groups.value.length > 1 || groups.value[0]?.key !== 'all',
);
const listDate = computed(() =>
    props.roster.date
        ? formatDate(props.roster.date, locale.value, {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : null,
);

function claimsOf(item: App.Data.RosterItemData): ClaimEntry[] {
    return item.claims.map((claim, index) => ({
        id: `${item.id}-${index}`,
        accountId: null,
        name: claim.user_name,
        quantity: claim.quantity,
        isOwn: false,
        canRemove: false,
    }));
}

// In-place login (F-01, F-19): the pending action rides along and completes after the login

type Pending =
    | { kind: 'claim'; item: App.Data.RosterItemData; quantity: number | null }
    | { kind: 'add' }
    | null;
const loginOpen = ref(false);
const pending = ref<Pending>(null);
const loginPanel = useTemplateRef<HTMLElement>('loginPanel');

const pendingClaim = computed<LoginPendingClaim>(() => ({
    rosterToken: props.roster.token,
    itemId: pending.value?.kind === 'claim' ? pending.value.item.id : null,
    quantity: pending.value?.kind === 'claim' ? pending.value.quantity : null,
}));

// The login form reads the pending claim once, so a new pending action needs a fresh form
const loginKey = computed(() => JSON.stringify(pendingClaim.value));

const pendingLabel = computed(() => {
    if (pending.value?.kind === 'claim') {
        return t('list.guest.pendingClaim', { item: pending.value.item.name });
    }

    return pending.value?.kind === 'add' ? t('list.guest.pendingAdd') : '';
});

function openLogin(next: Pending = null): void {
    pending.value = next;
    loginOpen.value = true;
    nextTick(() => {
        loginPanel.value?.scrollIntoView({
            block: 'center',
            behavior: 'smooth',
        });
        loginPanel.value
            ?.querySelector<HTMLInputElement>('input[type="email"]')
            ?.focus({ preventScroll: true });
    });
}

const claimingItem = ref<number | null>(null);
const claimErrors = ref<Record<number, string>>({});

function claim(item: App.Data.RosterItemData, request: ClaimRequest): void {
    if (!loggedIn.value) {
        openLogin({ kind: 'claim', item, quantity: request.quantity });

        return;
    }

    claimingItem.value = item.id;
    claimErrors.value = { ...claimErrors.value, [item.id]: '' };

    router.post(
        storeSharedClaim([props.roster.token, item.slug]).url,
        request.quantity === null ? {} : { quantity: request.quantity },
        {
            preserveScroll: true,
            onError: (errors) => {
                const message = Object.values(errors)[0] ?? '';

                claimErrors.value = {
                    ...claimErrors.value,
                    [item.id]: message,
                };
                toast.error(message);
            },
            onFinish: () => (claimingItem.value = null),
        },
    );
}

// Adding (F-14 setting): guests log in first, logged-in visitors add right away

const addOpen = ref(false);
const addForm = useForm({
    name: '',
    quantity: '' as string | number,
    unit: '',
    notes: '',
});

function startAdd(): void {
    if (!loggedIn.value) {
        openLogin({ kind: 'add' });

        return;
    }

    addForm.reset();
    addForm.clearErrors();
    addOpen.value = true;
}

function saveItem(): void {
    const hasQuantity = addForm.quantity !== '' && addForm.quantity !== null;

    addForm
        .transform((data) => ({
            name: data.name,
            quantity: hasQuantity ? Number(data.quantity) : null,
            unit: hasQuantity && data.unit.trim() ? data.unit.trim() : null,
            notes: data.notes.trim() || null,
        }))
        .post(storeSharedItem(props.roster.token).url, {
            preserveScroll: true,
            onSuccess: () => (addOpen.value = false),
        });
}

const buttonClass = 'h-(--control-height) px-4';
const chipClass =
    'border-border text-fg-muted inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[13px] font-semibold';
const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) rounded-(--control-radius) px-3.5 text-[15px]';
</script>

<template>
    <Head :title="roster.title" />

    <div class="flex flex-col gap-10" data-feature="F-19">
        <header class="flex flex-col gap-[18px]" data-feature="F-13">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 flex-[1_1_320px] flex-col gap-2.5">
                    <h1 class="text-3xl break-words">{{ roster.title }}</h1>
                    <p
                        v-if="roster.description"
                        class="text-fg-muted max-w-[65ch] text-[17px] break-words whitespace-pre-line"
                    >
                        {{ roster.description }}
                    </p>
                    <div
                        v-if="listDate && roster.date"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span :class="chipClass">
                            <CalendarDays class="size-3.5" aria-hidden="true" />
                            {{ listDate }}
                        </span>
                        <Badge
                            v-if="roster.is_past"
                            class="bg-surface-muted text-fg-muted"
                            >{{ t('list.past') }}</Badge
                        >
                        <Badge
                            v-else
                            class="bg-primary-soft text-primary-soft-fg"
                            >{{ relativeDay(roster.date) }}</Badge
                        >
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="!loggedIn && !loginOpen"
                        variant="outline"
                        :class="buttonClass"
                        @click="openLogin()"
                    >
                        <LogIn aria-hidden="true" />
                        {{ t('list.guest.login') }}
                    </Button>
                    <Button
                        v-if="roster.can_add_items && items.length"
                        :class="buttonClass"
                        data-feature="F-14"
                        @click="startAdd"
                    >
                        <Plus aria-hidden="true" />
                        {{ t('list.item.add') }}
                    </Button>
                </div>
            </div>

            <span
                v-if="items.length"
                class="text-fg-muted flex max-w-[360px] items-center gap-2.5 text-sm tabular-nums"
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

            <div
                v-if="!loggedIn"
                class="border-border bg-surface-muted text-fg flex gap-3 rounded-xl border p-4 text-sm"
            >
                <Link2
                    class="text-fg-muted mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ t('list.guest.note') }}</span>
            </div>
            <div
                v-if="roster.is_all_taken"
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
            v-if="loginOpen && !loggedIn"
            ref="loginPanel"
            class="box flex max-w-[560px] flex-col gap-4 p-5 sm:p-7"
            aria-labelledby="guest-login-title"
            data-feature="F-01"
        >
            <div class="flex items-start justify-between gap-3">
                <h2 id="guest-login-title" class="text-xl">
                    {{
                        pending
                            ? t('list.guest.loginToContinue')
                            : t('welcome.login.title')
                    }}
                </h2>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :aria-label="t('dialog.close')"
                    @click="loginOpen = false"
                >
                    <X />
                </Button>
            </div>
            <p v-if="pendingLabel" class="text-fg-muted">{{ pendingLabel }}</p>
            <LoginForm
                :key="loginKey"
                :expires-in-minutes="roster.link_expires_in_minutes"
                :pending-claim="pendingClaim"
                id-prefix="guest-login"
            />
        </section>

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
                            :item="{
                                id: item.id,
                                name: item.name,
                                quantity: item.quantity,
                                unit: item.unit,
                                notes: item.notes,
                                fields: itemFields(item, roster.custom_fields),
                            }"
                            :claims="claimsOf(item)"
                            :can-claim="item.can_claim"
                            :error="claimErrors[item.id] || null"
                            :processing="claimingItem === item.id"
                            @claim="claim(item, $event)"
                        />
                    </li>
                </ul>
            </div>
        </section>
        <EmptyState
            v-else
            :title="t('list.empty.title')"
            :text="
                roster.can_add_items
                    ? t('list.empty.textAdd')
                    : t('list.empty.textWait')
            "
            :icon="ListChecks"
        >
            <Button
                v-if="roster.can_add_items"
                size="lg"
                :class="buttonClass"
                data-feature="F-14"
                @click="startAdd"
            >
                <Plus aria-hidden="true" />
                {{ t('list.empty.add') }}
            </Button>
        </EmptyState>

        <Dialog v-model:open="addOpen">
            <DialogContent
                class="box-float max-h-[calc(100dvh-2rem)] overflow-y-auto p-6 sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">{{
                        t('list.item.addTitle')
                    }}</DialogTitle>
                    <DialogDescription class="sr-only">{{
                        roster.title
                    }}</DialogDescription>
                </DialogHeader>
                <form
                    id="shared-item-form"
                    class="flex flex-col gap-(--space-stack)"
                    novalidate
                    data-feature="F-14"
                    @submit.prevent="saveItem"
                >
                    <FormField
                        :label="t('list.item.name')"
                        control="shared-item-name"
                        required
                        :error="addForm.errors.name"
                    >
                        <template #default="{ describedBy, invalid }">
                            <Input
                                id="shared-item-name"
                                v-model="addForm.name"
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
                            control="shared-item-quantity"
                            :hint="t('list.item.quantityHint')"
                            :error="addForm.errors.quantity"
                        >
                            <template #default="{ describedBy, invalid }">
                                <Input
                                    id="shared-item-quantity"
                                    v-model="addForm.quantity"
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
                            control="shared-item-unit"
                            :error="addForm.errors.unit"
                        >
                            <template #default="{ describedBy, invalid }">
                                <Input
                                    id="shared-item-unit"
                                    v-model="addForm.unit"
                                    :placeholder="
                                        t('list.item.unitPlaceholder')
                                    "
                                    :disabled="
                                        addForm.quantity === '' ||
                                        addForm.quantity === null
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
                        control="shared-item-notes"
                        :error="addForm.errors.notes"
                    >
                        <template #default="{ describedBy, invalid }">
                            <Textarea
                                id="shared-item-notes"
                                v-model="addForm.notes"
                                rows="2"
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                            />
                        </template>
                    </FormField>
                </form>
                <DialogFooter class="flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="addOpen = false"
                        >{{ t('dialog.cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        form="shared-item-form"
                        :disabled="addForm.processing"
                        >{{ t('list.item.add') }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
