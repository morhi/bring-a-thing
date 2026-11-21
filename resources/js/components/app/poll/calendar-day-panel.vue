<script setup lang="ts">
import { computed } from 'vue';
import Mark from '@/components/app/mark.vue';
import PollAnswerCell from '@/components/app/poll-answer-cell.vue';
import { describeOption, formatDate } from '@/components/app/poll/dates';
import type {
    PollAnswer,
    PollGridOption,
    PollKey,
    PollOptionTotals,
} from '@/components/app/poll/types';
import { useI18n } from '@/composables/use-i18n';

const props = withDefaults(
    defineProps<{
        id: string;
        // YYYY-MM-DD of the selected day
        day: string;
        entries: {
            option: PollGridOption;
            key: string;
            answer: PollAnswer | null;
            totals: PollOptionTotals;
        }[];
        showAnswers?: boolean;
        closed?: boolean;
        busy?: boolean;
        idPrefix?: string;
    }>(),
    { showAnswers: true, closed: false, busy: false, idPrefix: 'poll' },
);

const emit = defineEmits<{ answer: [optionId: PollKey, answer: PollAnswer] }>();

defineSlots<{
    note?: (props: { option: PollGridOption }) => unknown;
    // Notes as plain text under the selector; touch screens have no hover to reveal a tooltip
    notes?: (props: { option: PollGridOption }) => unknown;
}>();

const { t, locale } = useI18n();

const heading = computed(() =>
    formatDate(props.day, locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    }),
);

const counts: PollAnswer[] = ['yes', 'maybe', 'no'];

function subtitle(option: PollGridOption): string {
    const time =
        option.start && option.end ? `${option.start}–${option.end}` : '';

    return [time, option.label].filter(Boolean).join(' · ');
}
</script>

<template>
    <section
        :id="id"
        :aria-label="heading"
        class="border-border flex flex-col gap-3 border-t pt-3"
    >
        <h3 class="font-display text-base font-[640]" aria-live="polite">
            {{ heading }}
        </h3>
        <div
            v-for="entry in entries"
            :key="entry.key"
            class="flex flex-col gap-2"
        >
            <div
                class="flex min-w-0 flex-wrap items-center justify-between gap-2"
            >
                <span class="flex min-w-0 items-center gap-2 text-sm">
                    <span
                        v-if="subtitle(entry.option)"
                        class="truncate font-semibold tabular-nums"
                        >{{ subtitle(entry.option) }}</span
                    >
                    <span
                        v-if="entry.option.fixed"
                        class="bg-primary font-tag text-primary-fg rounded-md px-1 text-[10px] font-bold"
                        >{{ t('group.polls.fixed') }}</span
                    >
                </span>
                <span
                    class="text-fg-muted ml-auto inline-flex items-center gap-2.5 text-sm font-semibold tabular-nums"
                >
                    <span
                        v-for="count in counts"
                        :key="count"
                        class="inline-flex items-center gap-1"
                    >
                        <Mark
                            :answer="count"
                            aria-hidden="true"
                            class="size-4! [&_svg]:size-2.5"
                        />
                        <span aria-hidden="true">{{
                            entry.totals[count]
                        }}</span>
                        <span class="sr-only">{{
                            t(`poll.totals.${count}`, {
                                n: entry.totals[count],
                            })
                        }}</span>
                    </span>
                </span>
            </div>
            <div
                v-if="showAnswers || $slots.note"
                class="flex flex-wrap items-center gap-2"
            >
                <PollAnswerCell
                    v-if="showAnswers"
                    :answer="entry.answer"
                    :editable="!closed"
                    :locked="closed"
                    :disabled="busy"
                    :name="`${idPrefix}-calendar-panel-${entry.key}`"
                    :option-label="describeOption(entry.option, locale)"
                    :participant-name="t('group.member.you')"
                    tinted
                    @update:answer="emit('answer', entry.option.id, $event)"
                />
                <slot name="note" :option="entry.option" />
            </div>
            <slot name="notes" :option="entry.option" />
        </div>
    </section>
</template>
