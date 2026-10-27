<script setup lang="ts">
import { computed } from 'vue';
import Mark from '@/components/app/mark.vue';
import PollAnswerCell from '@/components/app/poll-answer-cell.vue';
import { describeOption } from '@/components/app/poll/dates';
import type { PollAnswer, PollGridOption } from '@/components/app/poll/types';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        option: PollGridOption;
        // The current person's own answer; the block colour follows it like the table marks
        answer?: PollAnswer | null;
        yes: number;
        best?: boolean;
        // False for people outside the audience: they see the counts but have no answer
        showAnswer?: boolean;
        editable?: boolean;
        busy?: boolean;
        name: string;
        // Narrow day tiles stack the answers and drop the label
        compact?: boolean;
    }>(),
    {
        answer: null,
        best: false,
        showAnswer: true,
        editable: false,
        busy: false,
        compact: false,
    },
);

const emit = defineEmits<{ answer: [answer: PollAnswer] }>();

const { t, locale } = useI18n();

const tones: Record<PollAnswer | 'none', string> = {
    yes: 'border-primary bg-primary-soft text-primary-soft-fg',
    maybe: 'border-warning bg-warning-soft text-fg',
    no: 'border-danger/50 bg-danger-soft text-fg',
    none: 'border-border-strong bg-surface-raised text-fg border-dashed',
};

const tone = computed(() =>
    props.showAnswer ? tones[props.answer ?? 'none'] : tones.none,
);

const time = computed(() =>
    props.option.start && props.option.end
        ? `${props.option.start}–${props.option.end}`
        : '',
);

const description = computed(() => describeOption(props.option, locale.value));
</script>

<template>
    <div
        :title="option.label || undefined"
        :class="
            cn(
                'flex min-w-0 flex-col gap-1 rounded-lg border border-l-4 text-[11px] leading-tight',
                compact ? 'items-center px-0.5 py-1' : 'px-1.5 py-1',
                tone,
                option.fixed && 'shadow-[0_0_0_2px_var(--primary)]',
            )
        "
    >
        <span class="sr-only">{{ description }}</span>
        <span
            :class="
                cn(
                    'flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5',
                    compact && 'justify-center',
                )
            "
        >
            <span
                v-if="time"
                class="truncate font-bold tabular-nums"
                aria-hidden="true"
                >{{ time }}</span
            >
            <span class="inline-flex items-center gap-0.5 font-semibold">
                <Mark
                    answer="yes"
                    aria-hidden="true"
                    class="size-3.5! [&_svg]:size-2"
                />
                <span aria-hidden="true">{{ yes }}</span>
                <span class="sr-only">{{
                    t('poll.totals.yes', { n: yes })
                }}</span>
            </span>
            <span
                v-if="option.fixed"
                class="bg-primary font-tag text-primary-fg rounded-md px-1 text-[10px] font-bold"
                >{{ t('group.polls.fixed') }}</span
            >
            <span v-else-if="best" class="text-primary text-[10px] font-bold">{{
                t('poll.best')
            }}</span>
        </span>
        <span
            v-if="option.label && !compact"
            class="truncate"
            aria-hidden="true"
            >{{ option.label }}</span
        >
        <PollAnswerCell
            v-if="showAnswer"
            :answer="answer"
            :editable="editable"
            :disabled="busy"
            :name="name"
            :option-label="description"
            :participant-name="t('group.member.you')"
            size="sm"
            :stacked="compact"
            :class="!editable && !compact && 'mx-0'"
            @update:answer="emit('answer', $event)"
        />
    </div>
</template>
