<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, LogOutIcon, MailIcon } from '@lucide/vue';
import { computed } from 'vue';
import {
    addEmail,
    updatePassword,
    updateProfile,
} from '@/actions/App/Http/Controllers/SettingsController';
import ChoiceGroup from '@/components/app/choice-group.vue';
import type { ChoiceOption } from '@/components/app/choice-group.vue';
import FormField from '@/components/app/form-field.vue';
import UserAvatar from '@/components/app/user-avatar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Locale } from '@/lib/i18n';
import { dashboard, logout } from '@/routes';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    settings: App.Data.SettingsData;
}>();

const { t, locale, setLocale } = useI18n();

const profileForm = useForm({ name: props.settings.name ?? '' });
const emailForm = useForm({ email: props.settings.pending_email ?? '' });
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

// A name-only joiner has no email yet and adds one through a confirmation link (F-01)
const hasEmail = computed(() => props.settings.email !== null);

const languageOptions = computed<ChoiceOption[]>(() =>
    props.settings.languages.map((language) => ({
        value: language,
        title: t(`settings.language.${language}`),
        hint: t(`settings.language.${language}Hint`),
    })),
);

const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) max-w-(--field-max-width) rounded-(--control-radius) px-3.5 text-[15px]';
const sectionClass =
    'grid gap-4 md:grid-cols-[260px_minmax(0,1fr)] md:items-start md:gap-8';
const cardClass = 'box flex min-w-0 flex-col gap-(--space-stack) p-5 sm:p-6';

function saveProfile(): void {
    profileForm.patch(updateProfile.url(), { preserveScroll: true });
}

function sendEmailConfirmation(): void {
    emailForm.patch(addEmail.url(), { preserveScroll: true });
}

function savePassword(): void {
    passwordForm.patch(updatePassword.url(), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => passwordForm.reset('password', 'password_confirmation'),
    });
}

function chooseLanguage(value: unknown): void {
    if (
        typeof value !== 'string' ||
        !props.settings.languages.includes(value as Locale)
    ) {
        return;
    }

    setLocale(value as Locale);
}

function logOut(): void {
    router.post(logout().url);
}
</script>

<template>
    <Head :title="t('settings.title')" />

    <div class="flex flex-col gap-10">
        <header class="flex flex-col gap-2.5">
            <Link
                :href="dashboard().url"
                class="text-fg-muted hover:text-fg mb-1.5 inline-flex w-fit items-center gap-1.5 rounded-sm text-sm font-semibold no-underline"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('nav.backToDashboard') }}
            </Link>
            <h1 class="text-3xl">{{ t('settings.title') }}</h1>
            <p class="text-fg-muted max-w-[60ch]">{{ t('settings.lede') }}</p>
        </header>

        <section
            :class="sectionClass"
            aria-labelledby="settings-account-title"
            data-feature="F-02"
        >
            <div class="flex min-w-0 flex-col gap-1.5">
                <h2 id="settings-account-title" class="text-xl">
                    {{ t('settings.account') }}
                </h2>
                <p class="text-fg-muted text-[15px]">
                    {{ t('settings.account.text') }}
                </p>
            </div>

            <div :class="cardClass">
                <div class="flex items-center gap-3.5 pb-1">
                    <UserAvatar
                        :name="settings.name"
                        :email="settings.email"
                        size="lg"
                    />
                    <span class="flex min-w-0 flex-col gap-px">
                        <span class="font-semibold">{{
                            settings.name || t('common.noName')
                        }}</span>
                        <span
                            class="text-fg-soft text-sm [overflow-wrap:anywhere]"
                            >{{
                                settings.email ?? t('settings.email.none')
                            }}</span
                        >
                    </span>
                </div>

                <form
                    class="flex flex-col gap-(--space-stack)"
                    novalidate
                    @submit.prevent="saveProfile"
                >
                    <FormField
                        :label="t('settings.name')"
                        control="settings-name"
                        required
                        :error="profileForm.errors.name"
                    >
                        <template #default="{ id, describedBy, invalid }">
                            <Input
                                :id="id"
                                v-model="profileForm.name"
                                autocomplete="name"
                                required
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                                :class="inputClass"
                            />
                        </template>
                    </FormField>
                    <div>
                        <Button
                            type="submit"
                            size="lg"
                            class="rounded-full px-4"
                            :disabled="profileForm.processing"
                        >
                            {{ t('settings.save') }}
                        </Button>
                    </div>
                </form>

                <FormField
                    v-if="hasEmail"
                    :label="t('settings.email')"
                    control="settings-email"
                    :hint="t('settings.emailHint')"
                >
                    <template #default="{ id, describedBy }">
                        <Input
                            :id="id"
                            :model-value="settings.email ?? ''"
                            type="email"
                            readonly
                            :aria-describedby="describedBy"
                            :class="[
                                inputClass,
                                'bg-surface-muted text-fg-muted',
                            ]"
                        />
                    </template>
                </FormField>

                <form
                    v-else
                    class="border-border flex flex-col gap-(--space-stack) border-t pt-5"
                    novalidate
                    data-feature="F-01"
                    @submit.prevent="sendEmailConfirmation"
                >
                    <div
                        class="bg-surface-muted flex gap-3 rounded-xl p-4 text-sm"
                        role="note"
                    >
                        <MailIcon
                            class="text-primary mt-0.5 size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            <b>{{ t('settings.email.addHint') }}</b>
                            <br />
                            {{ t('settings.email.addText') }}
                        </span>
                    </div>

                    <div
                        v-if="settings.pending_email"
                        class="bg-primary-soft text-primary-soft-fg flex flex-col gap-1 rounded-xl p-4 text-sm"
                        role="status"
                    >
                        <strong>{{ t('settings.email.pending') }}</strong>
                        <span class="[overflow-wrap:anywhere]">{{
                            t('settings.email.pendingText', {
                                email: settings.pending_email,
                            })
                        }}</span>
                    </div>

                    <FormField
                        :label="t('settings.email')"
                        control="settings-add-email"
                        required
                        :hint="t('settings.email.fieldHint')"
                        :error="emailForm.errors.email"
                    >
                        <template #default="{ id, describedBy, invalid }">
                            <Input
                                :id="id"
                                v-model.trim="emailForm.email"
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
                    <div>
                        <Button
                            type="submit"
                            size="lg"
                            class="rounded-full px-4"
                            :disabled="emailForm.processing"
                        >
                            <MailIcon aria-hidden="true" />
                            {{ t('settings.email.send') }}
                        </Button>
                    </div>
                </form>
            </div>
        </section>

        <section
            :class="sectionClass"
            aria-labelledby="settings-password-title"
            data-feature="F-02"
        >
            <div class="flex min-w-0 flex-col gap-1.5">
                <h2 id="settings-password-title" class="text-xl">
                    {{ t('settings.password') }}
                </h2>
                <p class="text-fg-muted text-[15px]">
                    {{
                        settings.has_password
                            ? t('settings.password.textSet')
                            : t('settings.password.textNone')
                    }}
                </p>
            </div>

            <form :class="cardClass" novalidate @submit.prevent="savePassword">
                <FormField
                    v-if="settings.has_password"
                    :label="t('settings.password.current')"
                    control="settings-password-current"
                    required
                    :error="passwordForm.errors.current_password"
                >
                    <template #default="{ id, describedBy, invalid }">
                        <Input
                            :id="id"
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid || undefined"
                            :class="inputClass"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="t('settings.password.new')"
                    control="settings-password-new"
                    required
                    :hint="
                        t('settings.password.hint', {
                            n: settings.password_min_length,
                        })
                    "
                    :error="passwordForm.errors.password"
                >
                    <template #default="{ id, describedBy, invalid }">
                        <Input
                            :id="id"
                            v-model="passwordForm.password"
                            type="password"
                            autocomplete="new-password"
                            required
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid || undefined"
                            :class="inputClass"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="t('settings.password.repeat')"
                    control="settings-password-repeat"
                    required
                >
                    <template #default="{ id, describedBy }">
                        <Input
                            :id="id"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            :aria-describedby="describedBy"
                            :class="inputClass"
                        />
                    </template>
                </FormField>
                <div>
                    <Button
                        type="submit"
                        variant="secondary"
                        size="lg"
                        class="rounded-full px-4"
                        :disabled="passwordForm.processing"
                    >
                        {{
                            settings.has_password
                                ? t('settings.password.change')
                                : t('settings.password.set')
                        }}
                    </Button>
                </div>
            </form>
        </section>

        <section
            :class="sectionClass"
            aria-labelledby="settings-language-title"
            data-feature="F-24"
        >
            <div class="flex min-w-0 flex-col gap-1.5">
                <h2 id="settings-language-title" class="text-xl">
                    {{ t('settings.language') }}
                </h2>
                <p class="text-fg-muted text-[15px]">
                    {{ t('settings.language.text') }}
                </p>
            </div>

            <div :class="cardClass">
                <FormField
                    :label="t('settings.language.label')"
                    control="settings-language"
                    group
                    :hint="t('settings.language.note')"
                >
                    <ChoiceGroup
                        :model-value="locale"
                        name="settings-language"
                        :options="languageOptions"
                        @update:model-value="chooseLanguage"
                    />
                </FormField>
            </div>
        </section>

        <section
            :class="sectionClass"
            aria-labelledby="settings-logout-title"
            data-feature="F-02"
        >
            <div class="flex min-w-0 flex-col gap-1.5">
                <h2 id="settings-logout-title" class="text-xl">
                    {{ t('settings.logout') }}
                </h2>
                <p class="text-fg-muted text-[15px]">
                    {{ t('settings.logout.text') }}
                </p>
            </div>

            <div :class="cardClass">
                <div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="lg"
                        class="rounded-full px-4"
                        @click="logOut"
                    >
                        <LogOutIcon aria-hidden="true" />
                        {{ t('nav.logout') }}
                    </Button>
                </div>
            </div>
        </section>
    </div>
</template>
