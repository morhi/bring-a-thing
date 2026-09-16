<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import ProgressBar from 'primevue/progressbar';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import type { Auth, SharedRosterItem } from '@/types';
import { store as storeSharedClaim } from '@/actions/App/Http/Controllers/SharedRosterClaimController';

const props = defineProps<{
    token: string;
    item: SharedRosterItem;
    dateLabel?: string | null;
}>();

const emit = defineEmits<{
    'request-login': [item: SharedRosterItem, quantity: number | null];
}>();

const page = usePage<{ auth: Auth }>();
const isAuthenticated = computed(() => !!page.props.auth.user);
const toast = useToast();

function claimedTotal(): number {
    return props.item.claims.reduce(
        (sum, claim) => sum + Number(claim.quantity ?? 0),
        0,
    );
}

function remaining(): number {
    return Number(props.item.quantity) - claimedTotal();
}

const claimQuantity = ref<number | null>(remaining());

const claimQuantityInvalid = computed(
    () =>
        claimQuantity.value === null ||
        claimQuantity.value <= 0 ||
        claimQuantity.value > remaining(),
);

function showRequestError(errors: Record<string, string>) {
    const message = Object.values(errors)[0] ?? 'Something went wrong.';
    toast.add({ severity: 'error', summary: message, life: 6000 });
}

function submitClaim(quantity: number | null) {
    if (!isAuthenticated.value) {
        emit('request-login', props.item, quantity);
        return;
    }

    router.post(
        storeSharedClaim({ token: props.token, item: props.item }).url,
        { quantity },
        { preserveScroll: true, onError: showRequestError },
    );
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
                        :value="value.value ?? ''"
                    />
                </div>
            </div>
        </div>

        <div
            class="border-surface-100 dark:border-surface-800 mt-3 border-t pt-3"
        >
            <template v-if="item.quantity === null">
                <div class="flex items-center gap-3">
                    <template v-if="!item.claims.length">
                        <Button
                            label="I'll bring this"
                            size="small"
                            @click="submitClaim(null)"
                        />
                    </template>
                    <template v-else>
                        <Tag
                            severity="info"
                            :value="`Claimed by ${item.claims[0].user_name}`"
                        />
                    </template>
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
                    v-if="item.claims.length"
                    class="mt-2 flex flex-wrap gap-2"
                >
                    <Tag
                        v-for="claim in item.claims"
                        :key="claim.id"
                        severity="secondary"
                        :value="`${claim.user_name}: ${claim.quantity}${item.unit ? ` ${item.unit}` : ''}`"
                    />
                </div>
                <div
                    v-if="remaining() > 0"
                    class="mt-3 flex items-center gap-2"
                >
                    <InputNumber
                        v-model="claimQuantity"
                        :min="0.01"
                        :max="remaining()"
                        :max-fraction-digits="2"
                        :suffix="item.unit ? ` ${item.unit}` : ''"
                        :placeholder="`${remaining()}`"
                        :invalid="claimQuantityInvalid"
                        size="small"
                        class="w-32"
                    />
                    <Button
                        label="Claim"
                        size="small"
                        :disabled="claimQuantityInvalid"
                        @click="submitClaim(claimQuantity)"
                    />
                </div>
            </template>
        </div>
    </div>
</template>
