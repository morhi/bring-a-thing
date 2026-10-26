<script setup lang="ts">
import { Check, CircleHelp, X } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

export type MarkAnswer = 'yes' | 'maybe' | 'no' | 'none';

const props = withDefaults(defineProps<{ answer?: MarkAnswer; label?: string }>(), { answer: 'none', label: undefined });

const { t } = useI18n();

const icon = computed(() => ({ yes: Check, maybe: CircleHelp, no: X, none: null })[props.answer]);

const classes = {
    yes: 'bg-primary text-primary-fg',
    maybe: 'border-2 border-warning bg-warning-soft text-warning',
    no: 'bg-danger-soft text-danger',
    none: 'border-2 border-dashed border-border-strong bg-transparent',
};

// The fallback is the answer word; callers pass a richer label such as "Anna: yes"
const accessibleLabel = computed(() => props.label ?? t(`poll.answer.${props.answer}`));
</script>

<template>
    <span
        role="img"
        :aria-label="accessibleLabel"
        :class="cn('inline-grid size-[22px] flex-none place-items-center rounded-full', classes[answer])"
    >
        <component :is="icon" v-if="icon" class="size-[13px]" stroke-width="3" aria-hidden="true" />
    </span>
</template>
