<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/composables/use-i18n';

/**
 * Wrap a form control with label, then error or hint, on the form spacing contract.
 *
 * `control` is the id of the control; the label points at it and the hint or error
 * gets `${control}-hint` / `${control}-error`, so the control can reference them through
 * the slot prop `describedBy`. With `group` the label becomes the name of a role="group"
 * wrapper, for controls made of several inputs (choice cards, time segments).
 */
const props = withDefaults(
    defineProps<{
        label: string;
        control: string;
        hint?: string | null;
        error?: string | null;
        required?: boolean;
        group?: boolean;
    }>(),
    { hint: null, error: null, required: false, group: false },
);

const { t } = useI18n();

const labelId = computed(() => `${props.control}-label`);
const messageId = computed(() =>
    props.error ? `${props.control}-error` : `${props.control}-hint`,
);
const describedBy = computed(() =>
    props.error || props.hint ? messageId.value : undefined,
);
</script>

<template>
    <div
        class="flex min-w-0 flex-col gap-(--space-label)"
        :role="group ? 'group' : undefined"
        :aria-labelledby="group ? labelId : undefined"
        :aria-describedby="group ? describedBy : undefined"
    >
        <component
            :is="group ? 'span' : 'label'"
            :id="labelId"
            :for="group ? undefined : control"
            class="text-fg text-sm font-semibold break-words"
        >
            {{ label }}
            <template v-if="required">
                <span class="text-danger" aria-hidden="true">*</span>
                <span class="sr-only">({{ t('field.required') }})</span>
            </template>
        </component>

        <slot
            v-bind="{ id: control, labelId, describedBy, invalid: !!error }"
        />

        <span
            v-if="error"
            :id="messageId"
            class="text-danger text-[13px] font-medium"
            role="alert"
            >{{ error }}</span
        >
        <span
            v-else-if="hint"
            :id="messageId"
            class="text-fg-soft text-[13px]"
            >{{ hint }}</span
        >
    </div>
</template>
