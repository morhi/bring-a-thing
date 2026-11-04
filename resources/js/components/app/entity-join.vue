<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { CalendarDays, Check, Mail, TriangleAlert, User } from '@lucide/vue';
import { computed, ref } from 'vue';
import FormField from '@/components/app/form-field.vue';
import { formatDate } from '@/components/app/poll/dates';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';
import { dashboard, welcome } from '@/routes';

/**
 * Join page body behind an event or poll invite link (F-26, F-27).
 *
 * `join` is null for an unknown, revoked or replaced link ("Not found").
 * A guest joins with a name only, or with an email and a login link.
 */
const props = defineProps<{
    join: App.Data.EntityJoinData | null;
    // Needed for the "Not found" state, where `join` is null
    kind: 'event' | 'poll';
    // Posts the form; the kind decides between the event and the poll route
    submitUrl: (token: string) => string;
}>();

const { t, locale } = useI18n();
const page = usePage();
const userName = computed(() => page.props.auth.user?.name ?? null);
const loggedIn = computed(() => page.props.auth.user !== null);

type Mode = 'email' | 'name';
const mode = ref<Mode>('email');
const form = useForm({ name: '', email: '' });
// The email flow ends on this page: the login link joins on use
const sentTo = ref<string | null>(null);
// A new or nameless account also needs a name; the backend says when
const nameRequested = ref(false);

const kindLabel = computed(() => props.kind);

const whenText = computed(() => {
    if (!props.join?.date) {
        return null;
    }

    const day = formatDate(props.join.date, locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return props.join.starts_at ? `${day}, ${props.join.starts_at}` : day;
});

function submit(): void {
    if (!props.join) {
        return;
    }

    if (mode.value === 'name') {
        form.email = '';
    }

    form.post(props.submitUrl(props.join.token), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (props.join?.is_authenticated || mode.value === 'name') {
                return;
            }

            sentTo.value = form.email;
        },
        onError: (errors) => {
            if (errors.name && mode.value === 'email') {
                nameRequested.value = true;
            }
        },
    });
}

function setMode(next: Mode): void {
    mode.value = next;
    form.clearErrors();
}

function useOtherEmail(): void {
    sentTo.value = null;
    nameRequested.value = false;
    form.reset();
}

const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) rounded-(--control-radius) px-3.5 text-[15px]';
const buttonClass = 'h-(--control-height) w-full px-4';
</script>

<template>
    <Head
        :title="
            join
                ? t('entityJoin.title', { title: join.title })
                : t('entityJoin.notFound')
        "
    />

    <div
        v-if="!join"
        class="flex max-w-[60ch] flex-col gap-4 py-2 md:py-6"
        :data-feature="kindLabel === 'poll' ? 'F-27' : 'F-26'"
    >
        <h1 class="text-h1">{{ t('entityJoin.notFound') }}</h1>
        <p class="text-fg-muted text-lg">
            {{ t(`entityJoin.notFoundText.${kindLabel}`) }}
        </p>
        <Button
            as-child
            variant="outline"
            size="lg"
            class="h-(--control-height) w-fit px-4"
        >
            <Link :href="loggedIn ? dashboard().url : welcome().url">{{
                loggedIn ? t('nav.backToDashboard') : t('join.toStart')
            }}</Link>
        </Button>
    </div>

    <div
        v-else
        class="grid items-start gap-8 py-2 md:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] md:gap-14 md:py-6"
        :data-feature="join.kind === 'poll' ? 'F-27' : 'F-26'"
    >
        <div class="flex min-w-0 flex-col items-start gap-3.5">
            <h1 class="text-h1 [overflow-wrap:anywhere]">{{ join.title }}</h1>
            <p class="text-fg-muted max-w-[52ch] text-lg">
                {{
                    t(`entityJoin.lede.${kindLabel}`, {
                        name: join.organizer_name ?? t('common.noName'),
                    })
                }}
            </p>
            <p
                v-if="whenText"
                class="text-fg-muted inline-flex items-center gap-2 text-[15px]"
            >
                <CalendarDays class="size-4" aria-hidden="true" />
                {{ whenText }}
            </p>
        </div>

        <div
            class="box-float flex min-w-0 flex-col gap-(--space-stack) p-6 sm:p-7"
        >
            <form
                v-if="join.is_authenticated"
                novalidate
                class="flex flex-col gap-(--space-stack)"
                @submit.prevent="submit"
            >
                <p class="text-fg-muted">
                    {{
                        userName
                            ? t('join.joinAs', { name: userName })
                            : t('join.joinOneClick')
                    }}
                </p>
                <Button
                    type="submit"
                    size="lg"
                    :class="buttonClass"
                    :disabled="form.processing"
                >
                    <Check aria-hidden="true" /> {{ t('join.button') }}
                </Button>
            </form>

            <div v-else-if="sentTo" class="flex flex-col gap-(--space-stack)">
                <div
                    class="bg-primary-soft text-primary-soft-fg flex flex-col gap-2 rounded-(--radius-md) p-4"
                    role="status"
                >
                    <strong class="font-display text-[17px]">{{
                        t('welcome.login.sentTitle')
                    }}</strong>
                    <span>{{
                        t('welcome.login.sentText', {
                            email: sentTo,
                            minutes: join.link_expires_in_minutes,
                        })
                    }}</span>
                    <span>{{ t(`entityJoin.sentNote.${kindLabel}`) }}</span>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="lg"
                    :class="buttonClass"
                    @click="useOtherEmail"
                >
                    {{ t('welcome.login.again') }}
                </Button>
            </div>

            <template v-else>
                <div
                    class="grid grid-cols-2 gap-2"
                    role="group"
                    :aria-label="t('entityJoin.modeLabel')"
                >
                    <Button
                        type="button"
                        size="lg"
                        :variant="mode === 'email' ? 'default' : 'outline'"
                        :aria-pressed="mode === 'email'"
                        class="h-(--control-height) px-4"
                        @click="setMode('email')"
                    >
                        <Mail aria-hidden="true" />
                        {{ t('entityJoin.withEmail') }}
                    </Button>
                    <Button
                        type="button"
                        size="lg"
                        :variant="mode === 'name' ? 'default' : 'outline'"
                        :aria-pressed="mode === 'name'"
                        class="h-(--control-height) px-4"
                        @click="setMode('name')"
                    >
                        <User aria-hidden="true" />
                        {{ t('entityJoin.withName') }}
                    </Button>
                </div>

                <form
                    novalidate
                    class="flex flex-col gap-(--space-stack)"
                    @submit.prevent="submit"
                >
                    <FormField
                        v-if="mode === 'email'"
                        :label="t('welcome.login.email')"
                        control="join-email"
                        required
                        :error="form.errors.email"
                        :hint="t('welcome.login.note')"
                    >
                        <template #default="{ describedBy, invalid }">
                            <Input
                                id="join-email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                required
                                placeholder="name@example.com"
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                                :class="inputClass"
                            />
                        </template>
                    </FormField>
                    <FormField
                        v-if="mode === 'name' || nameRequested"
                        :label="t('welcome.login.name')"
                        control="join-name"
                        required
                        :error="form.errors.name"
                        :hint="t('join.nameHint')"
                    >
                        <template #default="{ describedBy, invalid }">
                            <Input
                                id="join-name"
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                required
                                :aria-describedby="describedBy"
                                :aria-invalid="invalid || undefined"
                                :class="inputClass"
                            />
                        </template>
                    </FormField>
                    <div
                        v-if="mode === 'name'"
                        class="bg-warning-soft text-warning flex items-start gap-2.5 rounded-(--radius-md) p-4 text-sm"
                        role="note"
                    >
                        <TriangleAlert
                            class="mt-0.5 size-4 flex-none"
                            aria-hidden="true"
                        />
                        <span>{{ t('entityJoin.nameOnlyWarning') }}</span>
                    </div>
                    <Button
                        type="submit"
                        size="lg"
                        :class="buttonClass"
                        :disabled="form.processing"
                    >
                        <Mail v-if="mode === 'email'" aria-hidden="true" />
                        <Check v-else aria-hidden="true" />
                        {{
                            mode === 'email'
                                ? t('welcome.login.sendLink')
                                : t('join.button')
                        }}
                    </Button>
                </form>
            </template>
        </div>
    </div>
</template>
