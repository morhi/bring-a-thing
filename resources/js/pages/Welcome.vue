<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRightIcon } from '@lucide/vue';
import { computed } from 'vue';
import LoginForm from '@/components/app/login-form.vue';
import Mark from '@/components/app/mark.vue';
import type { MarkAnswer } from '@/components/app/mark.vue';
import UserAvatar from '@/components/app/user-avatar.vue';
import LanguageSwitch from '@/components/app/shell/language-switch.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';
import { dashboard } from '@/routes';

defineOptions({ layout: WebsiteLayout });

const props = defineProps<{
    welcome: App.Data.WelcomeData;
}>();

const page = usePage();
const { t, locale } = useI18n();

const user = computed(() => page.props.auth.user);

// The tilted tag sits inside the sentence, at a different place per language
const titleParts = computed(() => {
    const [before, after = ''] = t('welcome.title').split(':tag');

    return { before, after };
});

// The illustration is a fixed example, so its people and dates never change
const people = ['Lena Frei', 'Tobi Keller', 'Mia Rossi', 'Jonas Brand'];

const pollRows: { name: string; marks: MarkAnswer[] }[] = [
    { name: people[0], marks: ['yes', 'yes', 'maybe'] },
    { name: people[1], marks: ['yes', 'maybe', 'yes'] },
    { name: people[2], marks: ['no', 'yes', 'yes'] },
    { name: people[3], marks: ['yes', 'no', 'none'] },
];

function shortDate(iso: string): string {
    const [year, month, day] = iso.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    return locale.value === 'de'
        ? `${String(day).padStart(2, '0')}.${String(month).padStart(2, '0')}.`
        : date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

const pollDates = ['2026-11-20', '2026-11-21', '2026-11-27'];
const bestDate = '2026-11-21';

const listItems = computed(() => [
    { name: t('welcome.mini.item1'), who: 'Lena', answer: 'yes' as MarkAnswer },
    { name: t('welcome.mini.item2'), who: '4 + 2', answer: 'yes' as MarkAnswer },
    { name: t('welcome.mini.item3'), who: '?', answer: 'none' as MarkAnswer },
]);

const steps = [1, 2, 3];

const miniClass = 'border-border bg-surface-raised flex min-w-0 flex-col gap-2 rounded-xl border p-3 text-xs shadow-md';
</script>

<template>
    <Head :title="t('welcome.login.title')" />

    <div class="flex flex-col">
        <section class="grid items-start gap-10 pt-2 pb-2 min-[900px]:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] min-[900px]:gap-14 min-[900px]:pt-10">
            <div class="flex min-w-0 flex-col gap-5">
                <h1 class="max-w-[14ch] text-[clamp(36px,4.4vw+14px,64px)] leading-[1.02] font-[720] tracking-[-0.03em]">
                    {{ titleParts.before
                    }}<span class="bg-primary font-tag text-primary-fg inline-block rotate-(--tag-tilt) rounded-lg font-bold leading-[1.2] shadow-sm mx-[0.04em] px-2.5 pt-0.5 pb-[3px] align-[0.08em] text-[0.78em]">{{ t('welcome.tag') }}</span
                    >{{ titleParts.after }}
                </h1>
                <p class="text-fg-muted max-w-[50ch] text-lg">{{ t('welcome.lede') }}</p>

                <div class="relative mt-2 grid gap-3.5 min-[560px]:grid-cols-3 min-[560px]:items-start" aria-hidden="true">
                    <div class="flex min-w-0 flex-col gap-2.5">
                        <span class="text-fg-muted flex items-center gap-2 text-[13px] font-semibold">
                            <b class="font-display text-primary text-[15px] tabular-nums">1</b>
                            {{ t('welcome.chain.1') }}
                        </span>
                        <div :class="miniClass">
                            <div class="font-display text-sm leading-tight font-[640]">{{ t('welcome.mini.poll') }}</div>
                            <div class="text-fg-muted grid grid-cols-[auto_repeat(3,1fr)] items-center gap-x-1.5 gap-y-1">
                                <span />
                                <span
                                    v-for="date in pollDates"
                                    :key="date"
                                    :class="['text-center text-[11px] font-semibold whitespace-nowrap', date === bestDate ? 'text-primary' : 'text-fg-soft']"
                                    >{{ shortDate(date) }}</span
                                >
                                <template v-for="row in pollRows" :key="row.name">
                                    <UserAvatar :name="row.name" size="sm" />
                                    <Mark v-for="(mark, index) in row.marks" :key="index" :answer="mark" class="mx-auto size-4 [&_svg]:size-2.5" />
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="flex min-w-0 flex-col gap-2.5 min-[560px]:mt-7">
                        <span class="text-fg-muted flex items-center gap-2 text-[13px] font-semibold">
                            <b class="font-display text-primary text-[15px] tabular-nums">2</b>
                            {{ t('welcome.chain.2') }}
                        </span>
                        <div :class="miniClass">
                            <div class="font-display text-sm leading-tight font-[640]">{{ t('welcome.mini.event') }}</div>
                            <div class="flex items-baseline gap-2">
                                <span class="bg-primary font-tag text-primary-fg inline-block rotate-(--tag-tilt) rounded-lg font-bold leading-[1.2] shadow-sm px-[7px] pt-px pb-0.5 text-xs">{{ shortDate('2026-11-14') }}</span>
                                <span>18:00</span>
                            </div>
                            <div class="text-fg-muted flex items-center gap-1.5">
                                <span class="flex -space-x-1.5">
                                    <UserAvatar v-for="name in people" :key="name" :name="name" size="sm" />
                                </span>
                                <span>{{ t('welcome.mini.accepted') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex min-w-0 flex-col gap-2.5 min-[560px]:mt-14">
                        <span class="text-fg-muted flex items-center gap-2 text-[13px] font-semibold">
                            <b class="font-display text-primary text-[15px] tabular-nums">3</b>
                            {{ t('welcome.chain.3') }}
                        </span>
                        <div :class="miniClass">
                            <div class="font-display text-sm leading-tight font-[640]">{{ t('welcome.mini.list') }}</div>
                            <div class="flex flex-col gap-1">
                                <div v-for="item in listItems" :key="item.name" class="bg-surface-muted flex items-center gap-2 rounded-[7px] px-2 py-1">
                                    <Mark :answer="item.answer" class="size-4 [&_svg]:size-2.5" />
                                    <span class="text-fg min-w-0 flex-1 truncate font-medium">{{ item.name }}</span>
                                    <span class="text-fg-soft">{{ item.who }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box relative flex flex-col gap-5 overflow-hidden p-7 shadow-md md:p-8">
                <span class="from-primary to-(--accent) absolute inset-x-0 top-0 h-1.5 bg-linear-to-r" aria-hidden="true" />

                <template v-if="user">
                    <h2 class="text-2xl">{{ t('welcome.loggedIn.title') }}</h2>
                    <p class="text-fg-muted">{{ t('welcome.loggedIn.text', { name: user.name || user.email || t('common.noName') }) }}</p>
                    <Button as-child size="lg" class="h-11 w-full rounded-full">
                        <Link :href="dashboard().url" class="text-primary-fg hover:text-primary-fg no-underline">
                            {{ t('welcome.loggedIn.open') }}
                            <ArrowRightIcon aria-hidden="true" />
                        </Link>
                    </Button>
                </template>

                <template v-else>
                    <h2 class="text-2xl">{{ t('welcome.login.title') }}</h2>
                    <LoginForm :expires-in-minutes="props.welcome.link_expires_in_minutes" :link-error="props.welcome.login_link_error" />
                </template>
            </div>
        </section>

        <section class="border-border mt-10 grid gap-5 border-t py-10 md:grid-cols-3 md:gap-8" :aria-label="t('welcome.steps.label')">
            <div v-for="step in steps" :key="step" class="flex flex-col gap-2">
                <span class="font-display text-primary text-[34px] leading-none font-[760] tabular-nums" aria-hidden="true">{{ step }}</span>
                <h3 class="text-[19px]">{{ t(`welcome.steps.${step}.title`) }}</h3>
                <p class="text-fg-muted max-w-[38ch]">{{ t(`welcome.steps.${step}.text`) }}</p>
            </div>
        </section>

        <div class="border-border flex flex-wrap items-center justify-end gap-4 border-t pt-6">
            <LanguageSwitch />
        </div>
    </div>
</template>
