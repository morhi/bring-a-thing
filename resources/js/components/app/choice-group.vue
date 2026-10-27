<script setup lang="ts">
import {
    CheckboxRoot,
    CheckboxIndicator,
    RadioGroupIndicator,
    RadioGroupItem,
    RadioGroupRoot,
} from 'reka-ui';
import { CheckIcon } from '@lucide/vue';
import { computed } from 'vue';

export type ChoiceOption = {
    value: string;
    title: string;
    hint?: string | null;
    feature?: string | null;
    disabled?: boolean;
};

/**
 * Render radio or checkbox choices as cards.
 *
 * Radio mode (default) takes a string model and moves with the arrow keys like a
 * native radio group; `multiple` switches to checkboxes with a string array model,
 * each card toggled with Space. Wrap it in a FormField with `group` for the label.
 */
const props = withDefaults(
    defineProps<{
        options: ChoiceOption[];
        name: string;
        multiple?: boolean;
        disabled?: boolean;
        invalid?: boolean;
        required?: boolean;
    }>(),
    { multiple: false, disabled: false, invalid: false, required: false },
);

const model = defineModel<string | string[] | null>({ default: null });

const selected = computed<string[]>(() => {
    if (Array.isArray(model.value)) {
        return model.value;
    }

    return model.value ? [model.value] : [];
});

const radioValue = computed(() =>
    Array.isArray(model.value) ? (model.value[0] ?? '') : (model.value ?? ''),
);

function toggle(value: string, checked: boolean | 'indeterminate'): void {
    const rest = selected.value.filter((entry) => entry !== value);

    model.value = checked === true ? [...rest, value] : rest;
}

function optionId(value: string, part: string): string {
    return `${props.name}-${value}-${part}`;
}

const cardClass =
    'relative flex cursor-pointer flex-col gap-1 rounded-xl border-2 border-depth-2-border bg-depth-2 py-3.5 pr-12 pl-5 transition-colors ' +
    'hover:border-fg-soft has-[[data-state=checked]]:border-primary has-[[data-state=checked]]:bg-primary-soft has-[[data-state=checked]]:shadow-[inset_0_0_0_1px_var(--primary)] ' +
    'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-focus-ring has-focus-visible:ring-2 has-focus-visible:ring-ring ' +
    'has-disabled:cursor-not-allowed has-disabled:opacity-60 has-disabled:hover:border-depth-2-border';

const indicatorClass =
    'absolute top-4 right-4 flex size-5 shrink-0 items-center justify-center border border-control-border bg-control text-primary-fg outline-none ' +
    'data-[state=checked]:border-primary data-[state=checked]:bg-primary disabled:cursor-not-allowed ' +
    // The card label also toggles it, but the control itself still needs a 24 px target (WCAG 2.5.8)
    'after:absolute after:-inset-1';
</script>

<template>
    <RadioGroupRoot
        v-if="!multiple"
        :model-value="radioValue"
        :name="name"
        :disabled="disabled"
        :required="required"
        :aria-invalid="invalid || undefined"
        class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,180px),1fr))] gap-2.5"
        @update:model-value="(value) => (model = String(value))"
    >
        <label
            v-for="option in options"
            :key="option.value"
            :class="[cardClass, invalid && 'border-danger']"
            :data-feature="option.feature || undefined"
        >
            <RadioGroupItem
                :value="option.value"
                :disabled="option.disabled"
                :aria-labelledby="optionId(option.value, 'title')"
                :aria-describedby="
                    option.hint ? optionId(option.value, 'hint') : undefined
                "
                :class="[indicatorClass, 'rounded-full']"
            >
                <RadioGroupIndicator
                    class="bg-primary-fg size-2 rounded-full"
                />
            </RadioGroupItem>
            <span
                :id="optionId(option.value, 'title')"
                class="text-[15px] font-semibold break-words"
                >{{ option.title }}</span
            >
            <span
                v-if="option.hint"
                :id="optionId(option.value, 'hint')"
                class="text-fg-muted text-[13px]"
                >{{ option.hint }}</span
            >
        </label>
    </RadioGroupRoot>

    <div
        v-else
        class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,180px),1fr))] gap-2.5"
        :aria-invalid="invalid || undefined"
    >
        <label
            v-for="option in options"
            :key="option.value"
            :class="[cardClass, invalid && 'border-danger']"
            :data-feature="option.feature || undefined"
        >
            <CheckboxRoot
                :model-value="selected.includes(option.value)"
                :name="name"
                :value="option.value"
                :disabled="disabled || option.disabled"
                :aria-labelledby="optionId(option.value, 'title')"
                :aria-describedby="
                    option.hint ? optionId(option.value, 'hint') : undefined
                "
                :class="[indicatorClass, 'rounded-md']"
                @update:model-value="(checked) => toggle(option.value, checked)"
            >
                <CheckboxIndicator>
                    <CheckIcon class="size-3.5" />
                </CheckboxIndicator>
            </CheckboxRoot>
            <span
                :id="optionId(option.value, 'title')"
                class="text-[15px] font-semibold break-words"
                >{{ option.title }}</span
            >
            <span
                v-if="option.hint"
                :id="optionId(option.value, 'hint')"
                class="text-fg-muted text-[13px]"
                >{{ option.hint }}</span
            >
        </label>
    </div>
</template>
