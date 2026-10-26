<script setup lang="ts">
import { parseDate } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { CalendarIcon, XIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useI18n } from '@/composables/use-i18n';

/**
 * Pick a single date from a calendar popover; the model is an ISO date string (YYYY-MM-DD).
 *
 * The trigger is named by the FormField label (`${id}-label`) plus the shown value,
 * or by `ariaLabel` when used without a FormField. Optional fields get a clear button.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        placeholder?: string | null;
        min?: string | null;
        max?: string | null;
        disabled?: boolean;
        invalid?: boolean;
        required?: boolean;
        ariaLabel?: string | null;
        describedBy?: string | null;
    }>(),
    {
        placeholder: null,
        min: null,
        max: null,
        disabled: false,
        invalid: false,
        required: false,
        ariaLabel: null,
        describedBy: null,
    },
);

const model = defineModel<string | null>({ default: null });

const { t, locale } = useI18n();

const open = ref(false);

const intlLocale = computed(() => (locale.value === 'de' ? 'de-CH' : 'en-GB'));

function toDateValue(iso: string | null): DateValue | undefined {
    if (!iso) {
        return undefined;
    }

    try {
        return parseDate(iso.slice(0, 10));
    } catch {
        return undefined;
    }
}

const value = computed(() => toDateValue(model.value));

const display = computed(() => {
    if (!value.value) {
        return '';
    }

    const options: Intl.DateTimeFormatOptions =
        locale.value === 'de'
            ? { day: '2-digit', month: '2-digit', year: 'numeric' }
            : { day: 'numeric', month: 'short', year: 'numeric' };

    return new Date(
        value.value.year,
        value.value.month - 1,
        value.value.day,
    ).toLocaleDateString(intlLocale.value, options);
});

const valueId = computed(() => `${props.id}-value`);
const labelledBy = computed(() =>
    props.ariaLabel ? undefined : `${props.id}-label ${valueId.value}`,
);

function select(next: DateValue | DateValue[] | undefined): void {
    if (!next || Array.isArray(next)) {
        return;
    }

    model.value = next.toString();
    open.value = false;
}

function clear(): void {
    model.value = null;
    // The clear button disappears with the value, so focus moves back to the trigger instead of the page body.
    document.getElementById(props.id)?.focus();
}
</script>

<template>
    <div class="flex w-full max-w-[220px] items-center gap-1">
        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <button
                    :id="id"
                    type="button"
                    :disabled="disabled"
                    :aria-label="
                        ariaLabel
                            ? `${ariaLabel}, ${display || placeholder || t('datePicker.placeholder')}`
                            : undefined
                    "
                    :aria-labelledby="labelledBy"
                    :aria-describedby="describedBy || undefined"
                    :aria-invalid="invalid || undefined"
                    :aria-required="required || undefined"
                    class="bg-control border-control-border text-fg hover:border-fg-soft focus-visible:border-primary-ring aria-invalid:border-danger disabled:bg-surface-muted disabled:text-fg-muted disabled:border-border flex h-(--control-height) w-full min-w-0 items-center gap-2 rounded-(--control-radius) border px-3.5 text-left text-sm transition-colors focus-visible:ring-3 focus-visible:ring-(--primary-ring)/25 focus-visible:outline-none disabled:cursor-not-allowed"
                >
                    <CalendarIcon
                        class="text-fg-muted size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span
                        :id="valueId"
                        class="truncate"
                        :class="!display && 'text-fg-soft'"
                        >{{
                            display ||
                            placeholder ||
                            t('datePicker.placeholder')
                        }}</span
                    >
                </button>
            </PopoverTrigger>
            <PopoverContent align="start" class="box-float w-auto p-2">
                <Calendar
                    :model-value="value"
                    :min-value="toDateValue(min)"
                    :max-value="toDateValue(max)"
                    :locale="intlLocale"
                    :week-starts-on="1"
                    prevent-deselect
                    initial-focus
                    class="[--cell-size:--spacing(9)]"
                    @update:model-value="select"
                />
            </PopoverContent>
        </Popover>

        <button
            v-if="!required && model && !disabled"
            type="button"
            :aria-label="t('datePicker.clear')"
            class="text-fg-muted hover:bg-surface-sunken hover:text-fg grid size-9 shrink-0 place-items-center rounded-full"
            @click="clear"
        >
            <XIcon class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
