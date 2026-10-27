<script setup lang="ts">
import { XIcon } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { NativeSelect } from '@/components/ui/native-select';
import { useI18n } from '@/composables/use-i18n';

/**
 * Pick a 24-hour time from an hour and a minute segment; the model is "HH:MM" or null.
 *
 * Both segments are native selects, so touch devices show their own wheel picker and
 * keyboards keep typing ("1", "4" for 14). Choosing
 * an hour first fills the minutes with 00. Optional fields get a clear button. The group
 * is named by the FormField label (`${id}-label`) or by `ariaLabel`.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        minuteStep?: number;
        disabled?: boolean;
        invalid?: boolean;
        required?: boolean;
        ariaLabel?: string | null;
        describedBy?: string | null;
    }>(),
    {
        minuteStep: 5,
        disabled: false,
        invalid: false,
        required: false,
        ariaLabel: null,
        describedBy: null,
    },
);

const model = defineModel<string | null>({ default: null });

const { t } = useI18n();

const hour = ref('');
const minute = ref('');

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

watch(
    model,
    (value) => {
        const match = /^(\d{1,2}):(\d{2})/.exec(value ?? '');

        if (!match) {
            hour.value = '';
            minute.value = '';

            return;
        }

        hour.value = pad(Number(match[1]));
        minute.value = match[2];
    },
    { immediate: true },
);

const hours = Array.from({ length: 24 }, (_, index) => pad(index));

const minutes = computed(() => {
    const step = props.minuteStep > 0 ? props.minuteStep : 1;
    const list = Array.from({ length: Math.ceil(60 / step) }, (_, index) =>
        pad(index * step),
    );

    // A stored value off the step grid (e.g. 14:07) stays selectable instead of being lost.
    if (minute.value && !list.includes(minute.value)) {
        return [...list, minute.value].sort();
    }

    return list;
});

function emitValue(): void {
    if (!hour.value) {
        return;
    }

    if (!minute.value) {
        minute.value = '00';
    }

    model.value = `${hour.value}:${minute.value}`;
}

function setHour(value: unknown): void {
    hour.value = String(value ?? '');
    emitValue();
}

function setMinute(value: unknown): void {
    minute.value = String(value ?? '');
    emitValue();
}

function clear(): void {
    hour.value = '';
    minute.value = '';
    model.value = null;
    // The clear button disappears with the value, so focus returns to the first segment.
    document.getElementById(props.id)?.focus();
}

const selectClass =
    'w-[5.25rem] [&_select]:bg-control [&_select]:border-control-border [&_select]:text-fg [&_select]:hover:border-fg-soft [&_select]:focus-visible:border-primary-ring ' +
    '[&_select]:aria-invalid:border-danger [&_select]:h-11 [&_select]:rounded-(--control-radius) [&_select]:pl-3 [&_select]:text-base [&_select]:tabular-nums ' +
    '[&_select]:disabled:bg-surface-muted [&_select]:disabled:text-fg-muted [&_select]:disabled:border-border md:[&_select]:h-(--control-height) md:[&_select]:text-sm';
</script>

<template>
    <div
        class="flex items-center gap-1.5"
        role="group"
        :aria-label="ariaLabel || undefined"
        :aria-labelledby="ariaLabel ? undefined : `${id}-label`"
        :aria-describedby="describedBy || undefined"
    >
        <NativeSelect
            :id="id"
            :model-value="hour"
            :disabled="disabled"
            :class="selectClass"
            :aria-label="t('timePicker.hours')"
            :aria-invalid="invalid || undefined"
            :aria-required="required || undefined"
            @update:model-value="setHour($event)"
        >
            <option value="" disabled>--</option>
            <option v-for="option in hours" :key="option" :value="option">
                {{ option }}
            </option>
        </NativeSelect>

        <span class="text-fg-muted font-semibold" aria-hidden="true">:</span>

        <NativeSelect
            :model-value="minute"
            :disabled="disabled"
            :class="selectClass"
            :aria-label="t('timePicker.minutes')"
            :aria-invalid="invalid || undefined"
            :aria-required="required || undefined"
            @update:model-value="setMinute($event)"
        >
            <option value="" disabled>--</option>
            <option v-for="option in minutes" :key="option" :value="option">
                {{ option }}
            </option>
        </NativeSelect>

        <button
            v-if="!required && (hour || minute) && !disabled"
            type="button"
            :aria-label="t('timePicker.clear')"
            class="text-fg-muted hover:bg-surface-sunken hover:text-fg grid size-11 shrink-0 place-items-center rounded-full md:size-9"
            @click="clear"
        >
            <XIcon class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
