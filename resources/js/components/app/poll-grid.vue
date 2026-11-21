<script setup lang="ts">
import { CalendarCheck, Star } from '@lucide/vue';
import { computed } from 'vue';
import Mark from '@/components/app/mark.vue';
import PollAnswerCell from '@/components/app/poll-answer-cell.vue';
import AnswerCycle from '@/components/app/poll/answer-cycle.vue';
import { describeOption, formatDate } from '@/components/app/poll/dates';
import NoteButton from '@/components/app/poll/note-button.vue';
import NoteMarker from '@/components/app/poll/note-marker.vue';
import type {
    PollAnswer,
    PollAnswers,
    PollGridOption,
    PollGridParticipant,
    PollKey,
    PollNotes,
    PollOptionTotals,
} from '@/components/app/poll/types';
import UserAvatar from '@/components/app/user-avatar.vue';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        options: PollGridOption[];
        // Rows in display order; the caller puts the current person first
        participants: PollGridParticipant[];
        answers: PollAnswers;
        totals: Record<string, PollOptionTotals>;
        // Computed by the backend (F-06): most yes, then fewest no, ties all listed
        bestOptionIds?: PollKey[];
        // True when the fewest-no rule decided the best option, so the no counts explain it
        showNoTotals?: boolean;
        currentParticipantId?: PollKey | null;
        closed?: boolean;
        // Blocks the own row while an answer is being saved
        busy?: boolean;
        // Ties each radio group to this poll when several grids share a page
        idPrefix?: string;
        // "Edit answers" (F-06): these rows get the cycle button for an editor
        editableParticipantIds?: PollKey[];
        // Notes keyed like answers (F-06); the own row edits them, other rows show a marker
        notes?: PollNotes;
    }>(),
    {
        bestOptionIds: () => [],
        showNoTotals: false,
        currentParticipantId: null,
        closed: false,
        busy: false,
        idPrefix: 'poll',
        editableParticipantIds: () => [],
        notes: () => ({}),
    },
);

const emit = defineEmits<{
    answer: [optionId: PollKey, answer: PollAnswer];
    answerFor: [participantId: PollKey, optionId: PollKey, answer: PollAnswer];
    editNote: [optionId: PollKey];
}>();

const { t, locale } = useI18n();

const bestIds = computed(() => new Set(props.bestOptionIds.map(String)));

const columns = computed(() =>
    props.options.map((option) => {
        const key = String(option.id);
        const fixed = !!option.fixed;

        return {
            option,
            key,
            fixed,
            best: !fixed && bestIds.value.has(key),
            weekday: formatDate(option.date, locale.value, {
                weekday: 'short',
            }).replace('.', ''),
            day: formatDate(option.date, locale.value, {
                day: 'numeric',
            }).replace('.', ''),
            month: formatDate(option.date, locale.value, {
                month: 'short',
            }).replace('.', ''),
            monthKey: option.date.slice(0, 7),
            // The compact column only fits the start; the full range is in the column title
            time: option.start ?? '',
            description: describeOption(option, locale.value),
            totals: props.totals[key] ?? { yes: 0, maybe: 0, no: 0 },
        };
    }),
);

// Consecutive columns of one month share a header cell, so the day columns stay narrow
const monthGroups = computed(() =>
    columns.value.reduce<{ key: string; label: string; span: number }[]>(
        (groups, column) => {
            const last = groups[groups.length - 1];

            if (last && last.key === column.monthKey) {
                last.span++;

                return groups;
            }

            groups.push({
                key: column.monthKey,
                label: column.month,
                span: 1,
            });

            return groups;
        },
        [],
    ),
);

// Marks the first column of each month after the first, where the month divider line goes
const monthStarts = computed(() => {
    const starts = new Set<string>();
    let previous = '';

    columns.value.forEach((column, index) => {
        if (index > 0 && column.monthKey !== previous) {
            starts.add(column.key);
        }

        previous = column.monthKey;
    });

    return starts;
});

const editableIds = computed(
    () => new Set(props.editableParticipantIds.map(String)),
);

// The caller only passes ids while the poll accepts edits, so closed does not apply here
const isEditable = (participant: PollGridParticipant): boolean =>
    editableIds.value.has(String(participant.id));

const isCurrent = (participant: PollGridParticipant): boolean =>
    props.currentParticipantId !== null &&
    String(participant.id) === String(props.currentParticipantId);

function answerOf(
    participant: PollGridParticipant,
    optionId: PollKey,
): PollAnswer | null {
    return props.answers[String(participant.id)]?.[String(optionId)] ?? null;
}

function noteOf(
    participant: PollGridParticipant,
    optionId: PollKey,
): string | null {
    return props.notes[String(participant.id)]?.[String(optionId)] ?? null;
}

// Edit mode lists people without any answer too, so their rows say why they are empty
function hasAnyAnswer(participant: PollGridParticipant): boolean {
    return Object.keys(props.answers[String(participant.id)] ?? {}).length > 0;
}

function displayName(participant: PollGridParticipant): string {
    return participant.name || participant.email || t('common.noName');
}

function headerTitle(column: {
    description: string;
    best: boolean;
    fixed: boolean;
}): string {
    if (column.fixed) {
        return `${column.description} · ${t('group.polls.fixed')}`;
    }

    return column.best
        ? `${column.description} · ${t('poll.best')}`
        : column.description;
}

function cellTint(column: { best: boolean; fixed: boolean }): string {
    if (column.fixed) {
        return 'bg-success-soft shadow-[inset_2px_0_0_var(--primary),inset_-2px_0_0_var(--primary)]';
    }

    return column.best ? 'bg-primary-soft' : '';
}
</script>

<template>
    <div
        class="box w-fit max-w-full min-w-0 overflow-hidden"
        data-feature="F-06"
    >
        <!-- Box fits the table, so a few options stay left-aligned instead of stretching -->
        <!-- The only scroll area of the app; capped in height so the header and total row can stick -->
        <div
            class="relative max-h-[75vh] max-w-full min-w-0 overflow-auto overscroll-x-contain pb-3 focus-visible:outline-offset-[-2px]"
            tabindex="0"
            role="region"
            :aria-label="t('poll.grid')"
        >
            <table class="w-max border-separate border-spacing-0 text-sm">
                <thead class="sticky top-0 z-3">
                    <tr>
                        <th
                            scope="col"
                            rowspan="2"
                            class="border-border sticky left-0 z-2 max-w-[132px] min-w-[104px] border-b bg-(--box-bg) pl-3 shadow-[1px_0_0_var(--border)] sm:max-w-[200px] sm:min-w-[150px] sm:pl-4"
                        >
                            <span class="sr-only">{{ t('poll.who') }}</span>
                        </th>
                        <th
                            v-for="(group, index) in monthGroups"
                            :key="group.key"
                            scope="colgroup"
                            :colspan="group.span"
                            :class="
                                cn(
                                    'text-fg-muted bg-(--box-bg) px-1.5 pt-2 pb-0.5 text-left text-xs font-semibold whitespace-nowrap',
                                    index > 0 && 'border-border border-l',
                                )
                            "
                        >
                            <span class="sticky left-[112px] sm:left-[158px]">{{
                                group.label
                            }}</span>
                        </th>
                    </tr>
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            :title="headerTitle(column)"
                            :class="
                                cn(
                                    'border-border relative w-12 max-w-12 min-w-12 border-b bg-(--box-bg) px-0.5 pt-1 pb-1.5 text-center align-top',
                                    monthStarts.has(column.key) && 'border-l',
                                    cellTint(column),
                                )
                            "
                        >
                            <span
                                class="flex h-3.5 items-center justify-center"
                            >
                                <span
                                    v-if="column.fixed"
                                    class="text-primary inline-flex"
                                >
                                    <CalendarCheck
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <span class="sr-only">{{
                                        t('group.polls.fixed')
                                    }}</span>
                                </span>
                                <span
                                    v-else-if="column.best"
                                    class="text-primary inline-flex"
                                >
                                    <Star
                                        class="size-3.5 fill-current"
                                        aria-hidden="true"
                                    />
                                    <span class="sr-only">{{
                                        t('poll.best')
                                    }}</span>
                                </span>
                            </span>
                            <span
                                class="text-fg-soft block text-[11px] leading-tight font-semibold tracking-[0.02em]"
                                >{{ column.weekday }}</span
                            >
                            <span
                                :class="
                                    cn(
                                        'font-display block text-base leading-tight font-[720] tabular-nums',
                                        column.best
                                            ? 'text-primary-soft-fg'
                                            : 'text-fg',
                                    )
                                "
                            >
                                {{ column.day }}
                            </span>
                            <span
                                v-if="column.time"
                                class="text-fg block text-[10px] leading-tight font-semibold tabular-nums"
                            >
                                {{ column.time }}
                            </span>
                            <span
                                v-if="column.option.label"
                                class="text-fg-muted block truncate text-[10px] leading-tight font-normal"
                            >
                                {{ column.option.label }}
                            </span>
                            <span class="sr-only">{{
                                column.description
                            }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="participant in participants"
                        :key="participant.id"
                        :class="
                            isCurrent(participant) ? 'bg-surface-raised' : ''
                        "
                    >
                        <th
                            scope="row"
                            :class="
                                cn(
                                    'border-border sticky left-0 z-2 max-w-[132px] min-w-[104px] border-b py-1.5 pr-2 pl-3 text-left font-semibold shadow-[1px_0_0_var(--border)] sm:max-w-[200px] sm:min-w-[150px] sm:pl-4',
                                    isCurrent(participant)
                                        ? 'bg-surface-raised'
                                        : 'bg-(--box-bg)',
                                )
                            "
                        >
                            <!-- Cells ignore max-width, so the cap sits here to keep long names truncated -->
                            <span
                                class="flex max-w-[112px] min-w-0 items-center gap-2 sm:max-w-[176px]"
                            >
                                <UserAvatar
                                    :name="participant.name"
                                    :email="participant.email"
                                    size="sm"
                                    class="max-sm:hidden"
                                />
                                <span
                                    class="min-w-0 truncate"
                                    :title="displayName(participant)"
                                >
                                    {{ displayName(participant) }}
                                    <span
                                        v-if="isCurrent(participant)"
                                        class="text-fg-soft font-normal"
                                        >({{ t('group.member.you') }})</span
                                    >
                                    <span
                                        v-if="
                                            isEditable(participant) &&
                                            !hasAnyAnswer(participant)
                                        "
                                        class="text-fg-soft block truncate text-xs font-normal"
                                        >{{
                                            t('poll.editAnswers.noAnswer')
                                        }}</span
                                    >
                                </span>
                            </span>
                        </th>
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="
                                cn(
                                    'group/note border-border relative border-b px-0.5 py-2.5 text-center align-middle',
                                    monthStarts.has(column.key) && 'border-l',
                                    cellTint(column),
                                )
                            "
                        >
                            <AnswerCycle
                                v-if="isCurrent(participant) && !closed"
                                :answer="
                                    answerOf(participant, column.option.id)
                                "
                                :disabled="busy"
                                :option-label="column.description"
                                @update:answer="
                                    emit('answer', column.option.id, $event)
                                "
                            />
                            <AnswerCycle
                                v-else-if="isEditable(participant)"
                                :answer="
                                    answerOf(participant, column.option.id)
                                "
                                :disabled="busy"
                                :option-label="column.description"
                                :participant-name="displayName(participant)"
                                @update:answer="
                                    emit(
                                        'answerFor',
                                        participant.id,
                                        column.option.id,
                                        $event,
                                    )
                                "
                            />
                            <PollAnswerCell
                                v-else
                                :answer="
                                    answerOf(participant, column.option.id)
                                "
                                :name="`${idPrefix}-answer-${column.key}`"
                                :option-label="column.description"
                                :participant-name="displayName(participant)"
                            />
                            <!-- Below the cycle button, so both stay full-size targets; a note needs an answer first -->
                            <!-- Fixed slot in every own cell, so the cycle buttons align whether or not a note button shows -->
                            <div
                                v-if="isCurrent(participant) && !closed"
                                class="mt-0.5 flex h-6 justify-center"
                            >
                                <NoteButton
                                    v-if="
                                        answerOf(participant, column.option.id)
                                    "
                                    :note="
                                        noteOf(participant, column.option.id)
                                    "
                                    :option-label="column.description"
                                    size="md"
                                    @open="emit('editNote', column.option.id)"
                                />
                            </div>
                            <NoteMarker
                                v-else-if="
                                    noteOf(participant, column.option.id)
                                "
                                :notes="[
                                    {
                                        name: displayName(participant),
                                        note: noteOf(
                                            participant,
                                            column.option.id,
                                        )!,
                                    },
                                ]"
                                class="absolute top-0.5 right-0.5"
                            />
                        </td>
                    </tr>
                </tbody>
                <tfoot class="sticky bottom-0 z-3">
                    <tr>
                        <th
                            scope="row"
                            class="border-border-strong bg-surface-muted text-fg-muted sticky left-0 z-2 max-w-[132px] min-w-[104px] border-t pt-1.5 pr-2 pb-3 pl-3 text-left text-[13px] font-semibold shadow-[1px_0_0_var(--border)] sm:max-w-[200px] sm:min-w-[150px] sm:pl-4"
                        >
                            {{ t('poll.totals') }}
                        </th>
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="
                                cn(
                                    'border-border-strong border-t px-0.5 pt-1.5 pb-3 text-center align-top font-semibold whitespace-nowrap tabular-nums',
                                    monthStarts.has(column.key) &&
                                        'border-l border-l-(--border)',
                                    column.fixed || column.best
                                        ? cellTint(column)
                                        : 'bg-surface-muted',
                                )
                            "
                        >
                            <span class="sr-only">
                                {{
                                    [
                                        t('poll.totals.yes', {
                                            n: column.totals.yes,
                                        }),
                                        t('poll.totals.maybe', {
                                            n: column.totals.maybe,
                                        }),
                                        t('poll.totals.no', {
                                            n: column.totals.no,
                                        }),
                                    ].join(', ')
                                }}
                            </span>
                            <!-- Stacked so three counts fit the narrow column -->
                            <span
                                aria-hidden="true"
                                class="flex flex-col items-center gap-0.5"
                            >
                                <span
                                    class="text-fg inline-flex items-center gap-0.5 text-sm"
                                >
                                    <Mark
                                        answer="yes"
                                        class="size-3.5! [&_svg]:size-2"
                                    />
                                    {{ column.totals.yes }}
                                </span>
                                <span
                                    v-if="column.totals.maybe"
                                    class="text-fg-muted inline-flex items-center gap-0.5 text-[11px]"
                                >
                                    <Mark
                                        answer="maybe"
                                        class="size-3! border-[1.5px]! [&_svg]:size-2"
                                    />
                                    {{ column.totals.maybe }}
                                </span>
                                <span
                                    v-if="showNoTotals"
                                    class="text-fg-muted inline-flex items-center gap-0.5 text-[11px]"
                                >
                                    <Mark
                                        answer="no"
                                        class="size-3! [&_svg]:size-2"
                                    />
                                    {{ column.totals.no }}
                                </span>
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
