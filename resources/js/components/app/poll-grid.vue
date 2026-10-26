<script setup lang="ts">
import { computed } from 'vue';
import Mark from '@/components/app/mark.vue';
import PollAnswerCell from '@/components/app/poll-answer-cell.vue';
import { describeOption, formatDate } from '@/components/app/poll/dates';
import type {
    PollAnswer,
    PollAnswers,
    PollGridOption,
    PollGridParticipant,
    PollKey,
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
    }>(),
    {
        bestOptionIds: () => [],
        showNoTotals: false,
        currentParticipantId: null,
        closed: false,
        busy: false,
        idPrefix: 'poll',
    },
);

const emit = defineEmits<{ answer: [optionId: PollKey, answer: PollAnswer] }>();

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
            time:
                option.start && option.end
                    ? `${option.start}–${option.end}`
                    : '',
            description: describeOption(option, locale.value),
            totals: props.totals[key] ?? { yes: 0, maybe: 0, no: 0 },
        };
    }),
);

const isCurrent = (participant: PollGridParticipant): boolean =>
    props.currentParticipantId !== null &&
    String(participant.id) === String(props.currentParticipantId);

function answerOf(
    participant: PollGridParticipant,
    optionId: PollKey,
): PollAnswer | null {
    return props.answers[String(participant.id)]?.[String(optionId)] ?? null;
}

function displayName(participant: PollGridParticipant): string {
    return participant.name || participant.email || t('common.noName');
}

function cellTint(column: { best: boolean; fixed: boolean }): string {
    if (column.fixed) {
        return 'bg-success-soft shadow-[inset_2px_0_0_var(--primary),inset_-2px_0_0_var(--primary)]';
    }

    return column.best ? 'bg-primary-soft' : '';
}
</script>

<template>
    <div class="box max-w-full min-w-0 overflow-hidden" data-feature="F-06">
        <!-- The only horizontal scroll area of the app; the name column stays put -->
        <div
            class="relative max-w-full min-w-0 overflow-x-auto overscroll-x-contain focus-visible:outline-offset-[-2px]"
            tabindex="0"
            role="region"
            :aria-label="t('poll.grid')"
        >
            <table
                class="w-max min-w-full border-separate border-spacing-0 text-sm"
            >
                <thead>
                    <tr>
                        <th
                            scope="col"
                            class="border-border sticky left-0 z-2 max-w-[140px] min-w-[120px] border-b bg-(--box-bg) pl-3 shadow-[1px_0_0_var(--border)] sm:max-w-[220px] sm:min-w-[150px] sm:pl-4"
                        >
                            <span class="sr-only">{{ t('poll.who') }}</span>
                        </th>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            :class="
                                cn(
                                    'border-border relative min-w-[84px] border-b bg-(--box-bg) px-2 pt-3 pb-2.5 text-center align-bottom',
                                    cellTint(column),
                                )
                            "
                        >
                            <span
                                class="mb-1.5 flex min-h-[22px] items-center justify-center"
                            >
                                <span
                                    v-if="column.fixed"
                                    class="bg-primary font-tag text-primary-fg inline-block -rotate-3 rounded-lg px-2 pt-px pb-0.5 text-xs font-bold shadow-sm"
                                >
                                    {{ t('group.polls.fixed') }}
                                </span>
                                <span
                                    v-else-if="column.best"
                                    class="text-primary text-[11px] font-bold tracking-[0.02em]"
                                    >{{ t('poll.best') }}</span
                                >
                            </span>
                            <span
                                class="text-fg-soft block text-xs leading-tight font-semibold tracking-[0.04em] uppercase"
                                >{{ column.weekday }}</span
                            >
                            <span
                                :class="
                                    cn(
                                        'font-display mt-0.5 block text-[22px] leading-tight font-[720] tabular-nums',
                                        column.best
                                            ? 'text-primary-soft-fg'
                                            : 'text-fg',
                                    )
                                "
                            >
                                {{ column.day }}
                            </span>
                            <span
                                class="text-fg-muted block text-xs leading-tight font-semibold"
                                >{{ column.month }}</span
                            >
                            <span
                                v-if="column.time"
                                class="text-fg mt-1.5 block text-xs leading-tight font-semibold whitespace-nowrap tabular-nums"
                            >
                                {{ column.time }}
                            </span>
                            <span
                                v-if="column.option.label"
                                :title="column.option.label"
                                class="text-fg-muted mx-auto mt-0.5 block max-w-[110px] truncate text-xs leading-tight font-normal"
                            >
                                {{ column.option.label }}
                            </span>
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
                                    'border-border sticky left-0 z-2 max-w-[140px] min-w-[120px] border-b py-2.5 pr-2 pl-3 text-left font-semibold shadow-[1px_0_0_var(--border)] sm:max-w-[220px] sm:min-w-[150px] sm:pl-4',
                                    isCurrent(participant)
                                        ? 'bg-surface-raised'
                                        : 'bg-(--box-bg)',
                                )
                            "
                        >
                            <span class="flex min-w-0 items-center gap-2.5">
                                <UserAvatar
                                    :name="participant.name"
                                    :email="participant.email"
                                    size="sm"
                                />
                                <span class="min-w-0 truncate">
                                    {{ displayName(participant) }}
                                    <span
                                        v-if="isCurrent(participant)"
                                        class="text-fg-soft font-normal"
                                        >({{ t('group.member.you') }})</span
                                    >
                                </span>
                            </span>
                        </th>
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="
                                cn(
                                    'border-border relative border-b px-2 py-2.5 text-center align-middle',
                                    cellTint(column),
                                )
                            "
                        >
                            <PollAnswerCell
                                :answer="
                                    answerOf(participant, column.option.id)
                                "
                                :editable="isCurrent(participant) && !closed"
                                :disabled="busy"
                                :name="`${idPrefix}-answer-${column.key}`"
                                :option-label="column.description"
                                :participant-name="displayName(participant)"
                                @update:answer="
                                    emit('answer', column.option.id, $event)
                                "
                            />
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th
                            scope="row"
                            class="border-border-strong bg-surface-muted text-fg-muted sticky left-0 z-2 max-w-[140px] min-w-[120px] border-t py-2.5 pr-2 pl-3 text-left text-[13px] font-semibold shadow-[1px_0_0_var(--border)] sm:max-w-[220px] sm:min-w-[150px] sm:pl-4"
                        >
                            {{ t('poll.totals') }}
                        </th>
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="
                                cn(
                                    'border-border-strong border-t px-2 py-2.5 text-center font-semibold whitespace-nowrap tabular-nums',
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
                            <span
                                aria-hidden="true"
                                class="inline-flex items-center justify-center gap-2"
                            >
                                <span
                                    class="text-fg inline-flex items-center gap-1 text-[15px]"
                                >
                                    <Mark
                                        answer="yes"
                                        class="size-4! [&_svg]:size-2.5"
                                    />
                                    {{ column.totals.yes }}
                                </span>
                                <span
                                    v-if="column.totals.maybe"
                                    class="text-fg-muted inline-flex items-center gap-1 text-[13px]"
                                >
                                    <Mark
                                        answer="maybe"
                                        class="size-4! border-[1.5px]! [&_svg]:size-2.5"
                                    />
                                    {{ column.totals.maybe }}
                                </span>
                                <span
                                    v-if="showNoTotals"
                                    class="text-fg-muted inline-flex items-center gap-1 text-[13px]"
                                >
                                    <Mark
                                        answer="no"
                                        class="size-4! [&_svg]:size-2.5"
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
