<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { useElementSize, useMediaQuery } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import AnswerDot from '@/components/app/poll/answer-dot.vue';
import CalendarDayPanel from '@/components/app/poll/calendar-day-panel.vue';
import CalendarOption from '@/components/app/poll/calendar-option.vue';
import NoteButton from '@/components/app/poll/note-button.vue';
import {
    HOUR_END,
    HOUR_HEIGHT,
    HOUR_START,
    hourLabel,
    slotStyle,
} from '@/components/app/poll/calendar';
import {
    addDays,
    dateLocale,
    describeOption,
    fromIso,
    mondayOf,
    toIso,
    toMinutes,
    toTime,
} from '@/components/app/poll/dates';
import type {
    OptionCalendarMode,
    PollAnswer,
    PollGridOption,
    PollKey,
    PollOptionNote,
    PollOptionTotals,
} from '@/components/app/poll/types';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        mode: OptionCalendarMode;
        // Chronological, as the backend sends them; the tab order follows it
        options: PollGridOption[];
        // The current person's answers keyed by option id
        answers: Record<string, PollAnswer>;
        totals: Record<string, PollOptionTotals>;
        // Accepted for callers but unused: "Best option" only shows in the table view
        bestOptionIds?: PollKey[];
        // False when the current person is not asked, so no own answer exists
        showAnswers?: boolean;
        closed?: boolean;
        busy?: boolean;
        idPrefix?: string;
        // The current person's notes and the notes of other people, both keyed by option id (F-06)
        notes?: Record<string, string>;
        otherNotes?: Record<string, PollOptionNote[]>;
    }>(),
    {
        notes: () => ({}),
        otherNotes: () => ({}),
        bestOptionIds: () => [],
        showAnswers: true,
        closed: false,
        busy: false,
        idPrefix: 'poll',
    },
);

const emit = defineEmits<{
    answer: [optionId: PollKey, answer: PollAnswer];
    editNote: [optionId: PollKey];
}>();

const { t, locale } = useI18n();

// Tall enough for the time line and the answer row of a block
const BLOCK_MIN_HEIGHT = 48;

// The small answer selector plus the padding of a month cell or a week block
const MONTH_CELL_MIN_WIDTH = 92;
const BLOCK_MIN_WIDTH = 100;
const MONTH_GAP = 4;
const HOUR_AXIS_WIDTH = 52;

const localeTag = computed(() => dateLocale(locale.value));
const narrow = useMediaQuery('(max-width: 719px)');
const small = useMediaQuery('(max-width: 639px)');

// Measured, because the card around the calendar can be narrower than the viewport suggests
const root = useTemplateRef<HTMLElement>('root');
const { width } = useElementSize(root);

// Opens where the poll starts; a poll without options falls back to the current month
const anchor = fromIso(props.options[0]?.date ?? toIso(new Date()));
const monthCursor = ref(new Date(anchor.getFullYear(), anchor.getMonth(), 1));
const weekAnchor = ref(anchor);

type Entry = {
    option: PollGridOption;
    key: string;
    answer: PollAnswer | null;
    yes: number;
    totals: PollOptionTotals;
    note: string | null;
    otherNotes: PollOptionNote[];
};

const entries = computed<Entry[]>(() =>
    props.options.map((option) => {
        const key = String(option.id);

        return {
            option,
            key,
            answer: props.answers[key] ?? null,
            yes: props.totals[key]?.yes ?? 0,
            totals: props.totals[key] ?? { yes: 0, maybe: 0, no: 0 },
            note: props.notes[key] ?? null,
            otherNotes: props.otherNotes[key] ?? [],
        };
    }),
);

function entriesOn(day: string): Entry[] {
    return entries.value.filter((entry) => entry.option.date === day);
}

function answerOf(optionId: PollKey): PollAnswer | null {
    return props.answers[String(optionId)] ?? null;
}

function noteOf(optionId: PollKey): string | null {
    return props.notes[String(optionId)] ?? null;
}

// The panel spells out every note, the own one included, since touch screens cannot hover a marker
function panelNotes(optionId: PollKey): PollOptionNote[] {
    const others = props.otherNotes[String(optionId)] ?? [];
    const own = noteOf(optionId);

    if (!own || !props.showAnswers) {
        return others;
    }

    return [{ name: t('group.member.you'), note: own }, ...others];
}

function answer(entry: Entry, value: PollAnswer): void {
    emit('answer', entry.option.id, value);
}

function shortDay(date: Date): string {
    return date
        .toLocaleDateString(localeTag.value, { weekday: 'short' })
        .replace('.', '');
}

/* Month view for whole-day polls */

const weekdayLabels = computed(() => {
    const monday = mondayOf(new Date(2026, 0, 5));

    return Array.from({ length: 7 }, (_, index) =>
        shortDay(addDays(monday, index)),
    );
});

const monthLabel = computed(() =>
    monthCursor.value.toLocaleDateString(localeTag.value, {
        month: 'long',
        year: 'numeric',
    }),
);

const weeks = computed(() => {
    const first = monthCursor.value;
    const start = mondayOf(first);
    const last = new Date(first.getFullYear(), first.getMonth() + 1, 0);
    const rows = Math.ceil((((first.getDay() + 6) % 7) + last.getDate()) / 7);

    return Array.from({ length: rows }, (_, row) =>
        Array.from({ length: 7 }, (_, column) => {
            const date = addDays(start, row * 7 + column);
            const value = toIso(date);
            const outside = date.getMonth() !== first.getMonth();

            return {
                value,
                day: date.getDate(),
                outside,
                // Days of the neighbour months stay empty, so an option never shows twice while paging
                entries: outside ? [] : entriesOn(value),
            };
        }),
    );
});

// Zero means not measured yet; the viewport rule alone decides until then
const compactMonth = computed(
    () =>
        small.value ||
        (width.value > 0 &&
            (width.value - 6 * MONTH_GAP) / 7 < MONTH_CELL_MIN_WIDTH),
);

function cellLabel(cell: { entries: Entry[] }): string {
    return cell.entries
        .map((entry) =>
            [
                describeOption(entry.option, locale.value),
                props.showAnswers
                    ? `${t('group.member.you')}: ${t(`poll.answer.${entry.answer ?? 'none'}`)}`
                    : '',
                t('poll.totals.yes', { n: entry.yes }),
            ]
                .filter(Boolean)
                .join(', '),
        )
        .join('; ');
}

function cellAnswer(cell: { entries: Entry[] }): PollAnswer | null {
    // Several options on one day show the first open one, so nothing unanswered hides
    const open = cell.entries.find((entry) => !entry.answer);

    return open ? null : (cell.entries[0]?.answer ?? null);
}

function cellYes(cell: { entries: Entry[] }): number {
    return cell.entries.reduce((sum, entry) => sum + entry.yes, 0);
}

const legend: (PollAnswer | null)[] = ['yes', 'maybe', 'no', null];

function shiftMonth(delta: number): void {
    monthCursor.value = new Date(
        monthCursor.value.getFullYear(),
        monthCursor.value.getMonth() + delta,
        1,
    );
}

/* Week view for time-slot polls */

const visibleDayCount = computed(() => (narrow.value ? 3 : 7));

// Wide screens show calendar weeks from Monday; narrow ones show three days from the anchor
const weekStart = computed(() =>
    narrow.value ? weekAnchor.value : mondayOf(weekAnchor.value),
);

function endMinutes(option: PollGridOption): number {
    const start = toMinutes(option.start as string);
    const end = toMinutes(option.end as string);

    // An end at or before the start runs past midnight; the grid cuts it at the day's end
    return end > start ? end : 24 * 60;
}

const timed = computed(() =>
    entries.value.filter((entry) => entry.option.start && entry.option.end),
);

// The axis covers 07:00–23:00 and grows when an option lies outside, so every block stays visible
const hourStart = computed(() =>
    Math.min(
        HOUR_START,
        ...timed.value.map((entry) =>
            Math.floor(toMinutes(entry.option.start as string) / 60),
        ),
    ),
);
const hourEnd = computed(() =>
    Math.max(
        HOUR_END,
        ...timed.value.map((entry) => Math.ceil(endMinutes(entry.option) / 60)),
    ),
);

const hours = computed(() =>
    Array.from(
        { length: hourEnd.value - hourStart.value },
        (_, index) => hourStart.value + index,
    ),
);
const gridHeight = computed(
    () => (hourEnd.value - hourStart.value) * HOUR_HEIGHT,
);

type PlacedEntry = Entry & { lane: number; lanes: number };

// Overlapping slots share the day width side by side, like a calendar app
function placeDay(day: string): PlacedEntry[] {
    const minimum = (BLOCK_MIN_HEIGHT / HOUR_HEIGHT) * 60;
    const placed: PlacedEntry[] = [];
    let cluster: PlacedEntry[] = [];
    let laneEnds: number[] = [];
    let clusterEnd = -1;

    const closeCluster = () => {
        cluster.forEach((entry) => {
            entry.lanes = laneEnds.length;
        });
        cluster = [];
        laneEnds = [];
    };

    timed.value
        .filter((entry) => entry.option.date === day)
        .forEach((entry) => {
            const start = toMinutes(entry.option.start as string);
            const end = Math.max(endMinutes(entry.option), start + minimum);

            if (start >= clusterEnd) {
                closeCluster();
            }

            let lane = laneEnds.findIndex((laneEnd) => laneEnd <= start);

            if (lane === -1) {
                lane = laneEnds.length;
                laneEnds.push(end);
            } else {
                laneEnds[lane] = end;
            }

            const item = { ...entry, lane, lanes: 1 };
            cluster.push(item);
            placed.push(item);
            clusterEnd = Math.max(clusterEnd, end);
        });

    closeCluster();

    return placed;
}

const visibleDays = computed(() =>
    Array.from({ length: visibleDayCount.value }, (_, index) => {
        const date = addDays(weekStart.value, index);
        const value = toIso(date);

        return {
            value,
            weekday: shortDay(date),
            day: date.getDate(),
            entries: placeDay(value),
        };
    }),
);

const weekLabel = computed(() => {
    const days = visibleDays.value;
    const from = fromIso(days[0].value).toLocaleDateString(localeTag.value, {
        day: 'numeric',
        month: 'short',
    });
    const to = fromIso(days[days.length - 1].value).toLocaleDateString(
        localeTag.value,
        { day: 'numeric', month: 'short', year: 'numeric' },
    );

    return `${from} – ${to}`;
});

function isTight(entry: PlacedEntry): boolean {
    if (!props.showAnswers || width.value === 0) {
        return false;
    }

    const column = (width.value - 2 - HOUR_AXIS_WIDTH) / visibleDayCount.value;

    return column / entry.lanes - 6 < BLOCK_MIN_WIDTH;
}

const anyTight = computed(() =>
    visibleDays.value.some((day) => day.entries.some(isTight)),
);

/* Day panel for compact cells and blocks */

const usesPanel = computed(() =>
    props.mode === 'day' ? compactMonth.value : anyTight.value,
);

const visibleEntries = computed<Entry[]>(() =>
    props.mode === 'day'
        ? weeks.value.flat().flatMap((cell) => cell.entries)
        : visibleDays.value.flatMap((day) => day.entries),
);

const pickedDay = ref<string | null>(null);

// A pick outside the visible month or week falls back to the first open option there, then today
const selectedDay = computed(() => {
    const visible = visibleEntries.value;

    if (
        pickedDay.value &&
        visible.some((entry) => entry.option.date === pickedDay.value)
    ) {
        return pickedDay.value;
    }

    const open = visible.find((entry) => !entry.answer) ?? visible[0];

    return open?.option.date ?? toIso(new Date());
});

const selectedEntries = computed(() =>
    entries.value.filter((entry) => entry.option.date === selectedDay.value),
);

const panelId = computed(() => `${props.idPrefix}-calendar-day-panel`);

function selectDay(day: string): void {
    pickedDay.value = day;
}

function shiftWeek(delta: number): void {
    weekAnchor.value = addDays(weekStart.value, delta * visibleDayCount.value);
}

function blockStyle(entry: PlacedEntry): Record<string, string> {
    const start = entry.option.start as string;

    return {
        ...slotStyle(
            start,
            toTime(endMinutes(entry.option)),
            hourStart.value,
            BLOCK_MIN_HEIGHT,
        ),
        left: `calc(${(entry.lane / entry.lanes) * 100}% + 3px)`,
        width: `calc(${100 / entry.lanes}% - 6px)`,
    };
}

function name(entry: Entry): string {
    return `${props.idPrefix}-calendar-answer-${entry.key}`;
}
</script>

<template>
    <div
        ref="root"
        class="flex w-full min-w-0 flex-col gap-2"
        data-feature="F-06"
    >
        <div class="flex items-center justify-between gap-2">
            <Button
                type="button"
                variant="outline"
                size="icon"
                :aria-label="t('cal.prev')"
                @click="mode === 'day' ? shiftMonth(-1) : shiftWeek(-1)"
            >
                <ChevronLeft />
            </Button>
            <span
                class="font-display flex-1 text-center text-base font-[640]"
                aria-live="polite"
                >{{ mode === 'day' ? monthLabel : weekLabel }}</span
            >
            <Button
                type="button"
                variant="outline"
                size="icon"
                :aria-label="t('cal.next')"
                @click="mode === 'day' ? shiftMonth(1) : shiftWeek(1)"
            >
                <ChevronRight />
            </Button>
        </div>

        <div
            v-if="mode === 'day'"
            role="group"
            :aria-label="monthLabel"
            class="flex flex-col gap-1"
        >
            <div class="grid grid-cols-7 gap-1" aria-hidden="true">
                <span
                    v-for="label in weekdayLabels"
                    :key="label"
                    class="text-fg-soft py-1 text-center text-xs font-semibold"
                    >{{ label }}</span
                >
            </div>
            <template v-if="compactMonth">
                <div
                    v-for="(week, row) in weeks"
                    :key="row"
                    class="grid grid-cols-7 gap-1"
                >
                    <template v-for="cell in week" :key="cell.value">
                        <button
                            v-if="cell.entries.length"
                            type="button"
                            :aria-label="cellLabel(cell)"
                            :aria-pressed="selectedDay === cell.value"
                            :aria-controls="panelId"
                            :class="
                                cn(
                                    'border-border bg-surface-raised text-fg hover:border-border-strong focus-visible:outline-focus-ring flex min-h-14 min-w-0 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-lg border p-0.5 text-xs focus-visible:outline-2 focus-visible:outline-offset-2',
                                    cell.entries.some(
                                        (entry) => entry.option.fixed,
                                    ) && 'shadow-[0_0_0_2px_var(--primary)]',
                                    selectedDay === cell.value &&
                                        'border-fg ring-fg ring-1',
                                )
                            "
                            @click="selectDay(cell.value)"
                        >
                            <span
                                aria-hidden="true"
                                class="font-semibold tabular-nums"
                                >{{ cell.day }}</span
                            >
                            <span
                                aria-hidden="true"
                                class="text-fg-muted inline-flex items-center gap-1 text-[11px] font-semibold tabular-nums"
                            >
                                <AnswerDot
                                    v-if="showAnswers"
                                    :answer="cellAnswer(cell)"
                                />
                                {{ cellYes(cell) }}
                            </span>
                        </button>
                        <span
                            v-else
                            aria-hidden="true"
                            :class="
                                cn(
                                    'text-fg-soft grid min-h-14 place-items-center text-xs tabular-nums',
                                    cell.outside && 'opacity-50',
                                )
                            "
                            >{{ cell.day }}</span
                        >
                    </template>
                </div>
                <ul
                    v-if="showAnswers"
                    class="text-fg-muted flex flex-wrap gap-x-3 gap-y-1 pt-1 text-xs"
                >
                    <li
                        v-for="item in legend"
                        :key="item ?? 'none'"
                        class="inline-flex items-center gap-1"
                    >
                        <AnswerDot :answer="item" />
                        {{ t(`poll.answer.${item ?? 'none'}`) }}
                    </li>
                </ul>
            </template>
            <template v-else>
                <div
                    v-for="(week, row) in weeks"
                    :key="row"
                    class="grid grid-cols-7 gap-1"
                >
                    <div
                        v-for="cell in week"
                        :key="cell.value"
                        :class="
                            cn(
                                'border-border bg-surface-raised text-fg flex min-h-16 min-w-0 flex-col gap-1 rounded-lg border p-1 sm:min-h-20',
                                cell.outside &&
                                    'text-fg-soft bg-surface-muted/40 border-transparent',
                                cell.entries.some(
                                    (entry) => entry.option.fixed,
                                ) && 'shadow-[0_0_0_2px_var(--primary)]',
                            )
                        "
                    >
                        <span
                            v-if="!cell.entries.length"
                            aria-hidden="true"
                            class="px-0.5 text-xs font-semibold tabular-nums"
                            >{{ cell.day }}</span
                        >
                        <CalendarOption
                            v-for="(entry, index) in cell.entries"
                            :key="entry.key"
                            variant="day"
                            :day="index === 0 ? cell.day : null"
                            :option="entry.option"
                            :answer="entry.answer"
                            :yes="entry.yes"
                            :show-answer="showAnswers"
                            :editable="showAnswers && !closed"
                            :closed="closed"
                            :busy="busy"
                            :name="name(entry)"
                            :note="entry.note"
                            :other-notes="entry.otherNotes"
                            @answer="answer(entry, $event)"
                            @edit-note="emit('editNote', entry.option.id)"
                        />
                    </div>
                </div>
            </template>
        </div>

        <div
            v-else
            role="group"
            :aria-label="weekLabel"
            class="border-border bg-surface-raised flex flex-col overflow-hidden rounded-xl border"
            :style="{ '--days': visibleDays.length }"
        >
            <div
                class="border-border bg-surface-muted grid grid-cols-[52px_repeat(var(--days),minmax(0,1fr))] border-b"
                aria-hidden="true"
            >
                <span />
                <span
                    v-for="day in visibleDays"
                    :key="day.value"
                    class="text-fg-muted flex flex-col items-center gap-0.5 px-1 py-2 text-xs"
                >
                    <span>{{ day.weekday }}</span>
                    <span
                        class="font-display text-fg grid size-7 place-items-center rounded-full text-[17px] leading-none font-bold"
                    >
                        {{ day.day }}
                    </span>
                </span>
            </div>
            <div
                class="relative grid grid-cols-[52px_repeat(var(--days),minmax(0,1fr))]"
                :style="{ height: `${gridHeight}px` }"
            >
                <div
                    class="border-border flex flex-col border-r"
                    aria-hidden="true"
                >
                    <span
                        v-for="hour in hours"
                        :key="hour"
                        class="text-fg-soft -translate-y-[7px] pt-0.5 pr-1.5 text-right text-[11px] tabular-nums"
                        :style="{ height: `${HOUR_HEIGHT}px` }"
                    >
                        {{ hourLabel(hour) }}
                    </span>
                </div>
                <div
                    v-for="day in visibleDays"
                    :key="day.value"
                    class="border-border relative border-r bg-[linear-gradient(to_bottom,var(--border)_0_1px,transparent_1px_100%)] bg-repeat-y last:border-r-0"
                    :style="{ backgroundSize: `100% ${HOUR_HEIGHT}px` }"
                >
                    <CalendarOption
                        v-for="entry in day.entries"
                        :key="entry.key"
                        :option="entry.option"
                        :answer="entry.answer"
                        :yes="entry.yes"
                        :show-answer="showAnswers"
                        :editable="showAnswers && !closed"
                        :closed="closed"
                        :busy="busy"
                        :name="name(entry)"
                        :compact="isTight(entry)"
                        :selected="isTight(entry) && selectedDay === day.value"
                        class="absolute z-1 shadow-sm focus-within:z-2 hover:z-2"
                        :style="blockStyle(entry)"
                        :note="entry.note"
                        :other-notes="entry.otherNotes"
                        @answer="answer(entry, $event)"
                        @select="selectDay(day.value)"
                        @edit-note="emit('editNote', entry.option.id)"
                    />
                </div>
            </div>
        </div>

        <CalendarDayPanel
            v-if="usesPanel && selectedEntries.length"
            :id="panelId"
            :day="selectedDay"
            :entries="selectedEntries"
            :show-answers="showAnswers"
            :closed="closed"
            :busy="busy"
            :id-prefix="idPrefix"
            @answer="(optionId, value) => emit('answer', optionId, value)"
        >
            <template #note="{ option }">
                <NoteButton
                    v-if="showAnswers && !closed && answerOf(option.id)"
                    :note="noteOf(option.id)"
                    :option-label="describeOption(option, locale)"
                    :subtle="false"
                    @open="emit('editNote', option.id)"
                />
            </template>
            <template #notes="{ option }">
                <ul
                    v-if="panelNotes(option.id).length"
                    class="text-fg-muted flex flex-col gap-0.5 text-sm"
                    data-feature="F-06"
                >
                    <li
                        v-for="(entry, index) in panelNotes(option.id)"
                        :key="index"
                        class="break-words"
                    >
                        {{
                            t('poll.note.marker', {
                                name: entry.name,
                                note: entry.note,
                            })
                        }}
                    </li>
                </ul>
            </template>
        </CalendarDayPanel>
    </div>
</template>
