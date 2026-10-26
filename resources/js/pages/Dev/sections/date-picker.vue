<script setup lang="ts">
import { ref } from 'vue';
import DatePicker from '@/components/app/date-picker.vue';
import FormField from '@/components/app/form-field.vue';

const date = ref<string | null>('2026-10-31');
const optional = ref<string | null>(null);
const bounded = ref<string | null>('2026-11-03');
</script>

<template>
    <section class="space-y-6">
        <h2 id="date-picker" class="font-display text-2xl">DatePicker</h2>
        <p class="text-fg-muted text-sm">
            Enter or Space opens the calendar, arrow keys move by day and week,
            Page Up and Page Down by month, Enter picks, Escape closes. The
            value is an ISO date.
        </p>

        <div class="box grid gap-(--space-stack) p-6">
            <FormField
                label="Date"
                control="dev-dp-required"
                required
                :hint="`Required, no clear button. Value: ${date}`"
            >
                <template #default="{ id, describedBy }">
                    <DatePicker
                        :id="id"
                        v-model="date"
                        required
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <FormField
                label="List date"
                control="dev-dp-optional"
                :hint="`Optional and empty, clear button after picking. Value: ${optional}`"
            >
                <template #default="{ id, describedBy }">
                    <DatePicker
                        :id="id"
                        v-model="optional"
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <FormField
                label="Date"
                control="dev-dp-bounded"
                hint="Limited to 1 Nov to 30 Nov 2026 (min and max arrive as props)."
            >
                <template #default="{ id, describedBy }">
                    <DatePicker
                        :id="id"
                        v-model="bounded"
                        min="2026-11-01"
                        max="2026-11-30"
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <FormField
                label="Date"
                control="dev-dp-error"
                required
                error="Pick a date."
            >
                <template #default="{ id, describedBy, invalid }">
                    <DatePicker
                        :id="id"
                        :model-value="null"
                        required
                        :invalid="invalid"
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <FormField label="Date" control="dev-dp-disabled" hint="Disabled.">
                <template #default="{ id, describedBy }">
                    <DatePicker
                        :id="id"
                        model-value="2026-12-24"
                        disabled
                        :described-by="describedBy"
                    />
                </template>
            </FormField>

            <div class="space-y-2">
                <p class="text-fg-muted text-sm">
                    Without a FormField, named by aria-label (option rows in the
                    poll editor):
                </p>
                <DatePicker
                    id="dev-dp-bare"
                    v-model="optional"
                    aria-label="Date 1"
                />
            </div>
        </div>
    </section>
</template>
