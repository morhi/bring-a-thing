<script setup lang="ts">
import { RadioGroupItem, RadioGroupRoot } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import Mark from '@/components/app/mark.vue';
import type { PollAnswer } from '@/components/app/poll/types';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        answer?: PollAnswer | null;
        editable?: boolean;
        disabled?: boolean;
        // Unique per option, groups the three radios of one cell
        name: string;
        // Spoken option description, e.g. "Saturday, 31 October 2026, 19:00–21:00"
        optionLabel: string;
        participantName?: string;
        // Answer choices in display order; the poll defines them
        choices?: PollAnswer[];
        size?: 'md' | 'sm';
        // Stacks the choices for narrow calendar days
        stacked?: boolean;
        // Shows the choices without accepting input, e.g. the own answer on a closed poll
        locked?: boolean;
        // Calendar views tint the whole pill in the answer colour, so a picked day reads at a glance
        tinted?: boolean;
    }>(),
    {
        answer: null,
        editable: false,
        disabled: false,
        participantName: '',
        choices: () => ['yes', 'maybe', 'no'],
        size: 'md',
        stacked: false,
        locked: false,
        tinted: false,
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

const readOnlyLabel = computed(() => {
    const word = t(`poll.answer.${props.answer ?? 'none'}`);

    return props.participantName ? `${props.participantName}: ${word}` : word;
});

function choose(value: unknown): void {
    const next = value as PollAnswer;

    // Busy keeps the radios focusable instead of disabling them, so arrow keys never drop focus
    if (props.locked || props.disabled || next === shown.value) {
        return;
    }

    pending.value = next;
    emit('update:answer', next);
}

// Idle choices take the answer colour on hover and a stronger tint while pressed
const hoverTones: Record<PollAnswer, string> = {
    yes: 'group-hover:border-primary! group-hover:text-primary! group-hover:bg-primary/12! group-active:bg-primary/24!',
    maybe: 'group-hover:border-warning! group-hover:text-warning! group-hover:bg-warning/12! group-active:bg-warning/24!',
    no: 'group-hover:border-danger! group-hover:text-danger! group-hover:bg-danger/12! group-active:bg-danger/24!',
};

// A light tint plus a border in the answer colour; the tokens adapt to both themes
const pillTones: Record<PollAnswer, string> = {
    yes: 'bg-primary/12 inset-ring inset-ring-primary',
    maybe: 'bg-warning/14 inset-ring inset-ring-warning',
    no: 'bg-danger/12 inset-ring inset-ring-danger',
};

const interactive = computed(() => !props.locked && !props.disabled);

const steps: Record<string, number> = {
    ArrowRight: 1,
    ArrowDown: 1,
    ArrowLeft: -1,
    ArrowUp: -1,
};

// Reka only checks on arrow focus while the key is still held, so a quick press would just move focus
function arrowSelect(event: KeyboardEvent): void {
    const step = steps[event.key];
    const current = (event.target as HTMLElement | null)?.closest<HTMLElement>(
        '[data-answer]',
    )?.dataset.answer as PollAnswer | undefined;

    if (!step || !current) {
        return;
    }

    const index = props.choices.indexOf(current);
    const length = props.choices.length;

    choose(props.choices[(index + step + length) % length]);
}
</script>

<template>
    <!-- One tab stop per option; arrow keys move between and pick the answers (roving radio group) -->
    <RadioGroupRoot
        v-if="editable || locked"
        :model-value="shown"
        :name="name"
        :disabled="locked"
        :aria-label="t('poll.answerLabel', { option: optionLabel })"
        :aria-disabled="disabled || undefined"
        :class="
            cn(
                'inline-flex gap-0.5 rounded-full p-[3px] transition-[background-color,box-shadow] duration-(--duration-fast)',
                tinted && shown ? pillTones[shown] : 'bg-surface-sunken',
                stacked && 'flex-col',
            )
        "
        @update:model-value="choose"
        @keydown="arrowSelect"
    >
        <RadioGroupItem
            v-for="value in choices"
            :key="value"
            :value="value"
            :data-answer="value"
            :aria-label="t(`poll.answer.${value}`)"
            :class="
                cn(
                    'group relative grid shrink-0 place-items-center rounded-full p-0',
                    'focus-visible:outline-focus-ring focus-visible:outline-2 focus-visible:outline-offset-2',
                    size === 'sm' ? 'size-6' : 'size-[30px]',
                    interactive && 'cursor-pointer',
                    shown === value && 'bg-surface-raised shadow-sm',
                    disabled && !locked && 'cursor-progress opacity-60',
                    // The own answer stays fully visible on a closed poll; only the other choices fade
                    locked && shown !== value && 'opacity-40',
                )
            "
        >
            <Mark
                :answer="value"
                aria-hidden="true"
                :class="
                    cn(
                        'transition-[transform,opacity,color,background-color,border-color,filter] duration-(--duration-fast)',
                        size === 'sm'
                            ? 'size-[18px]! [&_svg]:size-3'
                            : 'size-6!',
                        shown !== value &&
                            'border-border-strong! text-fg-soft! border-[1.5px]! border-solid! bg-transparent!',
                        interactive && 'group-active:scale-94',
                        interactive && shown !== value && hoverTones[value],
                        interactive &&
                            shown === value &&
                            'group-hover:brightness-110',
                    )
                "
            />
        </RadioGroupItem>
    </RadioGroupRoot>
    <Mark
        v-else
        :answer="answer ?? 'none'"
        :label="readOnlyLabel"
        class="mx-auto"
    />
</template>
