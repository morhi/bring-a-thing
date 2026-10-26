<script setup lang="ts">
import { ref } from 'vue';
import FormField from '@/components/app/form-field.vue';
import TimePicker from '@/components/app/time-picker.vue';

const start = ref<string | null>('18:30');
const optional = ref<string | null>(null);
const offGrid = ref<string | null>('09:07');
const slotStart = ref<string | null>('10:00');
const slotEnd = ref<string | null>('12:00');
</script>

<template>
    <section class="space-y-6">
        <h2 id="time-picker" class="font-display text-2xl">TimePicker</h2>
        <p class="text-fg-muted text-sm">
            24-hour, two segments. Tab moves between hours and minutes, Enter or
            arrow keys open a segment, typing digits jumps to a value. The value
            is "HH:MM" or null.
        </p>

        <div class="box grid gap-(--space-stack) p-6">
            <FormField
                label="Start time"
                control="dev-tp-required"
                required
                :hint="`Required. Value: ${start}`"
            >
                <template #default="{ id, describedBy }">
                    <TimePicker
                        :id="id"
                        v-model="start"
                        required
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <FormField
                label="Time"
                control="dev-tp-optional"
                :hint="`Optional and empty; picking an hour fills 00 minutes. Value: ${optional}`"
            >
                <template #default="{ id, describedBy }">
                    <TimePicker
                        :id="id"
                        v-model="optional"
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <FormField
                label="Time"
                control="dev-tp-step"
                :hint="`15-minute step; a stored value off the grid stays selectable. Value: ${offGrid}`"
            >
                <template #default="{ id, describedBy }">
                    <TimePicker
                        :id="id"
                        v-model="offGrid"
                        :minute-step="15"
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <FormField
                label="End time"
                control="dev-tp-error"
                required
                error="The end must be after the start."
            >
                <template #default="{ id, describedBy, invalid }">
                    <TimePicker
                        :id="id"
                        model-value="08:00"
                        required
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <FormField label="Time" control="dev-tp-disabled" hint="Disabled.">
                <template #default="{ id, describedBy }">
                    <TimePicker
                        :id="id"
                        model-value="20:00"
                        disabled
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <div class="space-y-2">
                <p class="text-fg-muted text-sm">
                    Without a FormField, named by aria-label (slot rows in the
                    poll editor):
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <TimePicker
                        id="dev-tp-slot-start"
                        v-model="slotStart"
                        aria-label="Start"
                    />
                    <TimePicker
                        id="dev-tp-slot-end"
                        v-model="slotEnd"
                        aria-label="End"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
