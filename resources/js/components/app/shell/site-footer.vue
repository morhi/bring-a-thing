<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/composables/use-i18n';
import { legalNotice, privacy } from '@/routes';
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
            <nav class="flex gap-4">
                <Link :href="privacy()" class="hover:underline">{{
                    t('welcome.footer.privacy')
                }}</Link>
                <Link :href="legalNotice()" class="hover:underline">{{
                    t('welcome.footer.legal')
                }}</Link>
            </nav>
            <slot />
            <LanguageSwitch />
        </div>
    </footer>
</template>
