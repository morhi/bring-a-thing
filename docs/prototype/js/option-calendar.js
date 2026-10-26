// Calendar editor for poll options (F-06, F-07). Day mode: month grid, click or drag to select days.
// Slot mode: week grid, drag vertically to draw a time slot. The row editor below stays the
// keyboard path; the calendar is the fast path. Both edit the same options array.

import { ref, computed, onMounted, onBeforeUnmount } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui } from './core.js';
import { t, TODAY } from './i18n.js';

const HOUR_START = 7;
const HOUR_END = 23;
const HOUR_HEIGHT = 44;
const STEP = 15;
const MIN_SLOT = 30;

function iso(date) {
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${date.getFullYear()}-${month}-${day}`;
}

function fromIso(value) {
  const [year, month, day] = value.split('-').map(Number);

  return new Date(year, month - 1, day);
}

function addDays(date, days) {
  const next = new Date(date);
  next.setDate(next.getDate() + days);

  return next;
}

function mondayOf(date) {
  const offset = (date.getDay() + 6) % 7;

  return addDays(new Date(date.getFullYear(), date.getMonth(), date.getDate()), -offset);
}

function toMinutes(time) {
  const [hours, minutes] = time.split(':').map(Number);

  return hours * 60 + minutes;
}

function toTime(minutes) {
  return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

function locale() {
  return ui.lang === 'de' ? 'de-CH' : 'en-GB';
}

const todayIso = iso(TODAY);

export const OptionCalendar = {
  props: {
    mode: { type: String, required: true },
    modelValue: { type: Array, required: true }
  },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    // edit-mode: prefilled options open the calendar where they are, otherwise it starts today.
    const firstUpcoming = props.modelValue.map((option) => option.date).filter((date) => date && date >= todayIso).sort()[0];
    const anchor = firstUpcoming ? fromIso(firstUpcoming) : TODAY;
    const monthCursor = ref(new Date(anchor.getFullYear(), anchor.getMonth(), 1));
    // The window starts on the anchor day: past days cannot take options, so a Monday start would waste columns.
    const weekCursor = ref(new Date(anchor.getFullYear(), anchor.getMonth(), anchor.getDate()));
    const narrow = ref(false);
    const media = window.matchMedia('(max-width: 719px)');
    const onMedia = () => { narrow.value = media.matches; };

    onMounted(() => {
      onMedia();
      media.addEventListener('change', onMedia);
    });
    onBeforeUnmount(() => media.removeEventListener('change', onMedia));

    const visibleDayCount = computed(() => (narrow.value ? 3 : 7));

    // Day mode

    const weekdayLabels = computed(() => {
      const monday = mondayOf(new Date(2026, 0, 5));

      return Array.from({ length: 7 }, (_, index) => addDays(monday, index).toLocaleDateString(locale(), { weekday: 'short' }).replace('.', ''));
    });

    const monthLabel = computed(() => monthCursor.value.toLocaleDateString(locale(), { month: 'long', year: 'numeric' }));

    const monthCells = computed(() => {
      const first = monthCursor.value;
      const start = mondayOf(first);
      const cells = [];

      for (let index = 0; index < 42; index += 1) {
        const date = addDays(start, index);
        const value = iso(date);
        const option = props.modelValue.find((entry) => entry.date === value);
        cells.push({
          value,
          day: date.getDate(),
          outside: date.getMonth() !== first.getMonth(),
          past: value < todayIso,
          today: value === todayIso,
          selected: !!option,
          label: option && option.label ? option.label : '' // poll-rules
        });
      }

      return cells;
    });

    function shiftMonth(delta) {
      monthCursor.value = new Date(monthCursor.value.getFullYear(), monthCursor.value.getMonth() + delta, 1);
    }

    let paint = null;
    let lastPainted = null;

    function setDay(value, selected) {
      const has = props.modelValue.some((option) => option.date === value);

      if (selected && !has) {
        emit('update:modelValue', [...props.modelValue, { date: value, start: null, end: null, label: '' }].sort((a, b) => a.date.localeCompare(b.date)));
      }

      if (!selected && has) {
        emit('update:modelValue', props.modelValue.filter((option) => option.date !== value));
      }
    }

    function dayPointerDown(cell, event) {
      if (cell.past) {
        return;
      }

      paint = !cell.selected;
      lastPainted = cell.value;
      setDay(cell.value, paint);
      event.currentTarget.closest('.cal-month').setPointerCapture(event.pointerId);
    }

    // Fill every day between the last painted one and the current one, so a fast drag
    // selects a range instead of only the cells the pointer happened to cross.
    function paintRange(toValue) {
      const from = fromIso(lastPainted);
      const to = fromIso(toValue);
      const step = to >= from ? 1 : -1;
      let cursor = from;

      while (iso(cursor) !== toValue) {
        cursor = addDays(cursor, step);

        if (iso(cursor) >= todayIso) {
          setDay(iso(cursor), paint);
        }
      }

      lastPainted = toValue;
    }

    function dayPointerMove(event) {
      if (paint === null) {
        return;
      }

      const target = document.elementFromPoint(event.clientX, event.clientY);
      const button = target && target.closest('[data-day]');

      if (!button || button.disabled || button.dataset.day === lastPainted) {
        return;
      }

      paintRange(button.dataset.day);
    }

    function dayPointerUp() {
      paint = null;
      lastPainted = null;
    }

    function dayKey(cell, event) {
      if (event.key !== 'Enter' && event.key !== ' ') {
        return;
      }

      event.preventDefault();
      setDay(cell.value, !cell.selected);
    }

    // Slot mode

    const visibleDays = computed(() => Array.from({ length: visibleDayCount.value }, (_, index) => {
      const date = addDays(weekCursor.value, index);
      const value = iso(date);

      return {
        value,
        past: value < todayIso,
        today: value === todayIso,
        weekday: date.toLocaleDateString(locale(), { weekday: 'short' }).replace('.', ''),
        day: date.getDate()
      };
    }));

    const weekLabel = computed(() => {
      const first = visibleDays.value[0];
      const last = visibleDays.value[visibleDays.value.length - 1];
      const from = fromIso(first.value).toLocaleDateString(locale(), { day: 'numeric', month: 'short' });
      const to = fromIso(last.value).toLocaleDateString(locale(), { day: 'numeric', month: 'short', year: 'numeric' });

      return `${from} – ${to}`;
    });

    const hours = Array.from({ length: HOUR_END - HOUR_START }, (_, index) => HOUR_START + index);
    const gridHeight = (HOUR_END - HOUR_START) * HOUR_HEIGHT;

    function shiftWeek(delta) {
      weekCursor.value = addDays(weekCursor.value, delta * visibleDayCount.value);
    }

    function slotStyle(start, end) {
      const top = ((toMinutes(start) - HOUR_START * 60) / 60) * HOUR_HEIGHT;
      const height = ((toMinutes(end) - toMinutes(start)) / 60) * HOUR_HEIGHT;

      return { top: `${top}px`, height: `${Math.max(height, 18)}px` };
    }

    function slotsFor(dayValue) {
      return props.modelValue
        .map((option, index) => ({ option, index }))
        .filter(({ option }) => option.date === dayValue && option.start && option.end);
    }

    // One gesture at a time on the week grid: draw a new slot on empty space, move a slot by
    // its body (vertically and across days), or resize it by its top or bottom edge.
    const draft = ref(null);
    let gesture = null;

    function minuteAtY(body, clientY) {
      const rect = body.getBoundingClientRect();
      const raw = ((clientY - rect.top) / HOUR_HEIGHT) * 60 + HOUR_START * 60;
      const snapped = Math.round(raw / STEP) * STEP;

      return Math.min(Math.max(snapped, HOUR_START * 60), HOUR_END * 60);
    }

    function dayAtX(body, clientX) {
      const rect = body.getBoundingClientRect();
      const width = (rect.width - 52) / visibleDays.value.length;
      const index = Math.floor((clientX - rect.left - 52) / width);

      return visibleDays.value[Math.min(Math.max(index, 0), visibleDays.value.length - 1)];
    }

    function gridPointerDown(event) {
      if (event.target.closest('.cal-slot__remove')) {
        return;
      }

      const body = event.currentTarget;
      const slot = event.target.closest('.cal-slot');
      const column = event.target.closest('.cal-col');

      if (slot && slot.dataset.index !== undefined) {
        const index = Number(slot.dataset.index);
        const option = props.modelValue[index];
        const handle = event.target.closest('.cal-slot__handle');
        gesture = {
          kind: handle ? handle.dataset.handle : 'move',
          index,
          body,
          startY: event.clientY,
          origin: { start: toMinutes(option.start), end: toMinutes(option.end), date: option.date }
        };
        body.setPointerCapture(event.pointerId);
        event.preventDefault();

        return;
      }

      if (!column || column.classList.contains('cal-col--past')) {
        return;
      }

      const anchor = minuteAtY(body, event.clientY);
      gesture = { kind: 'draw', body, anchor, day: column.dataset.day };
      draft.value = { date: column.dataset.day, start: toTime(anchor), end: toTime(Math.min(anchor + MIN_SLOT, HOUR_END * 60)) };
      body.setPointerCapture(event.pointerId);
      event.preventDefault();
    }

    function gridPointerMove(event) {
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

        draft.value = { date: gesture.day, start: toTime(start), end: toTime(end) };

        return;
      }

      const delta = Math.round(((event.clientY - gesture.startY) / HOUR_HEIGHT) * 60 / STEP) * STEP;
      const { start, end, date } = gesture.origin;
      const next = { start, end, date };

      if (gesture.kind === 'move') {
        const duration = end - start;
        next.start = Math.min(Math.max(start + delta, HOUR_START * 60), HOUR_END * 60 - duration);
        next.end = next.start + duration;
        const day = dayAtX(gesture.body, event.clientX);

        if (day && !day.past) {
          next.date = day.value;
        }
      }

      if (gesture.kind === 'start') {
        next.start = Math.min(Math.max(start + delta, HOUR_START * 60), end - MIN_SLOT);
      }

      if (gesture.kind === 'end') {
        next.end = Math.max(Math.min(end + delta, HOUR_END * 60), start + MIN_SLOT);
      }

      const current = props.modelValue[gesture.index];

      if (current.date === next.date && toMinutes(current.start) === next.start && toMinutes(current.end) === next.end) {
        return;
      }

      const updated = props.modelValue.map((option, index) => (index === gesture.index
        ? { ...option, date: next.date, start: toTime(next.start), end: toTime(next.end) }
        : option));
      emit('update:modelValue', updated);
    }

    function gridPointerUp() {
      if (!gesture) {
        return;
      }

      if (gesture.kind === 'draw') {
        const option = { date: draft.value.date, start: draft.value.start, end: draft.value.end, label: '' };
        emit('update:modelValue', [...props.modelValue, option].sort((a, b) => a.date.localeCompare(b.date) || a.start.localeCompare(b.start)));
        draft.value = null;
      }

      gesture = null;
    }

    function removeAt(index) {
      emit('update:modelValue', props.modelValue.filter((_, position) => position !== index));
    }

    function hourLabel(hour) {
      return `${String(hour).padStart(2, '0')}:00`;
    }

    return {
      ui, t, weekdayLabels, monthLabel, monthCells, shiftMonth, dayPointerDown, dayPointerMove, dayPointerUp, dayKey,
      visibleDays, weekLabel, hours, gridHeight, HOUR_HEIGHT, shiftWeek, slotStyle, slotsFor, draft,
      gridPointerDown, gridPointerMove, gridPointerUp, removeAt, hourLabel
    };
  },
  template: `
    <div class="cal">
      <div class="cal__head">
        <button type="button" class="btn btn--ghost btn--icon btn--sm" @click="mode === 'day' ? shiftMonth(-1) : shiftWeek(-1)" :aria-label="t('cal.prev')"><Icon name="back" /></button>
        <span class="cal__title" aria-live="polite">{{ mode === 'day' ? monthLabel : weekLabel }}</span>
        <button type="button" class="btn btn--ghost btn--icon btn--sm" @click="mode === 'day' ? shiftMonth(1) : shiftWeek(1)" :aria-label="t('cal.next')"><Icon name="chevron" /></button>
      </div>

      <div v-if="mode === 'day'" class="cal-month" @pointermove="dayPointerMove" @pointerup="dayPointerUp" @pointercancel="dayPointerUp" role="group" :aria-label="monthLabel">
        <div class="cal-month__weekdays" aria-hidden="true">
          <span v-for="label in weekdayLabels" :key="label">{{ label }}</span>
        </div>
        <div class="cal-month__grid">
          <button
            v-for="cell in monthCells"
            :key="cell.value"
            type="button"
            :class="['cal-day', { 'cal-day--outside': cell.outside, 'cal-day--today': cell.today, 'cal-day--selected': cell.selected }]"
            :data-day="cell.value"
            :disabled="cell.past"
            :aria-pressed="cell.selected"
            :aria-label="cell.label ? cell.value + ', ' + cell.label : cell.value"
            :title="cell.label || null"
            @pointerdown.prevent="dayPointerDown(cell, $event)"
            @keydown="dayKey(cell, $event)">
            <span>{{ cell.day }}</span>
          </button>
        </div>
      </div>

      <div v-else class="cal-week" role="group" :aria-label="weekLabel" :style="{ '--days': visibleDays.length }">
        <div class="cal-week__days">
          <span class="cal-week__corner"></span>
          <span v-for="day in visibleDays" :key="day.value" :class="['cal-week__day', { 'cal-week__day--today': day.today, 'cal-week__day--past': day.past }]">
            <span class="cal-week__weekday">{{ day.weekday }}</span>
            <span class="cal-week__num">{{ day.day }}</span>
          </span>
        </div>
        <div class="cal-week__scroll">
          <div class="cal-week__body" :style="{ height: gridHeight + 'px' }" @pointerdown="gridPointerDown" @pointermove="gridPointerMove" @pointerup="gridPointerUp" @pointercancel="gridPointerUp">
            <div class="cal-week__hours" aria-hidden="true">
              <span v-for="hour in hours" :key="hour" :style="{ height: HOUR_HEIGHT + 'px' }">{{ hourLabel(hour) }}</span>
            </div>
            <div
              v-for="day in visibleDays"
              :key="day.value"
              :class="['cal-col', { 'cal-col--past': day.past }]"
              :data-day="day.value"
              :style="{ backgroundSize: '100% ' + HOUR_HEIGHT + 'px' }">
              <div v-for="entry in slotsFor(day.value)" :key="entry.index" class="cal-slot" :data-index="entry.index" :style="slotStyle(entry.option.start, entry.option.end)" :title="t('cal.slotTitle')">
                <span class="cal-slot__handle cal-slot__handle--top" data-handle="start" aria-hidden="true"></span>
                <span class="cal-slot__time">{{ entry.option.start }}–{{ entry.option.end }}</span>
                <span class="cal-slot__label" v-if="entry.option.label">{{ entry.option.label }}</span>
                <button type="button" class="cal-slot__remove" @click="removeAt(entry.index)" :aria-label="t('newPoll.removeOption')"><Icon name="x" /></button>
                <span class="cal-slot__handle cal-slot__handle--bottom" data-handle="end" aria-hidden="true"></span>
              </div>
              <div v-if="draft && draft.date === day.value" class="cal-slot cal-slot--draft" :style="slotStyle(draft.start, draft.end)">
                <span class="cal-slot__time">{{ draft.start }}–{{ draft.end }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <p class="field__hint">{{ mode === 'day' ? t('cal.hintDay') : t('cal.hintSlots') }}</p>
    </div>`
};
