<script setup lang="ts">
import { ChevronLeft, ChevronRight, Lock, Plus, X } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref } from 'vue';
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
    OptionDraft,
    SlotTime,
} from '@/components/app/poll/types';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        mode: OptionCalendarMode;
        modelValue: OptionDraft[];
        // Server date as YYYY-MM-DD; earlier days cannot take options
        today?: string;
        // Time a slot gets when added by keyboard and no other slot exists yet
        defaultSlot?: SlotTime | null;
        disabled?: boolean;
    }>(),
    { today: () => toIso(new Date()), defaultSlot: null, disabled: false },
);

const emit = defineEmits<{ 'update:modelValue': [options: OptionDraft[]] }>();

const { t, locale } = useI18n();

// Visible window of the week grid; a drawing surface, not a domain limit
const HOUR_START = 7;
const HOUR_END = 23;
const HOUR_HEIGHT = 44;
const STEP = 15;
const MIN_SLOT = 30;
const HOURS_COLUMN = 52;

const localeTag = computed(() => dateLocale(locale.value));

// Prefilled options open the calendar where they are, otherwise it starts today
const firstUpcoming = props.modelValue
    .map((option) => option.date)
    .filter((date) => date && date >= props.today)
    .sort()[0];
const anchor = fromIso(firstUpcoming ?? props.today);
const monthCursor = ref(new Date(anchor.getFullYear(), anchor.getMonth(), 1));
// The week window starts on the anchor day: past days cannot take options, so a Monday start wastes columns
const weekCursor = ref(
    new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate()),
);
const narrow = useMediaQuery('(max-width: 719px)');
const visibleDayCount = computed(() => (narrow.value ? 3 : 7));

function sortOptions(options: OptionDraft[]): OptionDraft[] {
    return [...options].sort(
        (a, b) =>
            a.date.localeCompare(b.date) ||
            (a.start ?? '').localeCompare(b.start ?? ''),
    );
}

function isFrozen(option: OptionDraft): boolean {
    return !!option.locked || !!option.fixed;
}

function shortDay(date: Date): string {
    return date
        .toLocaleDateString(localeTag.value, { weekday: 'short' })
        .replace('.', '');
}

/* Day mode */

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

const monthCells = computed(() => {
    const first = monthCursor.value;
    const start = mondayOf(first);

    return Array.from({ length: 42 }, (_, index) => {
        const date = addDays(start, index);
        const value = toIso(date);
        const option = props.modelValue.find((entry) => entry.date === value);
        const locked = !!option && isFrozen(option);
        const spoken = [
            describeOption(
                {
                    date: value,
                    start: null,
                    end: null,
                    label: option?.label || null,
                },
                locale.value,
            ),
        ];

        if (locked) {
            spoken.push(
                option?.fixed ? t('group.polls.fixed') : t('cal.locked'),
            );
        }

        return {
            value,
            day: date.getDate(),
            outside: date.getMonth() !== first.getMonth(),
            past: value < props.today,
            today: value === props.today,
            selected: !!option,
            locked,
            label: option?.label ?? '',
            spoken: spoken.join(', '),
        };
    });
});

type MonthCell = (typeof monthCells.value)[number];

function shiftMonth(delta: number): void {
    monthCursor.value = new Date(
        monthCursor.value.getFullYear(),
        monthCursor.value.getMonth() + delta,
        1,
    );
}

function canToggle(value: string): boolean {
    const option = props.modelValue.find((entry) => entry.date === value);

    return (
        !props.disabled && value >= props.today && !(option && isFrozen(option))
    );
}

function setDay(
    value: string,
    selected: boolean,
    options = props.modelValue,
): OptionDraft[] {
    const has = options.some((option) => option.date === value);

    if (!canToggle(value)) {
        return options;
    }

    if (selected && !has) {
        return sortOptions([
            ...options,
            { date: value, start: null, end: null, label: '' },
        ]);
    }

    if (!selected && has) {
        return options.filter((option) => option.date !== value);
    }

    return options;
}

let paint: boolean | null = null;
let lastPainted: string | null = null;

function dayPointerDown(cell: MonthCell, event: PointerEvent): void {
    if (event.button !== 0 || !canToggle(cell.value)) {
        return;
    }

    paint = !cell.selected;
    lastPainted = cell.value;
    emit('update:modelValue', setDay(cell.value, paint));
    (event.currentTarget as HTMLElement)
        .closest<HTMLElement>('[data-month]')
        ?.setPointerCapture(event.pointerId);
}

// Fill every day between the last painted one and the current one, so a fast drag
// selects a range instead of only the cells the pointer happened to cross
function dayPointerMove(event: PointerEvent): void {
    if (paint === null || lastPainted === null) {
        return;
    }

    const target = document.elementFromPoint(event.clientX, event.clientY);
    const button = target?.closest<HTMLButtonElement>('[data-day]');

    if (!button || button.disabled || button.dataset.day === lastPainted) {
        return;
    }

    const toValue = button.dataset.day as string;
    const step = toValue >= lastPainted ? 1 : -1;
    let cursor = fromIso(lastPainted);
    let options = props.modelValue;

    while (toIso(cursor) !== toValue) {
        cursor = addDays(cursor, step);
        options = setDay(toIso(cursor), paint, options);
    }

    lastPainted = toValue;
    emit('update:modelValue', options);
}

function dayPointerUp(): void {
    paint = null;
    lastPainted = null;
}

function dayKey(cell: MonthCell, event: KeyboardEvent): void {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }

    event.preventDefault();
    emit('update:modelValue', setDay(cell.value, !cell.selected));
}

/* Slot mode */

const visibleDays = computed(() =>
    Array.from({ length: visibleDayCount.value }, (_, index) => {
        const date = addDays(weekCursor.value, index);
        const value = toIso(date);

        return {
            value,
            past: value < props.today,
            today: value === props.today,
            weekday: shortDay(date),
            day: date.getDate(),
            spoken: describeOption(
                { date: value, start: null, end: null, label: null },
                locale.value,
            ),
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

const hours = Array.from(
    { length: HOUR_END - HOUR_START },
    (_, index) => HOUR_START + index,
);
const gridHeight = (HOUR_END - HOUR_START) * HOUR_HEIGHT;

function shiftWeek(delta: number): void {
    weekCursor.value = addDays(weekCursor.value, delta * visibleDayCount.value);
}

function slotStyle(start: string, end: string): Record<string, string> {
    const top = ((toMinutes(start) - HOUR_START * 60) / 60) * HOUR_HEIGHT;
    const height = ((toMinutes(end) - toMinutes(start)) / 60) * HOUR_HEIGHT;

    return { top: `${top}px`, height: `${Math.max(height, 18)}px` };
}

function slotsFor(day: string): { option: OptionDraft; index: number }[] {
    return props.modelValue
        .map((option, index) => ({ option, index }))
        .filter(
            ({ option }) => option.date === day && option.start && option.end,
        );
}

function addSlot(day: string): void {
    const last = [...props.modelValue]
        .reverse()
        .find((option) => option.start && option.end);
    const time = last
        ? { start: last.start as string, end: last.end as string }
        : props.defaultSlot;

    if (props.disabled || !time) {
        return;
    }

    emit(
        'update:modelValue',
        sortOptions([
            ...props.modelValue,
            { date: day, start: time.start, end: time.end, label: '' },
        ]),
    );
}

function removeAt(index: number): void {
    const option = props.modelValue[index];

    if (props.disabled || !option || isFrozen(option)) {
        return;
    }

    emit(
        'update:modelValue',
        props.modelValue.filter((_, position) => position !== index),
    );
}

// One gesture at a time on the week grid: draw a new slot on empty space, move a slot by
// its body (vertically and across days), or resize it by its top or bottom edge
type Gesture =
    | { kind: 'draw'; body: HTMLElement; anchor: number; day: string }
    | {
          kind: 'move' | 'start' | 'end';
          index: number;
          body: HTMLElement;
          startY: number;
          origin: { start: number; end: number; date: string };
      };

const draft = ref<{ date: string; start: string; end: string } | null>(null);
let gesture: Gesture | null = null;

function minuteAtY(body: HTMLElement, clientY: number): number {
    const rect = body.getBoundingClientRect();
    const raw = ((clientY - rect.top) / HOUR_HEIGHT) * 60 + HOUR_START * 60;
    const snapped = Math.round(raw / STEP) * STEP;

    return Math.min(Math.max(snapped, HOUR_START * 60), HOUR_END * 60);
}

function dayAtX(body: HTMLElement, clientX: number) {
    const rect = body.getBoundingClientRect();
    const width = (rect.width - HOURS_COLUMN) / visibleDays.value.length;
    const index = Math.floor((clientX - rect.left - HOURS_COLUMN) / width);

    return visibleDays.value[
        Math.min(Math.max(index, 0), visibleDays.value.length - 1)
    ];
}

function gridPointerDown(event: PointerEvent): void {
    const target = event.target as HTMLElement;

    if (
        props.disabled ||
        event.button !== 0 ||
        target.closest('[data-slot-remove]')
    ) {
        return;
    }

    const body = event.currentTarget as HTMLElement;
    const slot = target.closest<HTMLElement>('[data-index]');
    const column = target.closest<HTMLElement>('[data-column]');

    if (slot) {
        const index = Number(slot.dataset.index);
        const option = props.modelValue[index];

        if (!option?.start || !option.end || isFrozen(option)) {
            return;
        }

        const handle = target.closest<HTMLElement>('[data-handle]')?.dataset
            .handle as 'start' | 'end' | undefined;
        gesture = {
            kind: handle ?? 'move',
            index,
            body,
            startY: event.clientY,
            origin: {
                start: toMinutes(option.start),
                end: toMinutes(option.end),
                date: option.date,
            },
        };
        body.setPointerCapture(event.pointerId);
        event.preventDefault();

        return;
    }

    if (!column || column.dataset.past === 'true') {
        return;
    }

    const start = minuteAtY(body, event.clientY);
    const day = column.dataset.column as string;
    gesture = { kind: 'draw', body, anchor: start, day };
    draft.value = {
        date: day,
        start: toTime(start),
        end: toTime(Math.min(start + MIN_SLOT, HOUR_END * 60)),
    };
    body.setPointerCapture(event.pointerId);
    event.preventDefault();
}

function gridPointerMove(event: PointerEvent): void {
    if (!gesture) {
        return;
    }

    if (gesture.kind === 'draw') {
        const current = minuteAtY(gesture.body, event.clientY);
        let start = Math.min(gesture.anchor, current);
        let end = Math.max(gesture.anchor, current);

        if (end - start < MIN_SLOT) {
            end = Math.min(start + MIN_SLOT, HOUR_END * 60);
            start = end - MIN_SLOT;
        }

        draft.value = {
            date: gesture.day,
            start: toTime(start),
            end: toTime(end),
        };

        return;
    }

    const delta =
        Math.round(
            (((event.clientY - gesture.startY) / HOUR_HEIGHT) * 60) / STEP,
        ) * STEP;
    const { start, end, date } = gesture.origin;
    const next = { start, end, date };

    if (gesture.kind === 'move') {
        const duration = end - start;
        next.start = Math.min(
            Math.max(start + delta, HOUR_START * 60),
            HOUR_END * 60 - duration,
        );
        next.end = next.start + duration;
        const day = dayAtX(gesture.body, event.clientX);

        if (day && !day.past) {
            next.date = day.value;
        }
    }

    if (gesture.kind === 'start') {
        next.start = Math.min(
            Math.max(start + delta, HOUR_START * 60),
            end - MIN_SLOT,
        );
    }

    if (gesture.kind === 'end') {
        next.end = Math.max(
            Math.min(end + delta, HOUR_END * 60),
            start + MIN_SLOT,
        );
    }

    const current = props.modelValue[gesture.index];

    if (
        !current ||
        (current.date === next.date &&
            current.start === toTime(next.start) &&
            current.end === toTime(next.end))
    ) {
        return;
    }

    const index = gesture.index;
    emit(
        'update:modelValue',
        props.modelValue.map((option, position) =>
            position === index
                ? {
                      ...option,
                      date: next.date,
                      start: toTime(next.start),
                      end: toTime(next.end),
                  }
                : option,
        ),
    );
}

function gridPointerUp(): void {
    if (!gesture) {
        return;
    }

    if (gesture.kind === 'draw' && draft.value) {
        emit(
            'update:modelValue',
            sortOptions([...props.modelValue, { ...draft.value, label: '' }]),
        );
        draft.value = null;
    }

    // Moving keeps array positions stable during the drag; sort once it ends
    if (gesture.kind !== 'draw') {
        emit('update:modelValue', sortOptions(props.modelValue));
    }

    gesture = null;
}

function hourLabel(hour: number): string {
    return `${String(hour).padStart(2, '0')}:00`;
}
</script>

<template>
    <div class="flex w-full min-w-0 flex-col gap-2" data-feature="F-06">
        <div class="flex items-center justify-between gap-2">
            <Button
                variant="ghost"
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
                variant="ghost"
                size="icon"
                :aria-label="t('cal.next')"
                @click="mode === 'day' ? shiftMonth(1) : shiftWeek(1)"
            >
                <ChevronRight />
            </Button>
        </div>

        <div
            v-if="mode === 'day'"
            data-month
            role="group"
            :aria-label="monthLabel"
            class="flex touch-none flex-col gap-1 select-none"
            @pointermove="dayPointerMove"
            @pointerup="dayPointerUp"
            @pointercancel="dayPointerUp"
        >
            <div class="grid grid-cols-7 gap-1" aria-hidden="true">
                <span
                    v-for="label in weekdayLabels"
                    :key="label"
                    class="text-fg-soft py-1 text-center text-xs font-semibold"
                    >{{ label }}</span
                >
            </div>
            <div class="grid grid-cols-7 gap-1">
                <button
                    v-for="cell in monthCells"
                    :key="cell.value"
                    type="button"
                    :data-day="cell.value"
                    :disabled="cell.past || disabled"
                    :aria-pressed="cell.selected"
                    :aria-disabled="cell.locked || undefined"
                    :aria-label="cell.spoken"
                    :title="cell.label || undefined"
                    :class="
                        cn(
                            'bg-surface-muted text-fg relative grid aspect-[1.7] max-h-[52px] min-h-9 place-items-center rounded-lg border border-transparent p-0 font-semibold tabular-nums',
                            'enabled:hover:border-border-strong disabled:text-fg-soft transition-colors duration-(--duration-fast) disabled:cursor-not-allowed disabled:bg-transparent disabled:opacity-60',
                            cell.outside &&
                                'text-fg-soft bg-transparent font-medium',
                            cell.today &&
                                'shadow-[inset_0_0_0_1.5px_var(--border-strong)]',
                            cell.selected &&
                                'border-primary bg-primary text-primary-fg enabled:hover:border-primary-hover enabled:hover:bg-primary-hover',
                            cell.locked && 'cursor-not-allowed',
                        )
                    "
                    @pointerdown.prevent="dayPointerDown(cell, $event)"
                    @keydown="dayKey(cell, $event)"
                >
                    <span>{{ cell.day }}</span>
                    <Lock
                        v-if="cell.locked"
                        class="absolute top-1 right-1 size-2.5"
                        aria-hidden="true"
                    />
                    <span
                        v-if="cell.label"
                        class="absolute bottom-1 left-1/2 size-1 -translate-x-1/2 rounded-full bg-current"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </div>

        <div
            v-else
            role="group"
            :aria-label="weekLabel"
            class="border-border bg-surface-raised flex flex-col overflow-hidden rounded-xl border select-none"
            :style="{ '--days': visibleDays.length }"
        >
            <div
                class="border-border bg-surface-muted grid grid-cols-[52px_repeat(var(--days),minmax(0,1fr))] border-b"
            >
                <span aria-hidden="true" />
                <span
                    v-for="day in visibleDays"
                    :key="day.value"
                    :class="
                        cn(
                            'text-fg-muted flex flex-col items-center gap-0.5 px-1 py-2 text-xs',
                            day.past && 'opacity-50',
                        )
                    "
                >
                    <span aria-hidden="true">{{ day.weekday }}</span>
                    <span
                        aria-hidden="true"
                        :class="
                            cn(
                                'font-display text-fg grid size-7 place-items-center rounded-full text-[17px] leading-none font-bold',
                                day.today && 'bg-primary text-primary-fg',
                            )
                        "
                    >
                        {{ day.day }}
                    </span>
                    <!-- Keyboard path for slots: drawing needs a pointer, this button does not -->
                    <Button
                        v-if="!day.past && !disabled"
                        variant="ghost"
                        size="icon-xs"
                        :aria-label="t('cal.addSlot', { date: day.spoken })"
                        :title="t('cal.addSlot', { date: day.spoken })"
                        @click="addSlot(day.value)"
                    >
                        <Plus />
                    </Button>
                </span>
            </div>
            <div class="max-h-[360px] overflow-y-auto overscroll-contain">
                <div
                    class="relative grid touch-none grid-cols-[52px_repeat(var(--days),minmax(0,1fr))]"
                    :style="{ height: `${gridHeight}px` }"
                    @pointerdown="gridPointerDown"
                    @pointermove="gridPointerMove"
                    @pointerup="gridPointerUp"
                    @pointercancel="gridPointerUp"
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
                        :data-column="day.value"
                        :data-past="day.past"
                        :class="
                            cn(
                                'border-border relative border-r bg-[linear-gradient(to_bottom,var(--border)_0_1px,transparent_1px_100%)] bg-repeat-y last:border-r-0',
                                day.past || disabled
                                    ? 'bg-surface-sunken/60 cursor-not-allowed'
                                    : 'cursor-crosshair',
                            )
                        "
                        :style="{ backgroundSize: `100% ${HOUR_HEIGHT}px` }"
                    >
                        <div
                            v-for="entry in slotsFor(day.value)"
                            :key="entry.index"
                            :data-index="entry.index"
                            :title="
                                entry.option.label ||
                                (isFrozen(entry.option)
                                    ? t('cal.locked')
                                    : t('cal.slotTitle'))
                            "
                            :style="
                                slotStyle(
                                    entry.option.start as string,
                                    entry.option.end as string,
                                )
                            "
                            :class="
                                cn(
                                    'group border-primary bg-primary-soft text-primary-soft-fg absolute inset-x-[3px] flex flex-col gap-px overflow-hidden rounded-lg border border-l-4 px-1.5 py-[3px] text-[11px] leading-tight',
                                    isFrozen(entry.option) || disabled
                                        ? 'cursor-default border-dashed'
                                        : 'cursor-grab active:cursor-grabbing active:shadow-md',
                                )
                            "
                        >
                            <template
                                v-if="!isFrozen(entry.option) && !disabled"
                            >
                                <span
                                    data-handle="start"
                                    class="absolute inset-x-0 top-0 h-[7px] cursor-ns-resize"
                                    aria-hidden="true"
                                />
                                <span
                                    data-handle="end"
                                    class="absolute inset-x-0 bottom-0 h-[7px] cursor-ns-resize"
                                    aria-hidden="true"
                                />
                            </template>
                            <span
                                class="flex items-center gap-1 truncate font-bold tabular-nums"
                            >
                                <Lock
                                    v-if="isFrozen(entry.option)"
                                    class="size-2.5 flex-none"
                                    aria-hidden="true"
                                />
                                {{ entry.option.start }}–{{ entry.option.end }}
                            </span>
                            <span v-if="entry.option.label" class="truncate">{{
                                entry.option.label
                            }}</span>
                            <button
                                v-if="!isFrozen(entry.option) && !disabled"
                                type="button"
                                data-slot-remove
                                class="bg-primary text-primary-fg absolute top-0.5 right-0.5 grid size-[18px] place-items-center rounded-[5px] p-0 opacity-0 transition-opacity duration-(--duration-fast) group-hover:opacity-100 focus-visible:opacity-100"
                                :aria-label="`${t('newPoll.removeOption')}: ${describeOption(entry.option, locale)}`"
                                @click="removeAt(entry.index)"
                            >
                                <X class="size-[11px]" aria-hidden="true" />
                            </button>
                        </div>
                        <div
                            v-if="draft && draft.date === day.value"
                            class="border-primary bg-primary/20 text-primary-soft-fg pointer-events-none absolute inset-x-[3px] flex flex-col overflow-hidden rounded-lg border border-l-4 border-dashed px-1.5 py-[3px] text-[11px] leading-tight"
                            :style="slotStyle(draft.start, draft.end)"
                        >
                            <span class="truncate font-bold tabular-nums"
                                >{{ draft.start }}–{{ draft.end }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-fg-muted text-sm">
            {{ mode === 'day' ? t('cal.hintDay') : t('cal.hintSlots') }}
        </p>
    </div>
</template>
