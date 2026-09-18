<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';
import SharedRosterItemCard from '@/components/SharedRosterItemCard.vue';
import OnboardingForm, {
    type PendingClaim,
} from '@/components/OnboardingForm.vue';
import type { Auth, SharedRoster, SharedRosterItem } from '@/types';
import { store as storeSharedItem } from '@/actions/App/Http/Controllers/SharedRosterItemController';

defineOptions({ layout: WebsiteLayout });

const props = defineProps<{
    token: string;
    roster: SharedRoster;
}>();

const page = usePage<{ auth: Auth }>();
const isAuthenticated = computed(() => !!page.props.auth.user);
const toast = useToast();

// --- Login dialog: opened in place instead of navigating away, so claiming
// or adding a thing from a shared link never leaves this page. ---
const loginDialogVisible = ref(false);
const loginDialogHeader = ref('');
const loginDialogMessage = ref('');
const pendingClaim = ref<PendingClaim | null>(null);

function requestLogin(item: SharedRosterItem, quantity: number | null) {
    pendingClaim.value = {
        rosterToken: props.token,
        itemId: item.id,
        quantity,
    };
    loginDialogHeader.value = 'Log in to claim this thing';
    loginDialogMessage.value = `Claiming "${item.name}" needs a quick login or sign-up first.`;
    loginDialogVisible.value = true;
}

watch(
    () => page.props.auth.user,
    (user) => {
        if (user) {
            loginDialogVisible.value = false;
        }
    },
);

// --- Add a thing: only offered when the owner turned this on for the
// shared link (roster.can_add_items). Guests log in first, then add. ---
const addDialogVisible = ref(false);

const addForm = useForm({
    name: '',
    quantity: null as number | null,
    unit: '',
    notes: '',
});

function openAddThing() {
    if (!isAuthenticated.value) {
        pendingClaim.value = {
            rosterToken: props.token,
            itemId: null,
            quantity: null,
        };
        loginDialogHeader.value = 'Log in to add a thing';
        loginDialogMessage.value =
            'Adding things to this list needs a quick login or sign-up first.';
        loginDialogVisible.value = true;
        return;
    }

    addForm.reset();
    addForm.clearErrors();
    addDialogVisible.value = true;
}

function submitAddThing() {
    addForm.post(storeSharedItem(props.token).url, {
        preserveScroll: true,
        onSuccess: () => {
            addDialogVisible.value = false;
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: Object.values(errors)[0] ?? 'Something went wrong.',
                life: 6000,
            });
        },
    });
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString(undefined, {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

const hasDatedItems = computed(() =>
    props.roster.items.some((item) => item.is_past !== null),
);
const upcomingItems = computed(() =>
    props.roster.items.filter((item) => item.is_past !== true),
);
const pastItems = computed(() =>
    props.roster.items.filter((item) => item.is_past === true),
);
</script>

<template>
    <Head :title="roster.title" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 px-6 py-16">
        <div class="flex flex-col gap-2">
            <Tag severity="secondary" value="Shared list" class="w-fit" />
            <h1
                class="text-surface-900 dark:text-surface-0 text-xl font-semibold"
            >
                {{ roster.title }}
            </h1>
            <Tag
                v-if="roster.date"
                :value="formatDate(roster.date)"
                class="w-fit"
            />
            <p
                v-if="roster.description"
                class="text-surface-700 dark:text-surface-300 max-w-2xl text-sm"
            >
                {{ roster.description }}
            </p>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-medium">Things</h2>
                <Button
                    v-if="roster.can_add_items"
                    label="Add thing"
                    size="small"
                    @click="openAddThing"
                />
            </div>

            <div
                v-if="roster.items.length === 0"
                class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-8 text-center text-sm"
            >
                No things yet.
            </div>

            <template v-else-if="!hasDatedItems">
                <ul class="flex flex-col gap-4">
                    <li v-for="item in roster.items" :key="item.id">
                        <SharedRosterItemCard
                            :token="token"
                            :item="item"
                            @request-login="requestLogin"
                        />
                    </li>
                </ul>
            </template>

            <template v-else>
                <div v-if="upcomingItems.length" class="mb-6">
                    <h3
                        class="text-surface-500 mb-3 text-sm font-medium tracking-wide uppercase"
                    >
                        Upcoming
                    </h3>
                    <ul class="flex flex-col gap-4">
                        <li v-for="item in upcomingItems" :key="item.id">
                            <SharedRosterItemCard
                                :token="token"
                                :item="item"
                                :date-label="
                                    item.date ? formatDate(item.date) : null
                                "
                                @request-login="requestLogin"
                            />
                        </li>
                    </ul>
                </div>
                <div v-if="pastItems.length">
                    <h3
                        class="text-surface-500 mb-3 text-sm font-medium tracking-wide uppercase"
                    >
                        Past
                    </h3>
                    <ul class="flex flex-col gap-4 opacity-60">
                        <li v-for="item in pastItems" :key="item.id">
                            <SharedRosterItemCard
                                :token="token"
                                :item="item"
                                :date-label="
                                    item.date ? formatDate(item.date) : null
                                "
                                @request-login="requestLogin"
                            />
                        </li>
                    </ul>
                </div>
            </template>
        </div>
    </div>

    <Dialog
        v-model:visible="loginDialogVisible"
        modal
        :header="loginDialogHeader"
        class="w-full max-w-md"
    >
        <p class="text-surface-500 mb-4 text-sm">
            {{ loginDialogMessage }}
        </p>
        <OnboardingForm :pending-claim="pendingClaim" />
    </Dialog>

    <Dialog
        v-model:visible="addDialogVisible"
        modal
        header="Add a thing"
        class="w-full max-w-md"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submitAddThing">
            <div class="flex flex-col gap-2">
                <label for="shared-item-name" class="text-sm font-medium">
                    Name <span class="text-red-500">*</span>
                </label>
                <InputText
                    id="shared-item-name"
                    v-model="addForm.name"
                    autofocus
                    :invalid="!!addForm.errors.name"
                />
                <small v-if="addForm.errors.name" class="text-red-500">
                    {{ addForm.errors.name }}
                </small>
            </div>

            <div class="flex gap-3">
                <div class="flex flex-1 flex-col gap-2">
                    <label
                        for="shared-item-quantity"
                        class="text-sm font-medium"
                    >
                        Quantity
                    </label>
                    <InputNumber
                        id="shared-item-quantity"
                        v-model="addForm.quantity"
                        :min="0.01"
                        :max-fraction-digits="2"
                        :invalid="!!addForm.errors.quantity"
                    />
                </div>
                <div class="flex flex-1 flex-col gap-2">
                    <label for="shared-item-unit" class="text-sm font-medium">
                        Unit
                    </label>
                    <InputText
                        id="shared-item-unit"
                        v-model="addForm.unit"
                        placeholder="kg, pieces, ..."
                        :invalid="!!addForm.errors.unit"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label for="shared-item-notes" class="text-sm font-medium">
                    Notes
                </label>
                <Textarea
                    id="shared-item-notes"
                    v-model="addForm.notes"
                    rows="2"
                    :invalid="!!addForm.errors.notes"
                />
            </div>

            <div class="flex gap-3">
                <Button
                    type="submit"
                    label="Add thing"
                    :loading="addForm.processing"
                />
                <Button
                    type="button"
                    label="Cancel"
                    severity="secondary"
                    text
                    @click="addDialogVisible = false"
                />
            </div>
        </form>
    </Dialog>
</template>
