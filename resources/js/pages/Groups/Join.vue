<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { dashboard, welcome } from '@/routes';
import { store as joinGroup } from '@/actions/App/Http/Controllers/GroupJoinController';
import { edit as editSettings } from '@/actions/App/Http/Controllers/SettingsController';
import AvatarStack from '@/components/app/avatar-stack.vue';
import LoginForm from '@/components/app/login-form.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';

defineOptions({ layout: WebsiteLayout });

/**
 * Join page behind a group's invite link (F-04, F-01).
 *
 * `join` is null for an unknown, revoked or replaced link ("Not found").
 * A guest logs in through the regular login form, which joins afterwards.
 */
const props = defineProps<{ join: App.Data.GroupJoinData | null }>();

const { t, tc } = useI18n();
const page = usePage();
const userName = computed(() => page.props.auth.user?.name ?? null);
const loggedIn = computed(() => page.props.auth.user !== null);

// The one-click join reports a missing verified email under `email`
const form = useForm<{ email?: string }>({});

function submit(): void {
    if (!props.join) {
        return;
    }

    form.post(joinGroup(props.join.token).url, {
        preserveScroll: true,
        preserveState: true,
    });
}

const buttonClass = 'h-(--control-height) w-full px-4';
</script>

<template>
    <Head
        :title="
            join
                ? t('join.title', { group: join.group_name })
                : t('group.notFound')
        "
    />

    <div
        v-if="!join"
        class="flex max-w-[60ch] flex-col gap-4 py-2 md:py-6"
        data-feature="F-04"
    >
        <h1 class="text-h1">{{ t('group.notFound') }}</h1>
        <p class="text-fg-muted text-lg">{{ t('join.notFoundText') }}</p>
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
    >
        <div
            class="flex min-w-0 flex-col items-start gap-3.5"
            data-feature="F-04"
        >
            <h1 class="text-h1 [overflow-wrap:anywhere]">
                {{ t('join.title', { group: join.group_name }) }}
            </h1>
            <p class="text-fg-muted max-w-[52ch] text-lg">
                {{ t('join.lede', { group: join.group_name }) }}
            </p>
            <div class="text-fg-muted flex items-center gap-2.5 text-[15px]">
                <AvatarStack :people="join.members" :max="5" />
                <span
                    >{{ join.member_count }}
                    {{ tc('word.memberCount', join.member_count) }}</span
                >
            </div>
        </div>

        <div
            class="box-float flex min-w-0 flex-col gap-(--space-stack) p-6 sm:p-7"
            data-feature="F-01"
        >
            <template v-if="join.is_authenticated && join.needs_email">
                <p class="text-fg-muted">
                    {{
                        join.has_email
                            ? t('join.loginFirst')
                            : t('join.addEmailFirst')
                    }}
                </p>
                <Button as-child size="lg" :class="buttonClass">
                    <Link :href="editSettings().url">{{
                        t('join.toSettings')
                    }}</Link>
                </Button>
            </template>

            <form
                v-else-if="join.is_authenticated"
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
                <p
                    v-if="form.errors.email"
                    class="text-danger text-sm font-medium"
                    role="alert"
                >
                    {{ form.errors.email }}
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

            <LoginForm
                v-else
                :expires-in-minutes="join.link_expires_in_minutes"
                :pending-join="{ kind: 'group', token: join.token }"
                id-prefix="join-login"
            />
        </div>
    </div>
</template>
