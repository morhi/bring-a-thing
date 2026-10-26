<script setup lang="ts">
import { ref } from 'vue';
import OptionCalendar from '@/components/app/option-calendar.vue';
import OptionRows from '@/components/app/poll/option-rows.vue';
import type { OptionDraft } from '@/components/app/poll/types';

const today = '2026-10-26';
const defaultSlot = { start: '19:00', end: '21:00' };

const dayOptions = ref<OptionDraft[]>([
    {
        id: 1,
        date: '2026-10-29',
        start: null,
        end: null,
        label: '',
        locked: true,
        answerCount: 4,
    },
    {
        id: 2,
        date: '2026-10-31',
        start: null,
        end: null,
        label: 'Room free until 22:00',
        locked: true,
        fixed: true,
        answerCount: 6,
    },
    { date: '2026-11-04', start: null, end: null, label: 'Lunch' },
    { date: '2026-11-05', start: null, end: null, label: '' },
]);

const slotOptions = ref<OptionDraft[]>([
    {
        id: 11,
        date: '2026-10-28',
        start: '18:00',
        end: '20:00',
        label: 'Rehearsal',
        locked: true,
        answerCount: 3,
    },
    { date: '2026-10-28', start: '20:30', end: '22:00', label: '' },
    {
        date: '2026-10-30',
        start: '12:00',
        end: '13:30',
        label: 'Lunch with a much longer label than fits',
    },
]);

const emptyOptions = ref<OptionDraft[]>([]);

const closedOptions: OptionDraft[] = [
    {
        id: 21,
        date: '2026-11-02',
        start: '19:00',
        end: '21:00',
        label: '',
        locked: true,
        answerCount: 5,
    },
    {
        id: 22,
        date: '2026-11-03',
        start: '19:00',
        end: '21:00',
        label: 'Fixed one',
        locked: true,
        fixed: true,
        answerCount: 7,
    },
];

const removeRequests = ref<string[]>([]);

function requestRemove(index: number, option: OptionDraft): void {
    removeRequests.value = [
        `#${index} ${option.date} (${option.answerCount ?? 0})`,
        ...removeRequests.value,
    ].slice(0, 3);
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="option-calendar" class="text-xl">Option calendar</h2>
        <p class="text-fg-muted">
            Organizers pick poll options by day (month grid, click or drag) or
            as time slots (week grid, draw, move, resize; the plus per day is
            the keyboard path). The rows below share the same v-model and carry
            times and labels. Locked options have answers and keep date and
            time; fixed ones cannot be removed. Removing a locked option emits
            <code>request-remove</code> for the page to confirm.
        </p>
        <div
            v-for="theme in ['', 'dark']"
            :key="theme"
            :class="theme"
            class="bg-surface-raised text-fg flex flex-col gap-8 rounded-2xl p-5"
        >
            <p class="text-fg-muted text-sm font-semibold">
                {{ theme || 'light' }}
            </p>

            <div class="flex max-w-[40rem] flex-col gap-3">
                <h3 class="text-base">
                    By day, with labels, locked and fixed days
                </h3>
                <OptionCalendar
                    v-model="dayOptions"
                    mode="day"
                    :today="today"
                />
                <OptionRows
                    v-model="dayOptions"
                    mode="day"
                    show-meta
                    @request-remove="requestRemove"
                />
            </div>

            <div class="flex max-w-[40rem] flex-col gap-3">
                <h3 class="text-base">Time slots, one locked slot</h3>
                <OptionCalendar
                    v-model="slotOptions"
                    mode="slots"
                    :today="today"
                    :default-slot="defaultSlot"
                />
                <OptionRows
                    v-model="slotOptions"
                    mode="slots"
                    show-meta
                    @request-remove="requestRemove"
                />
            </div>

            <p v-if="removeRequests.length" class="text-fg-muted text-sm">
                request-remove: {{ removeRequests.join(' · ') }}
            </p>

            <div class="flex max-w-[40rem] flex-col gap-3">
                <h3 class="text-base">Empty</h3>
                <OptionCalendar
                    v-model="emptyOptions"
                    mode="slots"
                    :today="today"
                    :default-slot="defaultSlot"
                />
                <OptionRows v-model="emptyOptions" mode="slots" />
            </div>

            <div class="flex max-w-[40rem] flex-col gap-3">
                <h3 class="text-base">Disabled (closed poll)</h3>
                <OptionCalendar
                    :model-value="closedOptions"
                    mode="day"
                    :today="today"
                    disabled
                />
                <OptionRows
                    :model-value="closedOptions"
                    mode="slots"
                    show-meta
                    disabled
                />
            </div>
        </div>
    </section>
</template>
