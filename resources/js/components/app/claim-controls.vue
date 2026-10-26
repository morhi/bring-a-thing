<script lang="ts">
export type ClaimId = number | string;

export type ClaimEntry = {
    id: ClaimId;
    accountId: ClaimId | null;
    name: string | null;
    quantity: number | null;
    isOwn: boolean;
    canRemove: boolean;
};

export type ClaimCandidate = {
    id: ClaimId;
    name: string;
    // Attendance warning for this person (F-18); set means "Claim anyway" must be confirmed.
    warning?: string | null;
};

export type ClaimRequest = {
    // Null claims for the current person.
    accountId: ClaimId | null;
    quantity: number | null;
};
</script>

<script setup lang="ts">
import { Check, Clock, CircleHelp, UserPlus } from '@lucide/vue';
import { computed, nextTick, ref, useId, watch } from 'vue';
import ClaimBadge from '@/components/app/claim-badge.vue';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/use-i18n';

const props = withDefaults(
    defineProps<{
        quantity: number | null;
        unit?: string | null;
        claims: ClaimEntry[];
        canClaim?: boolean;
        locked?: boolean;
        candidates?: ClaimCandidate[];
        attendanceWarning?: string | null;
        error?: string | null;
        processing?: boolean;
    }>(),
    {
        unit: null,
        canClaim: true,
        locked: false,
        candidates: () => [],
        attendanceWarning: null,
        error: null,
        processing: false,
    },
);

const emit = defineEmits<{
    claim: [request: ClaimRequest];
    release: [claimId: ClaimId];
}>();

const { t, locale } = useI18n();

const inputId = useId();
const errorId = useId();
const hintId = useId();

function formatAmount(value: number): string {
    const number = value.toLocaleString(
        locale.value === 'de' ? 'de-CH' : 'en-GB',
    );

    return props.unit ? `${number} ${props.unit}` : number;
}

const isTask = computed(() => props.quantity === null);
const ownClaim = computed(
    () => props.claims.find((claim) => claim.isOwn) ?? null,
);

const remaining = computed(() => {
    if (props.quantity === null) {
        return props.claims.length ? 0 : 1;
    }

    const claimed = props.claims.reduce(
        (sum, claim) => sum + (claim.quantity ?? 0),
        0,
    );

    return Math.max(0, props.quantity - claimed);
});

const showOwnWarning = computed(
    () =>
        !!props.attendanceWarning &&
        props.canClaim &&
        !props.locked &&
        !ownClaim.value &&
        remaining.value > 0,
);
const showClaimFor = computed(
    () => props.candidates.length > 0 && remaining.value > 0,
);

// The open quantity form: who it claims for and the entered amount.
const form = ref<{
    accountId: ClaimId | null;
    name: string | null;
    warning: string | null;
    amount: string;
} | null>(null);
const localError = ref('');
const inputRef = ref<InstanceType<typeof Input> | null>(null);

function existingClaimOf(accountId: ClaimId | null): ClaimEntry | null {
    if (accountId === null) {
        return ownClaim.value;
    }

    return props.claims.find((claim) => claim.accountId === accountId) ?? null;
}

function availableFor(accountId: ClaimId | null): number {
    return remaining.value + (existingClaimOf(accountId)?.quantity ?? 0);
}

function openForm(
    accountId: ClaimId | null,
    name: string | null,
    warning: string | null,
) {
    const existing = existingClaimOf(accountId);

    form.value = {
        accountId,
        name,
        warning,
        amount: String(existing?.quantity ?? remaining.value),
    };
    localError.value = '';
    nextTick(() => {
        const element = inputRef.value?.$el as HTMLInputElement | undefined;

        element?.focus();
        element?.select();
    });
}

function closeForm() {
    form.value = null;
    localError.value = '';
}

// Kept after closing so the dialog text stays during the close animation and the action handler order does not matter.
const pendingConfirm = ref<{ request: ClaimRequest; text: string } | null>(
    null,
);
const confirmOpen = ref(false);

// F-18: the claim is never blocked, only confirmed when the person is not around.
function request(claim: ClaimRequest, warning: string | null) {
    if (warning) {
        pendingConfirm.value = { request: claim, text: warning };
        confirmOpen.value = true;

        return;
    }

    emit('claim', claim);
}

function confirmClaim() {
    if (!pendingConfirm.value) {
        return;
    }

    emit('claim', pendingConfirm.value.request);
    confirmOpen.value = false;
}

function claimOwn() {
    if (isTask.value) {
        request({ accountId: null, quantity: null }, props.attendanceWarning);

        return;
    }

    openForm(null, null, props.attendanceWarning);
}

function claimFor(candidate: ClaimCandidate) {
    if (isTask.value) {
        request(
            { accountId: candidate.id, quantity: null },
            candidate.warning ?? null,
        );

        return;
    }

    openForm(candidate.id, candidate.name, candidate.warning ?? null);
}

function submitForm() {
    if (!form.value) {
        return;
    }

    const amount = Number(form.value.amount);

    if (!(amount > 0)) {
        localError.value = t('list.claim.amountRequired');

        return;
    }

    const available = availableFor(form.value.accountId);

    if (amount > available) {
        localError.value = t('list.claim.tooMuch', {
            n: formatAmount(available),
        });

        return;
    }

    localError.value = '';
    request(
        { accountId: form.value.accountId, quantity: amount },
        form.value.warning,
    );
}

// A changed claims prop means the server accepted the claim, so the form is done.
watch(
    () => props.claims,
    () => closeForm(),
);

const formError = computed(() => localError.value || props.error || '');
const formLabel = computed(() =>
    form.value?.name
        ? t('list.claim.howMuchFor', { name: form.value.name })
        : t('list.claim.howMuch'),
);
</script>

<template>
    <div class="flex flex-col gap-3" data-feature="F-15">
        <ul
            v-if="claims.length"
            class="border-border flex flex-col border-t pt-1.5"
        >
            <li v-for="claim in claims" :key="claim.id">
                <ClaimBadge
                    :name="claim.name"
                    :quantity="isTask ? null : claim.quantity"
                    :unit="unit"
                    :is-own="claim.isOwn"
                    :can-remove="claim.canRemove"
                    :disabled="processing"
                    @remove="emit('release', claim.id)"
                />
            </li>
        </ul>

        <p
            v-if="showOwnWarning"
            class="text-warning flex items-start gap-2 text-sm font-medium"
            data-feature="F-18"
        >
            <CircleHelp class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>{{ attendanceWarning }}</span>
        </p>

        <form
            v-if="form"
            class="flex flex-col gap-2"
            novalidate
            @submit.prevent="submitForm"
        >
            <Label :for="inputId">{{ formLabel }}</Label>
            <div class="flex flex-wrap items-center gap-2">
                <Input
                    :id="inputId"
                    ref="inputRef"
                    v-model="form.amount"
                    type="number"
                    min="0"
                    step="any"
                    inputmode="decimal"
                    class="w-28"
                    :aria-invalid="formError ? 'true' : undefined"
                    :aria-describedby="
                        formError ? `${errorId} ${hintId}` : hintId
                    "
                    @keydown.esc.prevent="closeForm"
                />
                <span v-if="unit" class="text-fg-muted text-sm">{{
                    unit
                }}</span>
                <Button type="submit" size="sm" :disabled="processing">{{
                    t('list.claim.confirm')
                }}</Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="closeForm"
                    >{{ t('dialog.cancel') }}</Button
                >
            </div>
            <p
                v-if="formError"
                :id="errorId"
                role="alert"
                class="text-danger text-sm"
            >
                {{ formError }}
            </p>
            <p :id="hintId" class="text-fg-muted text-sm">
                {{ t('list.claim.left', { n: formatAmount(remaining) }) }}
            </p>
        </form>

        <div v-else class="flex flex-wrap items-center gap-2">
            <p
                v-if="locked"
                class="text-warning flex items-start gap-2 text-sm font-medium"
            >
                <Clock class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span>{{ t('list.claim.locked') }}</span>
            </p>
            <template v-else-if="canClaim">
                <Button
                    v-if="!ownClaim && remaining > 0"
                    type="button"
                    size="sm"
                    :disabled="processing"
                    @click="claimOwn"
                >
                    <Check aria-hidden="true" />
                    {{ isTask ? t('list.claim.task') : t('list.claim.part') }}
                </Button>
                <Button
                    v-if="ownClaim && !isTask"
                    type="button"
                    variant="secondary"
                    size="sm"
                    :disabled="processing"
                    @click="openForm(null, null, null)"
                >
                    {{ t('list.claim.change') }}
                </Button>
                <Button
                    v-if="ownClaim"
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="processing"
                    @click="emit('release', ownClaim.id)"
                >
                    {{ t('list.claim.removeOwn') }}
                </Button>
            </template>

            <DropdownMenu v-if="showClaimFor">
                <DropdownMenuTrigger as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="processing"
                    >
                        <UserPlus aria-hidden="true" />
                        {{ t('list.claim.for') }}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    class="max-h-[60vh] min-w-56"
                >
                    <DropdownMenuLabel>{{
                        t('list.claim.for')
                    }}</DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="candidate in candidates"
                        :key="candidate.id"
                        @select="claimFor(candidate)"
                    >
                        <span class="min-w-0 flex-1 truncate">{{
                            candidate.name
                        }}</span>
                        <CircleHelp
                            v-if="candidate.warning"
                            class="text-warning"
                            aria-hidden="true"
                        />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <span class="flex-1" aria-hidden="true" />
            <slot name="actions" />
        </div>

        <AlertDialog v-model:open="confirmOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{
                        t('list.attendance.confirmTitle')
                    }}</AlertDialogTitle>
                    <AlertDialogDescription>{{
                        pendingConfirm?.text
                    }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{
                        t('dialog.cancel')
                    }}</AlertDialogCancel>
                    <AlertDialogAction @click="confirmClaim">{{
                        t('list.attendance.claimAnyway')
                    }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
