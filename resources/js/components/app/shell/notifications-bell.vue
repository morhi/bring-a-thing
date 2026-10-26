<script setup lang="ts">
import { BellIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';

const props = withDefaults(
    defineProps<{
        unread?: number;
        expanded?: boolean;
    }>(),
    { unread: 0, expanded: false },
);

defineEmits<{ toggle: [] }>();

const { t } = useI18n();

const label = computed(() =>
    props.unread > 0
        ? t('common.notifications.unread', { n: props.unread })
        : t('nav.notifications'),
);

// Large counts would stretch the badge past the bell
const badge = computed(() =>
    props.unread > 99 ? '99+' : String(props.unread),
);
</script>

<template>
    <Button
        variant="ghost"
        size="icon-lg"
        class="relative rounded-full"
        :aria-label="label"
        :aria-expanded="expanded"
        data-feature="F-23"
        @click="$emit('toggle')"
    >
        <BellIcon class="size-5" aria-hidden="true" />
        <span
            v-if="unread > 0"
            aria-hidden="true"
            class="bg-primary text-primary-fg absolute -top-0.5 -right-0.5 grid h-[18px] min-w-[18px] place-items-center rounded-full px-[5px] text-[11px] font-bold tabular-nums"
        >
            {{ badge }}
        </span>
    </Button>
</template>
