<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Check, Mail } from '@lucide/vue';
import { computed, ref } from 'vue';
import { dashboard, welcome } from '@/routes';
import { store as joinGroup } from '@/actions/App/Http/Controllers/GroupJoinController';
import { edit as editSettings } from '@/actions/App/Http/Controllers/SettingsController';
import AvatarStack from '@/components/app/avatar-stack.vue';
import FormField from '@/components/app/form-field.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';

defineOptions({ layout: WebsiteLayout });

/**
 * Join page behind a group's invite link (F-04, F-01).
 *
 * `join` is null for an unknown, revoked or replaced link ("Not found").
 */
const props = defineProps<{ join: App.Data.GroupJoinData | null }>();

const { t } = useI18n();
const page = usePage();
const userName = computed(() => page.props.auth.user?.name ?? null);
const loggedIn = computed(() => page.props.auth.user !== null);

const form = useForm({ name: '', email: '' });
// The closed flow ends on this page: the login link joins on use
const sentTo = ref<string | null>(null);
// A new or nameless account on a closed link also needs a name; the backend says when
const nameRequested = ref(false);
const askName = computed(() => !props.join?.locked || nameRequested.value);

function submit(): void {
    if (!props.join) {
        return;
    }

    form.post(joinGroup(props.join.token).url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (!props.join?.locked || props.join.is_authenticated) {
                return;
            }

            sentTo.value = form.email;
        },
        onError: (errors) => {
            if (errors.name) {
                nameRequested.value = true;
            }
        },
    });
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
    <Head :title="join ? t('join.title', { group: join.group_name }) : t('group.notFound')" />

    <div v-if="!join" class="flex max-w-[60ch] flex-col gap-4 py-2 md:py-6" data-feature="F-04">
        <h1 class="text-3xl">{{ t('group.notFound') }}</h1>
        <p class="text-fg-muted text-lg">{{ t('join.notFoundText') }}</p>
        <Button as-child variant="secondary" size="lg" class="h-(--control-height) w-fit px-4">
            <Link :href="loggedIn ? dashboard().url : welcome().url">{{
                loggedIn ? t('nav.backToDashboard') : t('join.toStart')
            }}</Link>
        </Button>
    </div>

    <div
        v-else
        class="grid items-start gap-8 py-2 md:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] md:gap-14 md:py-6"
    >
        <div class="flex min-w-0 flex-col items-start gap-3.5" data-feature="F-04">
            <h1 class="text-3xl [overflow-wrap:anywhere] md:text-4xl">
                {{ t('join.title', { group: join.group_name }) }}
            </h1>
            <p class="text-fg-muted max-w-[52ch] text-lg">
                {{ t(join.locked ? 'join.lede.closed' : 'join.lede.open', { group: join.group_name }) }}
            </p>
            <div class="text-fg-muted flex items-center gap-2.5 text-[15px]">
                <AvatarStack :people="join.members" :max="5" />
                <span>{{ join.member_count }} {{ t('word.members') }}</span>
            </div>
        </div>

        <div class="box-float flex min-w-0 flex-col gap-(--space-stack) p-6 sm:p-7" data-feature="F-01">
            <template v-if="join.is_authenticated && join.needs_email">
                <p class="text-fg-muted">
                    {{ join.has_email ? t('join.loginFirst') : t('join.addEmailFirst') }}
                </p>
                <Button as-child size="lg" :class="buttonClass">
                    <Link :href="editSettings().url">{{ t('join.toSettings') }}</Link>
                </Button>
            </template>

            <form v-else-if="join.is_authenticated" novalidate class="flex flex-col gap-(--space-stack)" @submit.prevent="submit">
                <p class="text-fg-muted">
                    {{ userName ? t('join.joinAs', { name: userName }) : t('join.joinOneClick') }}
                </p>
                <p v-if="form.errors.email" class="text-danger text-sm font-medium" role="alert">
                    {{ form.errors.email }}
                </p>
                <Button type="submit" size="lg" :class="buttonClass" :disabled="form.processing">
                    <Check aria-hidden="true" /> {{ t('join.button') }}
                </Button>
            </form>

            <div v-else-if="sentTo" class="flex flex-col gap-(--space-stack)">
                <div class="bg-primary-soft text-primary-soft-fg flex flex-col gap-2 rounded-(--radius-md) p-4" role="status">
                    <strong class="font-display text-[17px]">{{ t('welcome.login.sentTitle') }}</strong>
                    <span>{{ t('welcome.login.sentText', { email: sentTo }) }}</span>
                    <span>{{ t('join.sentNote', { group: join.group_name }) }}</span>
                </div>
                <Button type="button" variant="ghost" size="lg" :class="buttonClass" @click="useOtherEmail">
                    {{ t('welcome.login.again') }}
                </Button>
            </div>

            <form v-else novalidate class="flex flex-col gap-(--space-stack)" @submit.prevent="submit">
                <FormField
                    v-if="join.locked"
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
                    v-if="askName"
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
                <Button type="submit" size="lg" :class="buttonClass" :disabled="form.processing">
                    <template v-if="join.locked">
                        <Mail aria-hidden="true" /> {{ t('welcome.login.sendLink') }}
                    </template>
                    <template v-else>
                        <Check aria-hidden="true" /> {{ t('join.button') }}
                    </template>
                </Button>
            </form>
        </div>
    </div>
</template>
