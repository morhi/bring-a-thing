<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';
import SharedRosterItemCard from '@/components/SharedRosterItemCard.vue';
import OnboardingForm, {
    type PendingClaim,
} from '@/components/OnboardingForm.vue';
import type { Auth, SharedRoster, SharedRosterItem } from '@/types';

defineOptions({ layout: WebsiteLayout });

const props = defineProps<{
    token: string;
    roster: SharedRoster;
}>();

const page = usePage<{ auth: Auth }>();

// --- Login dialog: opened in place instead of navigating away, so claiming
// an item from a shared link never leaves this page. ---
const loginDialogVisible = ref(false);
const pendingClaim = ref<PendingClaim | null>(null);
const pendingClaimItemName = ref('');

function requestLogin(item: SharedRosterItem, quantity: number | null) {
    pendingClaim.value = {
        rosterToken: props.token,
        itemId: item.id,
        quantity,
    };
    pendingClaimItemName.value = item.name;
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
            <h2 class="mb-4 text-lg font-medium">Things</h2>

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
        header="Log in to claim this thing"
        class="w-full max-w-md"
    >
        <p class="text-surface-500 mb-4 text-sm">
            Claiming "{{ pendingClaimItemName }}" needs a quick login or sign-up
            first.
        </p>
        <OnboardingForm :pending-claim="pendingClaim" />
    </Dialog>
</template>
