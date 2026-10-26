<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/composables/use-i18n';
import { isCurrent } from './nav-items';
import type { NavItem } from './nav-items';

defineProps<{
    items: NavItem[];
    currentUrl: string;
}>();

const { t } = useI18n();
</script>

<template>
    <nav :aria-label="t('shell.nav')">
        <ul class="flex items-center gap-1">
            <li v-for="item in items" :key="item.key">
                <Link
                    :href="item.href"
                    :aria-current="
                        isCurrent(item.href, currentUrl) ? 'page' : undefined
                    "
                    class="text-fg-muted hover:bg-surface-sunken hover:text-fg aria-[current=page]:bg-primary-soft aria-[current=page]:text-primary-soft-fg inline-flex min-h-9 items-center rounded-full px-3.5 text-[15px] font-medium no-underline"
                >
                    {{ t(item.key) }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
