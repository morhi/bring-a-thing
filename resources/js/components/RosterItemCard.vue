<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Times from '@primeicons/vue/times';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import ProgressBar from 'primevue/progressbar';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import type { Auth, Roster, RosterItem } from '@/types';
import {
    destroy as destroyClaim,
    store as storeClaim,
} from '@/actions/App/Http/Controllers/RosterItemClaimController';

const props = defineProps<{
    roster: Roster;
    item: RosterItem;
    canManage: boolean;
    dateLabel?: string | null;
}>();

const emit = defineEmits<{
    edit: [item: RosterItem];
    delete: [item: RosterItem];
}>();

const page = usePage<{ auth: Auth }>();
const currentUserId = computed(() => page.props.auth.user?.id ?? null);
const toast = useToast();

function myClaim() {
    return (
        props.item.claims?.find(
            (claim) => claim.user_id === currentUserId.value,
        ) ?? null
    );
}

function claimedTotal(): number {
    return (props.item.claims ?? []).reduce(
        (sum, claim) => sum + Number(claim.quantity ?? 0),
        0,
    );
}

function remaining(): number {
    return Number(props.item.quantity) - claimedTotal();
}

/** What other members have claimed, excluding the current user's own claim. */
function othersTotal(): number {
    return (props.item.claims ?? [])
        .filter((claim) => claim.user_id !== currentUserId.value)
        .reduce((sum, claim) => sum + Number(claim.quantity ?? 0), 0);
}

/** The most the current user could set their own claim to. */
function maxClaimable(): number {
    return Number(props.item.quantity) - othersTotal();
}

const claimQuantityOverride = ref<number | null>(null);

const claimQuantity = computed<number | null>(() => {
    if (claimQuantityOverride.value !== null) {
        return claimQuantityOverride.value;
    }

    const existing = myClaim();

    return existing?.quantity !== null && existing?.quantity !== undefined
        ? Number(existing.quantity)
        : remaining();
});

const claimQuantityInvalid = computed(
    () =>
        claimQuantity.value === null ||
        claimQuantity.value <= 0 ||
        claimQuantity.value > maxClaimable(),
);

function showRequestError(errors: Record<string, string>) {
    const message = Object.values(errors)[0] ?? 'Something went wrong.';
    toast.add({ severity: 'error', summary: message, life: 6000 });
}

function submitClaim(quantity: number | null, userId?: number) {
    router.post(
        storeClaim({ roster: props.roster, item: props.item }).url,
        { quantity, user_id: userId ?? null },
        { preserveScroll: true, onError: showRequestError },
    );
}

function unclaim(userId?: number) {
    router.delete(
        destroyClaim({ roster: props.roster, item: props.item }).url,
        {
            data: { user_id: userId ?? null },
            preserveScroll: true,
            onError: showRequestError,
        },
    );
}

/** Group members who haven't already claimed this item, for the owner/admin "claim for" control. */
const unclaimedMembers = computed(() => {
    const claimedUserIds = new Set(
        (props.item.claims ?? []).map((claim) => claim.user_id),
    );

    return (props.roster.group?.members ?? [])
        .filter((member) => !claimedUserIds.has(member.id))
        .map((member) => ({
            id: member.id,
            label: member.name ?? member.email,
        }));
});

const claimForUserId = ref<number | null>(null);
const claimForQuantity = ref<number | null>(null);

function submitClaimFor() {
    if (claimForUserId.value === null) {
        return;
    }

    submitClaim(
        props.item.quantity === null
            ? null
            : (claimForQuantity.value ?? remaining()),
        claimForUserId.value,
    );
    claimForUserId.value = null;
    claimForQuantity.value = null;
}
</script>

<template>
    <div
        class="border-surface-200 dark:border-surface-700 rounded-lg border p-4"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2">
                    <span
                        class="text-surface-900 dark:text-surface-0 font-medium"
                    >
                        {{ item.name }}
                    </span>
                    <span v-if="item.quantity" class="text-surface-500 text-sm">
                        ({{ item.quantity
                        }}{{ item.unit ? ` ${item.unit}` : '' }})
                    </span>
                    <Tag v-if="dateLabel" severity="info" :value="dateLabel" />
                </div>
                <p v-if="item.notes" class="text-surface-500 text-sm">
                    {{ item.notes }}
                </p>
                <div
                    v-if="item.custom_field_values?.some((v) => v.value)"
                    class="flex flex-wrap gap-2"
                >
                    <Tag
                        v-for="value in item.custom_field_values.filter(
                            (v) => v.value,
                        )"
                        :key="value.id"
                        severity="secondary"
                        :value="`${value.custom_field?.name}: ${value.value}`"
                    />
                </div>
            </div>
            <div v-if="canManage" class="flex shrink-0 gap-1">
                <Button
                    label="Edit"
                    severity="secondary"
                    text
                    size="small"
                    @click="emit('edit', item)"
                />
                <Button
                    label="Remove"
                    severity="danger"
                    text
                    size="small"
                    @click="emit('delete', item)"
                />
            </div>
        </div>

        <div
            class="border-surface-100 dark:border-surface-800 mt-3 border-t pt-3"
        >
            <template v-if="item.quantity === null">
                <div class="flex flex-wrap items-center gap-3">
                    <template v-if="!item.claims?.length">
                        <Button
                            label="I'll bring this"
                            size="small"
                            @click="submitClaim(null)"
                        />
                    </template>
                    <template v-else-if="myClaim()">
                        <Tag severity="success" value="You're bringing this" />
                        <Button
                            label="Unclaim"
                            severity="danger"
                            text
                            size="small"
                            @click="unclaim()"
                        />
                    </template>
                    <template v-else>
                        <Tag
                            severity="info"
                            :value="`Claimed by ${item.claims![0].user?.name ?? item.claims![0].user?.email}`"
                        />
                        <Button
                            v-if="canManage"
                            label="Unclaim"
                            severity="danger"
                            text
                            size="small"
                            @click="unclaim(item.claims![0].user_id)"
                        />
                    </template>
                    <div
                        v-if="
                            canManage &&
                            !item.claims?.length &&
                            unclaimedMembers.length > 0
                        "
                        class="flex items-center gap-2"
                    >
                        <Select
                            v-model="claimForUserId"
                            :options="unclaimedMembers"
                            option-label="label"
                            option-value="id"
                            placeholder="Claim for..."
                            size="small"
                            class="w-40"
                        />
                        <Button
                            label="Claim for"
                            size="small"
                            severity="secondary"
                            :disabled="claimForUserId === null"
                            @click="submitClaimFor"
                        />
                    </div>
                </div>
            </template>

            <template v-else>
                <ProgressBar
                    :value="
                        Math.min(
                            100,
                            (claimedTotal() / Number(item.quantity)) * 100,
                        )
                    "
                    :show-value="false"
                    class="h-2"
                />
                <p class="text-surface-500 mt-2 text-xs">
                    {{ claimedTotal() }} / {{ item.quantity
                    }}{{ item.unit ? ` ${item.unit}` : '' }} claimed
                </p>
                <div
                    v-if="item.claims?.length"
                    class="mt-2 flex flex-wrap items-center gap-2"
                >
                    <div
                        v-for="claim in item.claims"
                        :key="claim.id"
                        class="flex items-center gap-1"
                    >
                        <Tag
                            severity="secondary"
                            :value="`${claim.user?.name ?? claim.user?.email}: ${claim.quantity}${item.unit ? ` ${item.unit}` : ''}`"
                        />
                        <Button
                            v-if="canManage && claim.user_id !== currentUserId"
                            severity="danger"
                            text
                            rounded
                            size="small"
                            aria-label="Remove claim"
                            @click="unclaim(claim.user_id)"
                        >
                            <Times class="h-3 w-3" />
                        </Button>
                    </div>
                </div>
                <div
                    v-if="remaining() > 0 || myClaim()"
                    class="mt-3 flex items-center gap-2"
                >
                    <InputNumber
                        :model-value="claimQuantity"
                        :min="0.01"
                        :max="maxClaimable()"
                        :max-fraction-digits="2"
                        :suffix="item.unit ? ` ${item.unit}` : ''"
                        :invalid="claimQuantityInvalid"
                        size="small"
                        class="w-32"
                        @update:model-value="
                            (value) => (claimQuantityOverride = value)
                        "
                    />
                    <Button
                        :label="myClaim() ? 'Update claim' : 'Claim'"
                        size="small"
                        :disabled="claimQuantityInvalid"
                        @click="submitClaim(claimQuantity)"
                    />
                    <Button
                        v-if="myClaim()"
                        label="Unclaim"
                        severity="danger"
                        text
                        size="small"
                        @click="unclaim()"
                    />
                </div>
                <div
                    v-if="
                        canManage &&
                        unclaimedMembers.length > 0 &&
                        remaining() > 0
                    "
                    class="mt-2 flex items-center gap-2"
                >
                    <Select
                        v-model="claimForUserId"
                        :options="unclaimedMembers"
                        option-label="label"
                        option-value="id"
                        placeholder="Claim for..."
                        size="small"
                        class="w-40"
                    />
                    <InputNumber
                        v-model="claimForQuantity"
                        :min="0.01"
                        :max="remaining()"
                        :max-fraction-digits="2"
                        :placeholder="String(remaining())"
                        :suffix="item.unit ? ` ${item.unit}` : ''"
                        size="small"
                        class="w-32"
                    />
                    <Button
                        label="Claim for"
                        size="small"
                        severity="secondary"
                        :disabled="claimForUserId === null"
                        @click="submitClaimFor"
                    />
                </div>
            </template>
        </div>
    </div>
</template>
