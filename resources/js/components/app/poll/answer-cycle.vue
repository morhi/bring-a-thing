<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Mark from '@/components/app/mark.vue';
import type { PollAnswer } from '@/components/app/poll/types';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        answer?: PollAnswer | null;
        disabled?: boolean;
        // Spoken option description, e.g. "Saturday, 31 October 2026, 19:00–21:00"
        optionLabel: string;
        // Answer choices in cycle order; the poll defines them
        choices?: PollAnswer[];
        // Set when an editor answers for someone else, so the label names that person
        participantName?: string | null;
    }>(),
    {
        answer: null,
        participantName: null,
        disabled: false,
        choices: () => ['yes', 'maybe', 'no'],
    },
);

const emit = defineEmits<{ 'update:answer': [answer: PollAnswer] }>();

const { t } = useI18n();

// Shows the picked answer right away, until the saved answer arrives or the request ends
const pending = ref<PollAnswer | null>(null);
const shown = computed(() => pending.value ?? props.answer);

watch(
    () => props.answer,
    () => {
        pending.value = null;
    },
);

watch(
    () => props.disabled,
    (disabled) => {
        if (!disabled) {
            pending.value = null;
        }
    },
);

const accessibleLabel = computed(() => {
    const label = props.participantName
        ? t('poll.answerLabelFor', {
              name: props.participantName,
              option: props.optionLabel,
          })
        : t('poll.answerLabel', { option: props.optionLabel });

    return `${label}: ${t(`poll.answer.${shown.value ?? 'none'}`)}`;
});

/**
 * Pick the answer a step away from the shown one; no answer counts as the slot before the first choice.
 */
function stepped(step: number): PollAnswer {
    const length = props.choices.length;
    const index = shown.value ? props.choices.indexOf(shown.value) : -1;

    if (index === -1) {
        return step > 0 ? props.choices[0] : props.choices[length - 1];
    }

    return props.choices[(index + step + length) % length];
}

function choose(next: PollAnswer): void {
    // Busy keeps the button focusable instead of disabling it, so arrow keys never drop focus
    if (props.disabled || next === shown.value) {
        return;
    }

    pending.value = next;
    emit('update:answer', next);
}

const steps: Record<string, number> = {
    ArrowRight: 1,
    ArrowDown: 1,
    ArrowLeft: -1,
    ArrowUp: -1,
};

// Arrow keys keep the F-06 keyboard rule: move between the answers and save at once
function arrowSelect(event: KeyboardEvent): void {
    const step = steps[event.key];

    if (!step) {
        return;
    }

    // The grid scrolls horizontally; the arrows belong to the answer here
    event.preventDefault();
    choose(stepped(step));
}

// Hover previews the colour of the answer the next click sets
const hoverTones: Record<PollAnswer, string> = {
    yes: 'group-hover:border-primary! group-hover:border-solid! group-hover:bg-primary/12! group-active:bg-primary/24!',
    maybe: 'group-hover:border-warning! group-hover:bg-warning/12! group-active:bg-warning/24!',
    no: 'group-hover:border-danger! group-hover:bg-danger/12! group-active:bg-danger/24!',
};
</script>

<template>
    <button
        type="button"
        :aria-label="accessibleLabel"
        :aria-disabled="disabled || undefined"
        :data-answer="shown ?? 'none'"
        :class="
            cn(
                'group relative mx-auto grid size-9 place-items-center rounded-full p-0',
                'hover:bg-surface-sunken focus-visible:outline-focus-ring focus-visible:outline-2 focus-visible:outline-offset-1',
                disabled ? 'cursor-progress opacity-60' : 'cursor-pointer',
            )
        "
        @click="choose(stepped(1))"
        @keydown="arrowSelect"
    >
        <Mark
            :answer="shown ?? 'none'"
            aria-hidden="true"
            :class="
                cn(
                    'size-6! transition-[transform,opacity,color,background-color,border-color,filter] duration-(--duration-fast)',
                    !disabled && 'group-active:scale-94',
                    !disabled && !shown && hoverTones[stepped(1)],
                    !disabled && shown && 'group-hover:brightness-110',
                )
            "
        />
    </button>
</template>
