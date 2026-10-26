<script setup lang="ts">
import { ref } from 'vue';
import ChoiceGroup from '@/components/app/choice-group.vue';
import type { ChoiceOption } from '@/components/app/choice-group.vue';
import FormField from '@/components/app/form-field.vue';

const typeOptions: ChoiceOption[] = [
    {
        value: 'date_finder',
        title: 'Find a date',
        hint: 'People mark which dates work for them.',
        feature: 'F-06',
    },
    {
        value: 'attendance',
        title: 'Attendance',
        hint: 'People say on which days they come.',
        feature: 'F-06',
    },
];

const longOptions: ChoiceOption[] = [
    {
        value: 'open',
        title: 'Anyone with the link can join right away with only a name, without logging in first',
        hint: 'Good for big, informal groups. You can close the link again at any time in the group settings.',
    },
    {
        value: 'closed',
        title: 'Login first',
        hint: 'People log in with their email before they join.',
    },
    {
        value: 'off',
        title: 'No link',
        disabled: true,
        hint: 'Not available for this group.',
    },
];

const daysOptions: ChoiceOption[] = [
    { value: 'fri', title: 'Friday' },
    { value: 'sat', title: 'Saturday' },
    { value: 'sun', title: 'Sunday', hint: 'Only until noon' },
];

const type = ref<string | string[] | null>('date_finder');
const empty = ref<string | string[] | null>(null);
const lock = ref<string | string[] | null>('closed');
const days = ref<string | string[] | null>(['sat']);
</script>

<template>
    <section class="space-y-6">
        <h2 id="choice-group" class="font-display text-2xl">ChoiceGroup</h2>
        <p class="text-fg-muted text-sm">
            Radio cards move with the arrow keys; checkbox cards are separate
            tab stops toggled with Space.
        </p>

        <div class="box grid gap-(--space-stack) p-6">
            <FormField
                label="Poll type"
                control="dev-cg-type"
                group
                hint="Radio, default."
            >
                <ChoiceGroup
                    v-model="type"
                    name="dev-cg-type"
                    :options="typeOptions"
                />
            </FormField>

            <FormField
                label="Poll type"
                control="dev-cg-error"
                group
                required
                error="Choose a poll type."
            >
                <ChoiceGroup
                    v-model="empty"
                    name="dev-cg-error"
                    :options="typeOptions"
                    invalid
                    required
                />
            </FormField>

            <FormField
                label="Invite link"
                control="dev-cg-long"
                group
                hint="Long titles and hints wrap; the third card is disabled."
            >
                <ChoiceGroup
                    v-model="lock"
                    name="dev-cg-long"
                    :options="longOptions"
                />
            </FormField>

            <FormField
                label="Poll type"
                control="dev-cg-disabled"
                group
                hint="The whole group disabled."
            >
                <ChoiceGroup
                    model-value="attendance"
                    name="dev-cg-disabled"
                    :options="typeOptions"
                    disabled
                />
            </FormField>

            <FormField
                label="Days you come"
                control="dev-cg-multiple"
                group
                :hint="`Checkbox mode, value: ${JSON.stringify(days)}`"
            >
                <ChoiceGroup
                    v-model="days"
                    name="dev-cg-multiple"
                    :options="daysOptions"
                    multiple
                />
            </FormField>
        </div>
    </section>
</template>
