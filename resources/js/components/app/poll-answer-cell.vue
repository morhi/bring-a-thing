<script setup lang="ts">
import { computed } from 'vue';
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
    }>(),
    {
        answer: null,
        editable: false,
        disabled: false,
        participantName: '',
        choices: () => ['yes', 'maybe', 'no'],
    },
);

const emit = defineEmits<{ 'update:answer': [answer: PollAnswer] }>();

const { t } = useI18n();

const readOnlyLabel = computed(() => {
    const word = t(`poll.answer.${props.answer ?? 'none'}`);

    return props.participantName ? `${props.participantName}: ${word}` : word;
});

function choose(value: PollAnswer): void {
    if (props.disabled || value === props.answer) {
        return;
    }

    emit('update:answer', value);
}
</script>

<template>
    <div
        v-if="editable"
        role="radiogroup"
        :aria-label="t('poll.answerLabel', { option: optionLabel })"
        :aria-disabled="disabled || undefined"
        class="bg-surface-sunken inline-flex gap-0.5 rounded-full p-[3px]"
    >
        <label
            v-for="value in choices"
            :key="value"
            :class="
                cn(
                    'relative grid size-[30px] cursor-pointer place-items-center rounded-full',
                    'has-[input:focus-visible]:outline-focus-ring has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-1',
                    answer === value && 'bg-surface-raised shadow-sm',
                    disabled && 'cursor-not-allowed opacity-60',
                )
            "
        >
            <!-- Native radios keep arrow-key navigation and form semantics -->
            <input
                type="radio"
                class="absolute inset-0 m-0 size-full cursor-[inherit] opacity-0"
                :name="name"
                :value="value"
                :checked="answer === value"
                :disabled="disabled"
                @change="choose(value)"
            />
            <Mark
                :answer="value"
                :class="
                    cn(
                        'size-6! transition-[transform,opacity] duration-(--duration-fast)',
                        answer !== value &&
                            'border-border-strong! text-fg-soft! border-[1.5px]! border-solid! bg-transparent!',
                    )
                "
            />
        </label>
    </div>
    <Mark
        v-else
        :answer="answer ?? 'none'"
        :label="readOnlyLabel"
        class="mx-auto"
    />
</template>
