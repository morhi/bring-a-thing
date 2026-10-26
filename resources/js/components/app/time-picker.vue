<script setup lang="ts">
import { XIcon } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/use-i18n';

/**
 * Pick a 24-hour time from an hour and a minute segment; the model is "HH:MM" or null.
 *
 * Both segments are selects, so arrow keys and typing ("1", "4" for 14) work. Choosing
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

const triggerClass =
    'bg-control border-control-border text-fg hover:border-fg-soft focus-visible:border-primary-ring aria-invalid:border-danger data-placeholder:text-fg-soft ' +
    'h-(--control-height)! w-[4.75rem] rounded-(--control-radius) px-3 text-sm tabular-nums focus-visible:ring-(--primary-ring)/25 disabled:opacity-100 disabled:bg-surface-muted disabled:text-fg-muted disabled:border-border';
</script>

<template>
    <div
        class="flex items-center gap-1.5"
        role="group"
        :aria-label="ariaLabel || undefined"
        :aria-labelledby="ariaLabel ? undefined : `${id}-label`"
        :aria-describedby="describedBy || undefined"
    >
        <Select
            :model-value="hour"
            :disabled="disabled"
            @update:model-value="setHour"
        >
            <SelectTrigger
                :id="id"
                :class="triggerClass"
                :aria-label="t('timePicker.hours')"
                :aria-invalid="invalid || undefined"
                :aria-required="required || undefined"
            >
                <SelectValue placeholder="--" />
            </SelectTrigger>
            <SelectContent class="max-h-64">
                <SelectItem
                    v-for="option in hours"
                    :key="option"
                    :value="option"
                    class="tabular-nums"
                    >{{ option }}</SelectItem
                >
            </SelectContent>
        </Select>

        <span class="text-fg-muted font-semibold" aria-hidden="true">:</span>

        <Select
            :model-value="minute"
            :disabled="disabled"
            @update:model-value="setMinute"
        >
            <SelectTrigger
                :class="triggerClass"
                :aria-label="t('timePicker.minutes')"
                :aria-invalid="invalid || undefined"
                :aria-required="required || undefined"
            >
                <SelectValue placeholder="--" />
            </SelectTrigger>
            <SelectContent class="max-h-64">
                <SelectItem
                    v-for="option in minutes"
                    :key="option"
                    :value="option"
                    class="tabular-nums"
                    >{{ option }}</SelectItem
                >
            </SelectContent>
        </Select>

        <button
            v-if="!required && (hour || minute) && !disabled"
            type="button"
            :aria-label="t('timePicker.clear')"
            class="text-fg-muted hover:bg-surface-sunken hover:text-fg grid size-9 shrink-0 place-items-center rounded-full"
            @click="clear"
        >
            <XIcon class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
