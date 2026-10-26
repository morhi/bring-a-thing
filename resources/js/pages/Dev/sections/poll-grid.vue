<script setup lang="ts">
import { ref } from 'vue';
import EmptyState from '@/components/app/empty-state.vue';
import PollGrid from '@/components/app/poll-grid.vue';
import { useI18n } from '@/composables/use-i18n';
import type {
    PollAnswer,
    PollAnswers,
    PollGridOption,
    PollGridParticipant,
    PollKey,
    PollOptionTotals,
} from '@/components/app/poll/types';

const { t } = useI18n();

const me = 1;

const dayOptions: PollGridOption[] = [
    { id: 'a', date: '2026-11-06', start: null, end: null, label: null },
    {
        id: 'b',
        date: '2026-11-07',
        start: null,
        end: null,
        label: 'Room free until 22:00',
    },
    { id: 'c', date: '2026-11-13', start: null, end: null, label: null },
    { id: 'd', date: '2026-11-14', start: null, end: null, label: null },
];

const slotOptions: PollGridOption[] = [
    {
        id: 's1',
        date: '2026-11-10',
        start: '12:00',
        end: '13:00',
        label: 'Lunch',
    },
    { id: 's2', date: '2026-11-10', start: '18:00', end: '20:00', label: null },
    {
        id: 's3',
        date: '2026-11-10',
        start: '20:30',
        end: '22:00',
        label: 'Late session with a long label',
    },
];

const people: PollGridParticipant[] = [
    { id: me, name: 'Maurice Hildebrandt' },
    { id: 2, name: 'Anna Keller' },
    { id: 3, name: null, email: 'ben@example.com' },
    { id: 4, name: 'Chiara Bernasconi-Rüegg' },
];

const manyPeople: PollGridParticipant[] = [
    ...people,
    ...[
        'Dario',
        'Elif',
        'Fabian',
        'Gülsen',
        'Hannes',
        'Ines',
        'Jonas',
        'Katrin',
    ].map((name, index) => ({ id: 10 + index, name })),
];

// The sample stands in for backend payloads: answers, totals and best ids all arrive as props
const dayAnswers = ref<PollAnswers>({
    '1': { a: 'yes', b: 'maybe' },
    '2': { a: 'yes', b: 'yes', c: 'no', d: 'maybe' },
    '3': { a: 'no', b: 'yes', c: 'yes', d: 'no' },
    '4': { a: 'yes', b: 'yes', c: 'maybe', d: 'no' },
});

const dayTotals: Record<string, PollOptionTotals> = {
    a: { yes: 3, maybe: 0, no: 1 },
    b: { yes: 3, maybe: 1, no: 0 },
    c: { yes: 1, maybe: 1, no: 1 },
    d: { yes: 0, maybe: 1, no: 2 },
};

const slotAnswers: PollAnswers = {
    '2': { s1: 'yes', s2: 'yes', s3: 'no' },
    '3': { s1: 'yes', s2: 'yes', s3: 'no' },
    '4': { s1: 'maybe', s2: 'no', s3: 'yes' },
};

const slotTotals: Record<string, PollOptionTotals> = {
    s1: { yes: 2, maybe: 1, no: 0 },
    s2: { yes: 2, maybe: 0, no: 1 },
    s3: { yes: 1, maybe: 0, no: 2 },
};

// Tie: two options with equal yes and no counts both carry the label
const tieTotals: Record<string, PollOptionTotals> = {
    s1: { yes: 3, maybe: 0, no: 1 },
    s2: { yes: 3, maybe: 0, no: 1 },
    s3: { yes: 1, maybe: 0, no: 3 },
};

const fixedOptions: PollGridOption[] = dayOptions.map((option) => ({
    ...option,
    fixed: option.id === 'a' || option.id === 'b',
}));

const manyAnswers: PollAnswers = Object.fromEntries(
    manyPeople.map((person, row) => [
        String(person.id),
        Object.fromEntries(
            dayOptions.map((option, column) => [
                option.id,
                (['yes', 'maybe', 'no'] as PollAnswer[])[(row + column) % 3],
            ]),
        ),
    ]),
);

const emptyTotals: Record<string, PollOptionTotals> = Object.fromEntries(
    dayOptions.map((option) => [option.id, { yes: 0, maybe: 0, no: 0 }]),
);

const lastAnswer = ref('');

function answer(optionId: PollKey, value: PollAnswer): void {
    dayAnswers.value = {
        ...dayAnswers.value,
        [String(me)]: { ...dayAnswers.value[String(me)], [optionId]: value },
    };
    lastAnswer.value = `${optionId}: ${value}`;
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="poll-grid" class="text-xl">Poll grid</h2>
        <p class="text-fg-muted">
            Participants by options. The own row is editable with a three-state
            radio group (arrow keys move between yes, maybe and no). Best option
            ids, totals and the no-count flag come from the backend; the grid
            only renders them. Only the grid scrolls sideways.
        </p>
        <div
            v-for="theme in ['', 'dark']"
            :key="theme"
            :class="theme"
            class="bg-surface-raised text-fg flex min-w-0 flex-col gap-8 rounded-2xl p-5"
        >
            <p class="text-fg-muted text-sm font-semibold">
                {{ theme || 'light' }}
            </p>

            <div class="flex min-w-0 flex-col gap-3">
                <h3 class="text-base">
                    By day, open, best option decided by fewest no
                </h3>
                <PollGrid
                    :id-prefix="`day-${theme}`"
                    :options="dayOptions"
                    :participants="people"
                    :answers="dayAnswers"
                    :totals="dayTotals"
                    :best-option-ids="['b']"
                    show-no-totals
                    :current-participant-id="me"
                    @answer="answer"
                />
                <p v-if="lastAnswer" class="text-fg-muted text-sm">
                    answer: {{ lastAnswer }}
                </p>
            </div>

            <div class="flex min-w-0 flex-col gap-3">
                <h3 class="text-base">
                    Time slots on one day, tie: both carry the label
                </h3>
                <PollGrid
                    :id-prefix="`tie-${theme}`"
                    :options="slotOptions"
                    :participants="people"
                    :answers="slotAnswers"
                    :totals="tieTotals"
                    :best-option-ids="['s1', 's2']"
                    :current-participant-id="me"
                />
            </div>

            <div class="flex min-w-0 flex-col gap-3">
                <h3 class="text-base">Saving (own row busy)</h3>
                <PollGrid
                    :id-prefix="`busy-${theme}`"
                    :options="slotOptions"
                    :participants="people"
                    :answers="slotAnswers"
                    :totals="slotTotals"
                    :best-option-ids="['s1']"
                    :current-participant-id="me"
                    busy
                />
            </div>

            <div class="flex min-w-0 flex-col gap-3">
                <h3 class="text-base">Closed with fixed options</h3>
                <PollGrid
                    :id-prefix="`fixed-${theme}`"
                    :options="fixedOptions"
                    :participants="people"
                    :answers="dayAnswers"
                    :totals="dayTotals"
                    :current-participant-id="me"
                    closed
                />
            </div>

            <div class="flex min-w-0 flex-col gap-3">
                <h3 class="text-base">
                    Attendance poll: no best option, many participants
                </h3>
                <PollGrid
                    :id-prefix="`many-${theme}`"
                    :options="[...dayOptions, ...slotOptions]"
                    :participants="manyPeople"
                    :answers="manyAnswers"
                    :totals="{ ...dayTotals, ...slotTotals }"
                    :current-participant-id="me"
                />
            </div>

            <div class="flex min-w-0 flex-col gap-3">
                <h3 class="text-base">No answers yet</h3>
                <PollGrid
                    :id-prefix="`empty-${theme}`"
                    :options="dayOptions"
                    :participants="[people[0]]"
                    :answers="{}"
                    :totals="emptyTotals"
                    :current-participant-id="me"
                />
                <EmptyState
                    :title="t('poll.empty.title')"
                    :text="t('poll.empty.text')"
                />
            </div>
        </div>
    </section>
</template>
