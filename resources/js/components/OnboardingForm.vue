<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import {
    needsName,
    store as sendMagicLink,
} from '@/actions/App/Http/Controllers/Auth/MagicLinkController';
import { store as loginWithPassword } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';

export type PendingClaim = {
    rosterToken: string;
    itemId: number | null;
    quantity: number | null;
};

const props = withDefaults(
    defineProps<{
        pendingClaim?: PendingClaim | null;
    }>(),
    { pendingClaim: null },
);

const form = useForm({
    email: '',
    name: '',
    roster_name: '',
    password: '',
    pending_claim_roster_token: props.pendingClaim?.rosterToken ?? '',
    pending_claim_item_id: props.pendingClaim?.itemId ?? null,
    pending_claim_quantity: props.pendingClaim?.quantity ?? null,
});

type Step = 'email' | 'name' | 'roster' | 'password' | 'done';

// Claiming from a shared list is meant to take one extra step, so the
// first-list-naming step is skipped entirely when there's a pending claim.
const stepSequence = computed<Step[]>(() =>
    props.pendingClaim ? ['email', 'name'] : ['email', 'name', 'roster'],
);

const step = ref<Step>('email');
const checkingEmail = ref(false);

const isValidEmail = (email: string) =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

async function continueFromEmail() {
    if (!isValidEmail(form.email)) {
        form.setError('email', 'Enter a valid email address.');
        return;
    }

    form.clearErrors('email');
    checkingEmail.value = true;

    try {
        const response = await fetch(
            needsName({ query: { email: form.email } }).url,
        );
        const data = await response.json();

        if (data.hasPassword) {
            step.value = 'password';
        } else if (data.needsName) {
            step.value = 'name';
        } else {
            // Returning, passwordless account: no onboarding prompt needed.
            requestMagicLink();
        }
    } finally {
        checkingEmail.value = false;
    }
}

function continueFromName() {
    if (!form.name.trim()) {
        form.setError('name', 'Enter your name.');
        return;
    }

    form.clearErrors('name');

    if (props.pendingClaim) {
        requestMagicLink();
    } else {
        step.value = 'roster';
    }
}

function requestMagicLink() {
    form.post(sendMagicLink().url, {
        preserveScroll: true,
        onSuccess: () => {
            step.value = 'done';
        },
        onError: (errors) => {
            if (errors.email) {
                step.value = 'email';
            } else if (errors.name) {
                step.value = 'name';
            }
        },
    });
}

function skipRoster() {
    form.roster_name = '';
    requestMagicLink();
}

function loginWithPasswordSubmit() {
    form.post(loginWithPassword().url, {
        onError: () => form.reset('password'),
    });
}

function useMagicLinkInstead() {
    form.reset('password');
    requestMagicLink();
}
</script>

<template>
    <div class="mx-auto w-full max-w-sm text-left">
        <div
            v-if="stepSequence.includes(step)"
            class="mb-4 flex justify-center gap-1.5"
        >
            <span
                v-for="dot in stepSequence"
                :key="dot"
                class="h-1.5 w-6 rounded-full transition-colors"
                :class="
                    stepSequence.indexOf(dot) <= stepSequence.indexOf(step)
                        ? 'bg-primary-500'
                        : 'bg-surface-200 dark:bg-surface-700'
                "
            />
        </div>

        <form
            v-if="step === 'email'"
            class="flex flex-col gap-3"
            @submit.prevent="continueFromEmail"
        >
            <InputText
                v-model="form.email"
                type="email"
                placeholder="you@example.com"
                autocomplete="email"
                autofocus
                :invalid="!!form.errors.email"
            />
            <small v-if="form.errors.email" class="text-red-500">
                {{ form.errors.email }}
            </small>
            <Button
                type="submit"
                label="Continue"
                :loading="checkingEmail || form.processing"
            />
        </form>

        <form
            v-else-if="step === 'name'"
            class="flex flex-col gap-3"
            @submit.prevent="continueFromName"
        >
            <p class="text-surface-500 dark:text-surface-400 text-sm">
                What's your name?
            </p>
            <InputText
                v-model="form.name"
                placeholder="Your name"
                autocomplete="name"
                autofocus
                :invalid="!!form.errors.name"
            />
            <small v-if="form.errors.name" class="text-red-500">
                {{ form.errors.name }}
            </small>
            <Button type="submit" label="Continue" />
            <Button
                type="button"
                label="Back"
                severity="secondary"
                text
                @click="step = 'email'"
            />
        </form>

        <form
            v-else-if="step === 'roster'"
            class="flex flex-col gap-3"
            @submit.prevent="requestMagicLink"
        >
            <p class="text-surface-500 dark:text-surface-400 text-sm">
                Name your first list, e.g. "Sunday Potluck". You can skip this
                and add it later.
            </p>
            <InputText
                v-model="form.roster_name"
                placeholder="Sunday Potluck"
                autofocus
                :invalid="!!form.errors.roster_name"
            />
            <small v-if="form.errors.roster_name" class="text-red-500">
                {{ form.errors.roster_name }}
            </small>
            <Button
                type="submit"
                label="Create my list"
                :loading="form.processing"
            />
            <Button
                type="button"
                label="Skip for now"
                severity="secondary"
                text
                :disabled="form.processing"
                @click="skipRoster"
            />
        </form>

        <form
            v-else-if="step === 'password'"
            class="flex flex-col gap-3"
            @submit.prevent="loginWithPasswordSubmit"
        >
            <p class="text-surface-500 dark:text-surface-400 text-sm">
                Enter your password to log in.
            </p>
            <Password
                v-model="form.password"
                placeholder="Password"
                autocomplete="current-password"
                autofocus
                :feedback="false"
                toggle-mask
                :invalid="!!form.errors.password"
                input-class="w-full"
                class="w-full"
            />
            <small v-if="form.errors.password" class="text-red-500">
                {{ form.errors.password }}
            </small>
            <Button type="submit" label="Log in" :loading="form.processing" />
            <Button
                type="button"
                label="Send a magic link instead"
                severity="secondary"
                text
                :disabled="form.processing"
                @click="useMagicLinkInstead"
            />
            <Button
                type="button"
                label="Back"
                severity="secondary"
                text
                :disabled="form.processing"
                @click="step = 'email'"
            />
        </form>

        <div v-else class="text-center">
            <p class="text-surface-900 dark:text-surface-0 font-semibold">
                Check your email
            </p>
            <p class="text-surface-600 dark:text-surface-300 mt-2 text-sm">
                We've sent a login link to {{ form.email }}.
                <template v-if="pendingClaim?.itemId !== null && pendingClaim">
                    Your claim will be saved as soon as you follow it.
                </template>
                <template v-else-if="pendingClaim">
                    You'll be back on the list as soon as you follow it.
                </template>
                <template v-else-if="form.roster_name">
                    "{{ form.roster_name }}" will be waiting for you.
                </template>
            </p>
        </div>
    </div>
</template>
