<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CheckIcon, ClockIcon, MailIcon } from '@lucide/vue';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import { store as loginWithPassword } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import {
    needsName as lookupEmail,
    store as sendMagicLink,
} from '@/actions/App/Http/Controllers/Auth/MagicLinkController';
import FormField from '@/components/app/form-field.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';

export type LoginPendingClaim = {
    rosterToken: string;
    itemId: number | null;
    quantity: number | null;
};

/**
 * One form for logging in and signing up with a magic link (F-01).
 *
 * The email decides the next step: an account with a password gets the password
 * field, a new or nameless account gets the name field, everyone else gets the
 * link right away. A pending claim from a shared list rides along, so the claim
 * completes after the login (F-19).
 */
const props = withDefaults(
    defineProps<{
        expiresInMinutes: number;
        linkError?: App.Enums.LoginLinkError | null;
        pendingClaim?: LoginPendingClaim | null;
        idPrefix?: string;
    }>(),
    { linkError: null, pendingClaim: null, idPrefix: 'login' },
);

type Step = 'email' | 'name' | 'password' | 'sent';

const { t } = useI18n();

const form = useForm({
    email: '',
    name: '',
    password: '',
    pending_claim_roster_token: props.pendingClaim?.rosterToken ?? '',
    pending_claim_item_id: props.pendingClaim?.itemId ?? null,
    pending_claim_quantity: props.pendingClaim?.quantity ?? null,
});

const step = ref<Step>('email');
const checking = ref(false);
const showLinkError = ref(props.linkError !== null);
const emailInput = useTemplateRef<InstanceType<typeof Input>>('emailInput');

const busy = computed(() => checking.value || form.processing);

const submitLabel = computed(() => {
    if (step.value === 'password') {
        return t('welcome.login.withPassword');
    }

    return step.value === 'name'
        ? t('welcome.login.continue')
        : t('welcome.login.sendLink');
});

const linkErrorText = computed(() =>
    props.linkError === 'invalid'
        ? t('welcome.login.invalidLink')
        : t('welcome.login.expired'),
);

const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) rounded-(--control-radius) px-3.5 text-[15px]';

// A changed address may belong to another account, so the follow-up fields start over
watch(
    () => form.email,
    () => {
        if (step.value !== 'name' && step.value !== 'password') {
            return;
        }

        step.value = 'email';
        form.password = '';
    },
);

function id(part: string): string {
    return `${props.idPrefix}-${part}`;
}

function focusEmail(): void {
    nextTick(() =>
        (emailInput.value?.$el as HTMLInputElement | undefined)?.focus(),
    );
}

function requestNewLink(): void {
    showLinkError.value = false;
    focusEmail();
}

async function submit(): Promise<void> {
    form.clearErrors();

    if (step.value === 'password') {
        form.post(loginWithPassword.url(), {
            onError: () => form.reset('password'),
        });

        return;
    }

    if (step.value === 'name') {
        sendLink();

        return;
    }

    await lookUpAccount();
}

async function lookUpAccount(): Promise<void> {
    checking.value = true;

    try {
        const response = await fetch(
            lookupEmail.url({ query: { email: form.email } }),
            {
                headers: { Accept: 'application/json' },
            },
        );

        if (!response.ok) {
            form.setError('email', t('welcome.login.invalid'));

            return;
        }

        const account = (await response.json()) as {
            needsName: boolean;
            hasPassword: boolean;
        };

        if (account.hasPassword) {
            step.value = 'password';

            return;
        }

        if (account.needsName) {
            step.value = 'name';

            return;
        }

        sendLink();
    } finally {
        checking.value = false;
    }
}

function sendLink(): void {
    form.post(sendMagicLink.url(), {
        preserveScroll: true,
        onSuccess: () => {
            step.value = 'sent';
            showLinkError.value = false;
        },
    });
}

function useLinkInstead(): void {
    form.password = '';
    sendLink();
}

function startOver(): void {
    form.reset();
    form.clearErrors();
    step.value = 'email';
    focusEmail();
}
</script>

<template>
    <div class="flex flex-col gap-5" data-feature="F-01">
        <div
            v-if="showLinkError && linkError"
            role="alert"
            class="border-warning bg-warning-soft text-fg flex gap-3 rounded-xl border p-4 text-sm"
        >
            <ClockIcon
                class="text-warning mt-0.5 size-5 shrink-0"
                aria-hidden="true"
            />
            <div class="flex flex-col items-start gap-2.5">
                <span>{{ linkErrorText }}</span>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="requestNewLink"
                >
                    <MailIcon aria-hidden="true" />
                    {{ t('welcome.login.requestNew') }}
                </Button>
            </div>
        </div>

        <template v-if="step !== 'sent'">
            <form
                class="flex flex-col gap-5"
                novalidate
                @submit.prevent="submit"
            >
                <div class="flex flex-col gap-(--space-stack)">
                    <FormField
                        :label="t('welcome.login.email')"
                        :control="id('email')"
                        required
                        :error="form.errors.email"
                    >
                        <template
                            #default="{ id: controlId, describedBy, invalid }"
                        >
                            <Input
                                :id="controlId"
                                ref="emailInput"
                                v-model.trim="form.email"
                                type="email"
                                autocomplete="email"
                                placeholder="name@example.com"
                                required
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                                :class="inputClass"
                            />
                        </template>
                    </FormField>

                    <FormField
                        v-if="step === 'name'"
                        :label="t('welcome.login.name')"
                        :control="id('name')"
                        required
                        :hint="t('welcome.login.nameHint')"
                        :error="form.errors.name"
                    >
                        <template
                            #default="{ id: controlId, describedBy, invalid }"
                        >
                            <Input
                                :id="controlId"
                                v-model="form.name"
                                autocomplete="name"
                                required
                                autofocus
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                                :class="inputClass"
                            />
                        </template>
                    </FormField>

                    <FormField
                        v-if="step === 'password'"
                        :label="t('welcome.login.password')"
                        :control="id('password')"
                        required
                        :hint="t('welcome.login.passwordHint')"
                        :error="form.errors.password"
                    >
                        <template
                            #default="{ id: controlId, describedBy, invalid }"
                        >
                            <Input
                                :id="controlId"
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                autofocus
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                                :class="inputClass"
                            />
                        </template>
                    </FormField>
                </div>

                <div class="flex flex-col gap-2.5">
                    <Button
                        type="submit"
                        size="lg"
                        class="h-11 w-full rounded-full"
                        :disabled="busy"
                    >
                        <CheckIcon
                            v-if="step === 'password'"
                            aria-hidden="true"
                        />
                        <MailIcon v-else aria-hidden="true" />
                        {{ submitLabel }}
                    </Button>
                    <Button
                        v-if="step === 'password'"
                        type="button"
                        variant="ghost"
                        size="lg"
                        class="h-11 w-full rounded-full"
                        :disabled="busy"
                        @click="useLinkInstead"
                    >
                        {{ t('welcome.login.useLink') }}
                    </Button>
                </div>
            </form>

            <p v-if="step !== 'password'" class="text-fg-soft text-[13px]">
                {{ t('welcome.login.note') }}
            </p>
        </template>

        <template v-else>
            <div
                role="status"
                class="bg-primary-soft text-primary-soft-fg flex flex-col gap-2 rounded-xl p-4"
            >
                <strong class="font-display text-[17px]">{{
                    t('welcome.login.sentTitle')
                }}</strong>
                <span>{{
                    t('welcome.login.sentText', {
                        email: form.email,
                        minutes: expiresInMinutes,
                    })
                }}</span>
            </div>
            <Button
                type="button"
                variant="ghost"
                size="lg"
                class="h-11 w-full rounded-full"
                @click="startOver"
            >
                {{ t('welcome.login.again') }}
            </Button>
        </template>
    </div>
</template>
