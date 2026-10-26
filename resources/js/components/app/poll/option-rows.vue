<script setup lang="ts">
import { Lock, Minus } from '@lucide/vue';
import { computed } from 'vue';
import { describeOption, formatDate } from '@/components/app/poll/dates';
import TimePicker from '@/components/app/time-picker.vue';
import type {
    OptionCalendarMode,
    OptionDraft,
} from '@/components/app/poll/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        mode: OptionCalendarMode;
        modelValue: OptionDraft[];
        // Edit mode shows the answer count of existing options
        showMeta?: boolean;
        disabled?: boolean;
        // Keeps the time picker ids unique when several row lists share a page
        idPrefix?: string;
    }>(),
    { showMeta: false, disabled: false, idPrefix: 'option' },
);

const emit = defineEmits<{
    'update:modelValue': [options: OptionDraft[]];
    // Options with answers lose them on removal, so the page confirms first (F-08)
    'request-remove': [index: number, option: OptionDraft];
}>();

const { t, tc, locale } = useI18n();

const slotCount = computed(
    () =>
        props.modelValue.filter((option) => option.start && option.end).length,
);

function isFrozen(option: OptionDraft): boolean {
    return !!option.locked || !!option.fixed;
}

function update(index: number, changes: Partial<OptionDraft>): void {
    emit(
        'update:modelValue',
        props.modelValue.map((option, position) =>
            position === index ? { ...option, ...changes } : option,
        ),
    );
}

function remove(index: number): void {
    const option = props.modelValue[index];

    if (props.disabled || option.fixed) {
        return;
    }

    if (option.locked) {
        emit('request-remove', index, option);

        return;
    }

    emit(
        'update:modelValue',
        props.modelValue.filter((_, position) => position !== index),
    );
}

function canApplyToAll(option: OptionDraft): boolean {
    return (
        props.mode === 'slots' &&
        !props.disabled &&
        slotCount.value >= 2 &&
        !!option.start &&
        !!option.end
    );
}

// Locked options keep their time (F-06)
function applyTimeToAll(source: OptionDraft): void {
    emit(
        'update:modelValue',
        props.modelValue.map((option) =>
            option === source || isFrozen(option)
                ? option
                : { ...option, start: source.start, end: source.end },
        ),
    );
}
</script>

<template>
    <div class="flex flex-col gap-2" data-feature="F-06">
        <p v-if="!modelValue.length" class="text-fg-muted text-sm">
            {{ t('newPoll.noOptions') }}
        </p>
        <ul v-else class="flex flex-col gap-2">
            <li
                v-for="(option, index) in modelValue"
                :key="
                    option.id ?? `new-${option.date}-${option.start}-${index}`
                "
                :class="
                    cn(
                        'bg-surface-muted grid items-center gap-2 rounded-lg p-2',
                        mode === 'slots'
                            ? 'grid-cols-[minmax(0,1fr)_auto] md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto_auto]'
                            : 'grid-cols-[minmax(0,1fr)_auto] sm:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_auto_auto]',
                    )
                "
            >
                <span
                    :class="
                        cn(
                            'flex min-w-0 items-center gap-1.5 text-sm font-semibold',
                            mode === 'slots' && 'col-span-2 md:col-span-1',
                        )
                    "
                >
                    <Lock
                        v-if="isFrozen(option)"
                        role="img"
                        class="text-fg-soft size-3.5 flex-none"
                        :aria-label="
                            option.fixed
                                ? t('group.polls.fixed')
                                : t('cal.locked')
                        "
                    />
                    <span class="truncate">{{
                        formatDate(option.date, locale, {
                            weekday: 'short',
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric',
                        })
                    }}</span>
                </span>
                <div
                    v-if="mode === 'slots'"
                    class="col-span-2 flex flex-wrap items-center gap-x-2 gap-y-1.5 md:col-span-1"
                >
                    <TimePicker
                        :id="`${idPrefix}-${index}-start`"
                        :model-value="option.start"
                        :disabled="disabled || isFrozen(option)"
                        required
                        :aria-label="`${t('newPoll.start')}, ${describeOption({ ...option, start: null, end: null, label: null }, locale)}`"
                        @update:model-value="update(index, { start: $event })"
                    />
                    <span class="text-fg-muted" aria-hidden="true">–</span>
                    <TimePicker
                        :id="`${idPrefix}-${index}-end`"
                        :model-value="option.end"
                        :disabled="disabled || isFrozen(option)"
                        required
                        :aria-label="`${t('newPoll.end')}, ${describeOption({ ...option, start: null, end: null, label: null }, locale)}`"
                        @update:model-value="update(index, { end: $event })"
                    />
                </div>
                <Input
                    type="text"
                    :model-value="option.label"
                    :disabled="disabled"
                    :placeholder="t('newPoll.slotLabelPlaceholder')"
                    :aria-label="`${t('newPoll.slotLabel')}, ${describeOption({ ...option, label: null }, locale)}`"
                    :class="
                        cn(
                            'bg-surface-raised h-9',
                            mode === 'day'
                                ? 'order-1 col-span-2 sm:order-none sm:col-span-1'
                                : 'max-md:order-1',
                        )
                    "
                    @update:model-value="
                        update(index, { label: String($event) })
                    "
                />
                <span
                    v-if="showMeta"
                    :class="
                        cn(
                            'text-fg-muted flex items-center gap-1.5 text-xs whitespace-nowrap',
                            mode === 'slots' ? 'max-md:hidden' : 'max-sm:hidden',
                        )
                    "
                >
                    <template v-if="option.id">{{
                        tc('cal.answerCount', option.answerCount ?? 0)
                    }}</template>
                    <Badge v-if="option.fixed" variant="secondary">{{
                        t('group.polls.fixed')
                    }}</Badge>
                </span>
                <span
                    v-else
                    :class="mode === 'slots' ? 'max-md:hidden' : 'max-sm:hidden'"
                    aria-hidden="true"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    :class="cn('justify-self-end', mode === 'slots' && 'max-md:order-1')"
                    :disabled="disabled || !!option.fixed"
                    :aria-label="`${t('newPoll.removeOption')}: ${describeOption(option, locale)}`"
                    @click="remove(index)"
                >
                    <Minus />
                </Button>
                <button
                    v-if="canApplyToAll(option)"
                    type="button"
                    class="text-primary hover:text-primary-hover order-2 col-span-full min-h-7 justify-self-start px-0.5 text-[13px] font-semibold underline underline-offset-[3px]"
                    @click="applyTimeToAll(option)"
                >
                    {{ t('newPoll.applyTimeToAll') }}
                </button>
            </li>
        </ul>
    </div>
</template>
