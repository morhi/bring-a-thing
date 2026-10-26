<script setup lang="ts">
import { BellIcon } from '@lucide/vue';
import { computed, useTemplateRef } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';

const props = withDefaults(
    defineProps<{
        unread?: number;
        expanded?: boolean;
        // id of the panel the bell opens
        controls?: string;
    }>(),
    { unread: 0, expanded: false, controls: 'notifications-panel' },
);

defineEmits<{ toggle: [] }>();

const { t, tc } = useI18n();

const button = useTemplateRef<InstanceType<typeof Button>>('button');

const label = computed(() =>
    props.unread > 0
        ? tc('common.notifications.unread', props.unread)
        : t('nav.notifications'),
);

// Large counts would stretch the badge past the bell
const badge = computed(() =>
    props.unread > 99 ? '99+' : String(props.unread),
);

function focus(): void {
    (button.value?.$el as HTMLElement | undefined)?.focus();
}

defineExpose({ focus });
</script>

<template>
    <Button
        type="button"
        ref="button"
        variant="ghost"
        size="icon-lg"
        class="relative rounded-full"
        :aria-label="label"
        :aria-expanded="expanded"
        :aria-controls="controls"
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
