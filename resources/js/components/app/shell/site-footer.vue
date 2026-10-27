<script setup lang="ts">
import { useI18n } from '@/composables/use-i18n';
import LanguageSwitch from './language-switch.vue';

const { t } = useI18n();

const year = new Date().getFullYear();
</script>

<template>
    <footer class="border-border mt-auto border-t">
        <div
            class="content-width text-fg-soft flex flex-wrap items-center justify-between gap-4 pt-6 pb-10 text-sm"
        >
            <span>&copy; {{ year }} {{ t('welcome.footer.copy') }}</span>
            <!-- The prototype's privacy and legal notice links wait for backlog item LEGAL; no routes exist yet -->
            <slot />
            <LanguageSwitch />
        </div>
    </footer>
</template>
