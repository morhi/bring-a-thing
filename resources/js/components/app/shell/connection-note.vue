<script setup lang="ts">
import { WifiOffIcon } from '@lucide/vue';
import { useI18n } from '@/composables/use-i18n';

const { t } = useI18n();
</script>

<template>
    <div
        role="status"
        data-feature="F-21"
        class="bg-warning-soft text-warning border-warning/30 sticky top-0 z-60 flex items-center justify-center gap-2 border-b px-4 py-2.5 text-sm font-medium"
    >
        <WifiOffIcon class="size-4 shrink-0" aria-hidden="true" />
        <span>{{ t('conn.lost') }}</span>
    </div>
</template>
