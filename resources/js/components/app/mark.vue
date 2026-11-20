<script setup lang="ts">
import { Check, X } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

export type MarkAnswer = 'yes' | 'maybe' | 'no' | 'none';

const props = withDefaults(
    defineProps<{ answer?: MarkAnswer; label?: string }>(),
    { answer: 'none', label: undefined },
);

const { t } = useI18n();

const icon = computed(
    () => ({ yes: Check, maybe: null, no: X, none: null })[props.answer],
);

const classes = {
    yes: 'bg-primary text-primary-fg',
    maybe: 'border-2 border-warning bg-warning-soft text-warning',
    no: 'bg-danger-soft text-danger',
    none: 'border-2 border-dashed border-border-strong bg-transparent',
};

// The fallback is the answer word; callers pass a richer label such as "Anna: yes"
const accessibleLabel = computed(
    () => props.label ?? t(`poll.answer.${props.answer}`),
);
</script>

<template>
    <span
        role="img"
        :aria-label="accessibleLabel"
        :class="
            cn(
                'inline-grid size-[22px] flex-none place-items-center rounded-full',
                classes[answer],
            )
        "
    >
        <!-- Lucide only ships the question mark inside a circle, which doubles the ring of the round mark -->
        <svg
            v-if="answer === 'maybe'"
            class="size-[13px]"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="3"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
        >
            <path d="M9 8.5a3 3 0 0 1 5.8 1c0 2.2-2.8 2.7-2.8 4.7" />
            <path d="M12 19.5h.01" />
        </svg>
        <component
            :is="icon"
            v-else-if="icon"
            class="size-[13px]"
            stroke-width="3"
            aria-hidden="true"
        />
    </span>
</template>
