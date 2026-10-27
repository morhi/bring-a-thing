<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref } from 'vue';
import CalendarOption from '@/components/app/poll/calendar-option.vue';
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
        bestOptionIds?: PollKey[];
        // False when the current person is not asked, so no own answer exists
        showAnswers?: boolean;
        closed?: boolean;
        busy?: boolean;
        idPrefix?: string;
    }>(),
    {
        bestOptionIds: () => [],
        showAnswers: true,
        closed: false,
        busy: false,
        idPrefix: 'poll',
    },
);

const emit = defineEmits<{ answer: [optionId: PollKey, answer: PollAnswer] }>();

const { t, locale } = useI18n();

// Tall enough for the time line and the answer row of a block
const BLOCK_MIN_HEIGHT = 48;

const localeTag = computed(() => dateLocale(locale.value));
const narrow = useMediaQuery('(max-width: 719px)');
const bestIds = computed(() => new Set(props.bestOptionIds.map(String)));

// Opens where the poll starts; a poll without options falls back to the current month
const anchor = fromIso(props.options[0]?.date ?? toIso(new Date()));
const monthCursor = ref(new Date(anchor.getFullYear(), anchor.getMonth(), 1));
const weekAnchor = ref(anchor);

type Entry = {
    option: PollGridOption;
    key: string;
    answer: PollAnswer | null;
    yes: number;
    best: boolean;
};

const entries = computed<Entry[]>(() =>
    props.options.map((option) => {
        const key = String(option.id);

        return {
            option,
            key,
            answer: props.answers[key] ?? null,
            yes: props.totals[key]?.yes ?? 0,
            best: !option.fixed && bestIds.value.has(key),
        };
    }),
);

function entriesOn(day: string): Entry[] {
    return entries.value.filter((entry) => entry.option.date === day);
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
    <div class="flex w-full min-w-0 flex-col gap-2" data-feature="F-06">
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
                            'border-border bg-surface-muted text-fg flex min-h-16 min-w-0 flex-col gap-1 rounded-lg border p-1 sm:min-h-20',
                            cell.outside &&
                                'text-fg-soft bg-surface-muted/40 border-transparent',
                        )
                    "
                >
                    <span
                        aria-hidden="true"
                        class="px-0.5 text-xs font-semibold tabular-nums"
                        >{{ cell.day }}</span
                    >
                    <CalendarOption
                        v-for="entry in cell.entries"
                        :key="entry.key"
                        :option="entry.option"
                        :answer="entry.answer"
                        :yes="entry.yes"
                        :best="entry.best"
                        :show-answer="showAnswers"
                        :editable="showAnswers && !closed"
                        :busy="busy"
                        :name="name(entry)"
                        :compact="narrow"
                        @answer="answer(entry, $event)"
                    />
                </div>
            </div>
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
                        :best="entry.best"
                        :show-answer="showAnswers"
                        :editable="showAnswers && !closed"
                        :busy="busy"
                        :name="name(entry)"
                        :compact="narrow && entry.lanes > 1"
                        class="absolute z-1 shadow-sm focus-within:z-2 hover:z-2"
                        :style="blockStyle(entry)"
                        @answer="answer(entry, $event)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
