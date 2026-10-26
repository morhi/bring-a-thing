<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        // Calendar date as YYYY-MM-DD; no time zone shifts it
        date: string;
        fixed?: boolean;
        size?: 'default' | 'lg';
    }>(),
    { fixed: false, size: 'default' },
);

const { locale } = useI18n();

const parsed = computed(() => {
    const [year, month, day] = props.date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day));
});

const day = computed(() => parsed.value.getUTCDate());
const month = computed(() => parsed.value.toLocaleDateString(locale.value, { month: 'short', timeZone: 'UTC' }));
</script>

<template>
    <div
        aria-hidden="true"
        :class="
            cn(
                'font-display flex flex-none flex-col items-center justify-center rounded-sm leading-none',
                size === 'lg' ? 'w-16 px-1 py-2.5' : 'w-14 px-1 py-2',
                fixed ? 'bg-primary text-primary-fg' : 'bg-surface-muted text-fg',
            )
        "
    >
        <span :class="cn('font-[720] tabular-nums', size === 'lg' ? 'text-[28px]' : 'text-2xl')">{{ day }}</span>
        <span :class="cn('mt-1 text-xs font-semibold', fixed ? 'opacity-85' : 'text-fg-muted')">{{ month }}</span>
    </div>
</template>
