<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import Mark from '@/components/app/mark.vue';
import PollAnswerCell from '@/components/app/poll-answer-cell.vue';
import AnswerDot from '@/components/app/poll/answer-dot.vue';
import { describeOption } from '@/components/app/poll/dates';
import NoteButton from '@/components/app/poll/note-button.vue';
import NoteMarker from '@/components/app/poll/note-marker.vue';
import type {
    PollAnswer,
    PollGridOption,
    PollOptionNote,
} from '@/components/app/poll/types';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        option: PollGridOption;
        // The current person's own answer; the block colour follows it like the table marks
        answer?: PollAnswer | null;
        yes: number;
        // False for people outside the audience: they see the counts but have no answer
        showAnswer?: boolean;
        editable?: boolean;
        busy?: boolean;
        name: string;
        // Too narrow for the selector: the block becomes a button that opens the day panel
        compact?: boolean;
        // The compact block whose day the panel shows
        selected?: boolean;
        // Month cells stay neutral so a fully picked month remains readable; week blocks keep their colour
        variant?: 'day' | 'block';
        // Day number shown top-left in the month cell
        day?: number | null;
        // Marks the day number as the current day
        today?: boolean;
        // Closed polls still show the own answer in the selector, just locked
        closed?: boolean;
        // The own note on this option and the notes of other people (F-06)
        note?: string | null;
        otherNotes?: PollOptionNote[];
    }>(),
    {
        answer: null,
        showAnswer: true,
        editable: false,
        busy: false,
        compact: false,
        selected: false,
        variant: 'block',
        day: null,
        today: false,
        closed: false,
        note: null,
        otherNotes: () => [],
    },
);

const emit = defineEmits<{
    answer: [answer: PollAnswer];
    select: [];
    editNote: [];
}>();

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

// A note needs an answer, so the button only appears once the own answer exists
const canEditNote = computed(
    () => props.showAnswer && props.editable && !!props.answer,
);

// The own note stays readable on a closed poll, through the marker
const markerNotes = computed<PollOptionNote[]>(() =>
    !canEditNote.value && props.showAnswer && props.note
        ? [
              { name: t('group.member.you'), note: props.note },
              ...props.otherNotes,
          ]
        : props.otherNotes,
);

const compactLabel = computed(() =>
    [
        description.value,
        props.showAnswer
            ? `${t('group.member.you')}: ${t(`poll.answer.${props.answer ?? 'none'}`)}`
            : '',
        t('poll.totals.yes', { n: props.yes }),
    ]
        .filter(Boolean)
        .join(', '),
);
</script>

<template>
    <div
        v-if="variant === 'day'"
        :title="option.label || undefined"
        class="group/note flex min-w-0 flex-1 flex-col gap-1 text-[11px] leading-tight"
    >
        <span class="sr-only">{{ description }}</span>
        <span class="flex min-w-0 items-center justify-between gap-1">
            <span
                v-if="day !== null"
                aria-hidden="true"
                :class="
                    cn(
                        'grid size-6 flex-none place-items-center rounded-full text-xs leading-none font-semibold tabular-nums',
                        today && 'bg-primary text-primary-fg',
                    )
                "
                >{{ day }}</span
            >
            <span class="ml-auto inline-flex items-center gap-0.5">
                <NoteMarker :notes="markerNotes" />
                <NoteButton
                    v-if="canEditNote"
                    :note="note"
                    :option-label="description"
                    size="md"
                    class="-my-1"
                    @open="emit('editNote')"
                />
            </span>
            <span
                class="text-fg-muted inline-flex items-center gap-0.5 font-semibold tabular-nums"
            >
                <Check
                    class="text-primary size-3"
                    stroke-width="3"
                    aria-hidden="true"
                />
                <span aria-hidden="true">{{ yes }}</span>
                <span class="sr-only">{{
                    t('poll.totals.yes', { n: yes })
                }}</span>
            </span>
        </span>
        <span
            v-if="option.fixed"
            class="bg-primary font-tag text-primary-fg self-start rounded-md px-1 text-[10px] font-bold"
            >{{ t('group.polls.fixed') }}</span
        >
        <span v-if="option.label" class="truncate px-0.5" aria-hidden="true">{{
            option.label
        }}</span>
        <PollAnswerCell
            v-if="showAnswer"
            :answer="answer"
            :editable="editable"
            :locked="!editable && closed"
            :disabled="busy"
            :name="name"
            :option-label="description"
            :participant-name="t('group.member.you')"
            size="sm"
            tinted
            class="mt-auto self-center"
            @update:answer="emit('answer', $event)"
        />
    </div>
    <button
        v-else-if="compact"
        type="button"
        :title="option.label || undefined"
        :aria-label="compactLabel"
        :aria-pressed="selected"
        :class="
            cn(
                'focus-visible:outline-focus-ring flex min-w-0 cursor-pointer flex-col items-start gap-0.5 overflow-hidden rounded-lg border border-l-4 px-1 py-1 text-left text-[11px] leading-tight focus-visible:outline-2 focus-visible:outline-offset-2',
                tone,
                option.fixed && 'shadow-[0_0_0_2px_var(--primary)]',
                selected && 'ring-fg ring-2',
            )
        "
        @click="emit('select')"
    >
        <span
            v-if="time"
            class="w-full truncate font-bold tabular-nums"
            aria-hidden="true"
            >{{ time }}</span
        >
        <span
            class="inline-flex items-center gap-1 font-semibold tabular-nums"
            aria-hidden="true"
        >
            <AnswerDot v-if="showAnswer" :answer="answer" />
            <Mark answer="yes" class="size-3.5! [&_svg]:size-2" />
            {{ yes }}
        </span>
    </button>
    <div
        v-else
        :title="option.label || undefined"
        :class="
            cn(
                'group/note flex min-w-0 flex-col gap-1 rounded-lg border border-l-4 px-1.5 py-1 text-[11px] leading-tight',
                tone,
                option.fixed && 'shadow-[0_0_0_2px_var(--primary)]',
            )
        "
    >
        <span class="sr-only">{{ description }}</span>
        <span class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5">
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
            <span class="ml-auto inline-flex items-center gap-0.5">
                <NoteMarker :notes="markerNotes" />
                <NoteButton
                    v-if="canEditNote"
                    :note="note"
                    :option-label="description"
                    size="md"
                    class="-my-1"
                    @open="emit('editNote')"
                />
            </span>
        </span>
        <span v-if="option.label" class="truncate" aria-hidden="true">{{
            option.label
        }}</span>
        <PollAnswerCell
            v-if="showAnswer"
            :answer="answer"
            :editable="editable"
            :locked="!editable && closed"
            :disabled="busy"
            :name="name"
            :option-label="description"
            :participant-name="t('group.member.you')"
            size="sm"
            tinted
            :class="!editable && 'mx-0'"
            @update:answer="emit('answer', $event)"
        />
    </div>
</template>
