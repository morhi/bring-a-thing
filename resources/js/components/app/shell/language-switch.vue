<script setup lang="ts">
import { useI18n } from '@/composables/use-i18n';
import type { Locale } from '@/lib/i18n';

const { t, locale, setLocale } = useI18n();

// Language names stay in their own language, so everyone finds theirs
const languages: { code: Locale; label: string }[] = [
    { code: 'en', label: 'English' },
    { code: 'de', label: 'Deutsch' },
];
</script>

<template>
    <div
        class="bg-surface-sunken inline-flex gap-0.5 rounded-full p-[3px]"
        role="group"
        :aria-label="t('welcome.language')"
        data-feature="F-24"
    >
        <button
            v-for="language in languages"
            :key="language.code"
            type="button"
            :lang="language.code"
            :aria-pressed="locale === language.code"
            class="text-fg-muted hover:text-fg aria-pressed:bg-surface-raised aria-pressed:text-fg focus-visible:outline-focus-ring min-h-8 rounded-full px-2.5 text-[13px] font-semibold aria-pressed:shadow-sm"
            @click="setLocale(language.code)"
        >
            {{ language.label }}
        </button>
    </div>
</template>
