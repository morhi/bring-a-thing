<script setup lang="ts">
import { ref } from 'vue';
import FormField from '@/components/app/form-field.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

const name = ref('Summer barbecue');
const empty = ref('');
const notes = ref('');

const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring h-(--control-height) max-w-(--field-max-width) rounded-(--control-radius) px-3.5 text-[15px]';
</script>

<template>
    <section class="space-y-6">
        <h2 id="form-field" class="font-display text-2xl">FormField</h2>
        <p class="text-fg-muted text-sm">
            Label, control, then error or hint. Tab through the fields; the hint
            or error is announced as the description.
        </p>

        <div class="box grid gap-(--space-stack) p-6">
            <FormField
                label="Group name"
                control="dev-ff-default"
                hint="Shown to everyone in the group."
            >
                <template #default="{ id, describedBy }">
                    <Input
                        :id="id"
                        v-model="name"
                        :aria-describedby="describedBy"
                        :class="inputClass"
                    />
                </template>
            </FormField>

            <FormField
                label="Title"
                control="dev-ff-required"
                required
                hint="Required fields carry an asterisk; optional fields carry nothing."
            >
                <template #default="{ id, describedBy }">
                    <Input
                        :id="id"
                        v-model="empty"
                        required
                        :aria-describedby="describedBy"
                        :class="inputClass"
                        placeholder="What is it about?"
                    />
                </template>
            </FormField>

            <FormField
                label="Title"
                control="dev-ff-error"
                required
                error="Enter a title."
            >
                <template #default="{ id, describedBy, invalid }">
                    <Input
                        :id="id"
                        model-value=""
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid || undefined"
                        :class="inputClass"
                    />
                </template>
            </FormField>

            <FormField
                label="Location"
                control="dev-ff-disabled"
                hint="Locked because the event is in the past."
            >
                <template #default="{ id, describedBy }">
                    <Input
                        :id="id"
                        model-value="Community garden, Lindenstrasse 12"
                        disabled
                        :aria-describedby="describedBy"
                        :class="inputClass"
                    />
                </template>
            </FormField>

            <FormField
                label="A very long label that explains in detail what the people in this group should write into the field below, so it has to wrap on narrow screens"
                control="dev-ff-long"
                hint="Long hints wrap as well and stay under the control, never beside it, even when the text runs over several lines at 375 pixels."
            >
                <template #default="{ id, describedBy }">
                    <Textarea
                        :id="id"
                        v-model="notes"
                        :aria-describedby="describedBy"
                        class="bg-control border-control-border max-w-(--field-max-width) rounded-(--control-radius)"
                    />
                </template>
            </FormField>
        </div>
    </section>
</template>
