<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';
import { dashboard, welcome } from '@/routes';

defineOptions({ layout: WebsiteLayout });

/**
 * Note for a regenerated, disabled or unknown share link (F-19, section 8).
 */
const { t } = useI18n();
const page = usePage();
const loggedIn = computed(() => page.props.auth.user !== null);
</script>

<template>
    <Head :title="t('list.share.invalidTitle')" />

    <div
        class="flex max-w-[60ch] flex-col gap-4 py-2 md:py-6"
        data-feature="F-19"
    >
        <h1 class="text-h1">{{ t('list.share.invalidTitle') }}</h1>
        <p class="text-fg-muted text-[17px]">
            {{ t('list.share.invalidText') }}
        </p>
        <Button
            as-child
            variant="secondary"
            size="lg"
            class="h-(--control-height) w-fit px-4"
        >
            <Link :href="loggedIn ? dashboard().url : welcome().url">{{
                loggedIn ? t('nav.backToDashboard') : t('join.toStart')
            }}</Link>
        </Button>
    </div>
</template>
