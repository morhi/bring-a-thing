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
import { Label } from '@/components/ui/label';
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
        // Label errors by option index
        errors?: Record<number, string | undefined>;
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
            <!-- A fixed date track keeps the inputs aligned across rows; the meta sits after the label so it never pushes the input away -->
            <li
                v-for="(option, index) in modelValue"
                :key="
                    option.id ?? `new-${option.date}-${option.start}-${index}`
                "
                :class="
                    cn(
                        'bg-surface-muted grid items-center gap-2 rounded-lg px-2 pt-4 pb-0',
                        mode === 'slots'
                            ? 'grid-cols-[minmax(0,1fr)_auto] lg:grid-cols-[11rem_auto_minmax(0,1fr)_auto_auto]'
                            : 'grid-cols-[minmax(0,1fr)_auto] sm:grid-cols-[11rem_minmax(0,1fr)_auto_auto]',
                    )
                "
            >
                <span
                    class="flex min-w-0 items-center gap-1.5 text-sm font-semibold"
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
                    class="col-span-2 flex flex-col items-start gap-1.5 max-lg:order-1 lg:col-span-1 lg:mt-3.5 lg:flex-row lg:items-center lg:gap-3 lg:self-start"
                >
                    <!-- Narrow screens stack start and end with visible captions; the range stays on one line from md up -->
                    <div
                        class="flex flex-col gap-1.5 md:flex-row md:flex-nowrap md:items-center md:gap-2"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="text-fg-muted w-12 text-xs md:hidden"
                                aria-hidden="true"
                                >{{ t('newPoll.start') }}</span
                            >
                            <TimePicker
                                :id="`${idPrefix}-${index}-start`"
                                :model-value="option.start"
                                :disabled="disabled || isFrozen(option)"
                                required
                                :aria-label="`${t('newPoll.start')}, ${describeOption({ ...option, start: null, end: null, label: null }, locale)}`"
                                @update:model-value="
                                    update(index, { start: $event })
                                "
                            />
                        </div>
                        <span
                            class="text-fg-muted max-md:hidden"
                            aria-hidden="true"
                            >–</span
                        >
                        <div class="flex items-center gap-2">
                            <span
                                class="text-fg-muted w-12 text-xs md:hidden"
                                aria-hidden="true"
                                >{{ t('newPoll.end') }}</span
                            >
                            <TimePicker
                                :id="`${idPrefix}-${index}-end`"
                                :model-value="option.end"
                                :disabled="disabled || isFrozen(option)"
                                required
                                :aria-label="`${t('newPoll.end')}, ${describeOption({ ...option, start: null, end: null, label: null }, locale)}`"
                                @update:model-value="
                                    update(index, { end: $event })
                                "
                            />
                        </div>
                    </div>
                    <!-- Sits beside the range from lg up and below it on narrower screens, always inside the row -->
                    <button
                        v-if="canApplyToAll(option)"
                        type="button"
                        class="text-primary hover:text-primary-hover min-h-4 px-0.5 text-[13px] leading-4 font-semibold whitespace-nowrap underline underline-offset-[3px]"
                        @click="applyTimeToAll(option)"
                    >
                        {{ t('newPoll.applyTimeToAll') }}
                    </button>
                </div>
                <div
                    :class="
                        cn(
                            'flex min-w-0 flex-col gap-1',
                            mode === 'day'
                                ? 'order-1 col-span-2 sm:order-none sm:col-span-1'
                                : 'max-lg:order-2',
                        )
                    "
                >
                    <Label :for="`${idPrefix}-${index}-label`">{{
                        t('newPoll.slotLabel')
                    }}</Label>
                    <Input
                        :id="`${idPrefix}-${index}-label`"
                        type="text"
                        :model-value="option.label"
                        :disabled="disabled"
                        :placeholder="t('newPoll.slotLabelPlaceholder')"
                        class="bg-surface-raised h-9"
                        @update:model-value="
                            update(index, { label: String($event) })
                        "
                    />
                    <!-- Reserved so an error never shifts the row -->
                    <span class="text-destructive -mt-1 min-h-4 text-xs">{{
                        errors?.[index]
                    }}</span>
                </div>
                <span
                    v-if="showMeta"
                    :class="
                        cn(
                            'text-fg-muted flex items-center gap-1.5 text-xs whitespace-nowrap',
                            mode === 'slots'
                                ? 'max-lg:hidden'
                                : 'max-sm:hidden',
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
                    :class="
                        mode === 'slots' ? 'max-lg:hidden' : 'max-sm:hidden'
                    "
                    aria-hidden="true"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="justify-self-end"
                    :disabled="disabled || !!option.fixed"
                    :aria-label="`${t('newPoll.removeOption')}: ${describeOption(option, locale)}`"
                    @click="remove(index)"
                >
                    <Minus />
                </Button>
            </li>
        </ul>
    </div>
</template>
