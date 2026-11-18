<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';

// Open to guests and accounts: accounts keep their app shell
defineOptions({
    layout: (props: { auth: { user: unknown } }) =>
        props.auth.user ? AppLayout : WebsiteLayout,
});

const props = defineProps<{
    operator: App.Data.OperatorData;
}>();

const { t } = useI18n();

const sectionClass =
    'grid gap-4 min-[720px]:grid-cols-[260px_minmax(0,1fr)] min-[720px]:items-start min-[720px]:gap-8';
const cardClass =
    'box flex min-w-0 flex-col gap-(--space-stack) p-(--card-padding) shadow-(--box-shadow)';

const hasOperator = computed(
    () =>
        !!(
            props.operator.name ||
            props.operator.address ||
            props.operator.email
        ),
);
</script>

<template>
    <Head :title="t('legal.notice.title')" />

    <div class="flex flex-col gap-10 min-[720px]:gap-14" data-feature="F-33">
        <header class="flex flex-col gap-2.5">
            <h1 class="text-h1">{{ t('legal.notice.title') }}</h1>
        </header>

        <section
            v-if="hasOperator"
            :class="sectionClass"
            aria-labelledby="legal-operator-title"
        >
            <h2 id="legal-operator-title" class="text-h2">
                {{ t('legal.notice.operator') }}
            </h2>
            <div :class="cardClass">
                <p v-if="operator.name" class="max-w-[60ch]">
                    {{ operator.name }}
                </p>
                <p
                    v-if="operator.address"
                    class="max-w-[60ch] whitespace-pre-line"
                >
                    {{ operator.address }}
                </p>
                <p v-if="operator.email" class="max-w-[60ch]">
                    <a :href="`mailto:${operator.email}`" class="underline">{{
                        operator.email
                    }}</a>
                </p>
            </div>
        </section>
    </div>
</template>
