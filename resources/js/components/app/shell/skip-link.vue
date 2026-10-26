<script setup lang="ts">
import { useI18n } from '@/composables/use-i18n';

const { t } = useI18n();
</script>

<template>
    <a
        href="#main"
        class="bg-primary text-primary-fg hover:text-primary-fg sr-only z-80 rounded-lg px-4 py-2.5 font-semibold no-underline shadow-md focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
    >
        {{ t('shell.skip') }}
    </a>
</template>
