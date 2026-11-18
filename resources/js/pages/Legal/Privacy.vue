<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';

// Open to guests and accounts: accounts keep their app shell
defineOptions({
    layout: (props: { auth: { user: unknown } }) =>
        props.auth.user ? AppLayout : WebsiteLayout,
});

defineProps<{
    operator: App.Data.OperatorData;
}>();

const { t } = useI18n();

const sections = [
    'controller',
    'data',
    'purpose',
    'recipients',
    'cookies',
    'retention',
    'rights',
];

const sectionClass =
    'grid gap-4 min-[720px]:grid-cols-[260px_minmax(0,1fr)] min-[720px]:items-start min-[720px]:gap-8';
const cardClass =
    'box flex min-w-0 flex-col gap-(--space-stack) p-(--card-padding) shadow-(--box-shadow)';

// The email sits inside the sentence, at a different place per language
function parts(key: string): [string, string] {
    const [before, after = ''] = t(key).split(':email');

    return [before, after];
}
</script>

<template>
    <Head :title="t('legal.privacy.title')" />

    <div class="flex flex-col gap-10 min-[720px]:gap-14" data-feature="F-33">
        <header class="flex flex-col gap-2.5">
            <h1 class="text-h1">{{ t('legal.privacy.title') }}</h1>
        </header>

        <section
            v-for="section in sections"
            :key="section"
            :class="sectionClass"
            :aria-labelledby="`legal-${section}-title`"
        >
            <h2 :id="`legal-${section}-title`" class="text-h2">
                {{ t(`legal.privacy.${section}.title`) }}
            </h2>
            <div :class="cardClass">
                <p class="max-w-[60ch]">
                    {{ t(`legal.privacy.${section}.text`) }}
                </p>

                <p
                    v-if="section === 'controller' && operator.email"
                    class="max-w-[60ch]"
                >
                    {{ parts('legal.privacy.controller.contact')[0]
                    }}<a :href="`mailto:${operator.email}`" class="underline">{{
                        operator.email
                    }}</a
                    >{{ parts('legal.privacy.controller.contact')[1] }}
                </p>

                <template v-if="section === 'rights'">
                    <p v-if="operator.email" class="max-w-[60ch]">
                        {{ parts('legal.privacy.rights.contact')[0]
                        }}<a
                            :href="`mailto:${operator.email}`"
                            class="underline"
                            >{{ operator.email }}</a
                        >{{ parts('legal.privacy.rights.contact')[1] }}
                    </p>
                    <p v-else class="max-w-[60ch]">
                        {{ t('legal.privacy.rights.contactNone') }}
                    </p>
                    <p class="max-w-[60ch]">
                        {{ t('legal.privacy.rights.complaint') }}
                    </p>
                </template>
            </div>
        </section>
    </div>
</template>
