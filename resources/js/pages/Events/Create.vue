<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { TriangleAlert, Users } from '@lucide/vue';
import { computed } from 'vue';
import {
    show as showEvent,
    store as storeEvent,
    update as updateEvent,
} from '@/actions/App/Http/Controllers/EventController';
import { show as showGroup } from '@/actions/App/Http/Controllers/GroupController';
import DatePicker from '@/components/app/date-picker.vue';
import FormField from '@/components/app/form-field.vue';
import FormPage from '@/components/app/form-page.vue';
import TimePicker from '@/components/app/time-picker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ form: App.Data.EventFormData }>();

const { t } = useI18n();

// The same page serves "New event" and, with an event, its edit mode (F-10)
const editing = computed(() => props.form.event !== null);

const eventForm = useForm({
    title: props.form.event?.title ?? '',
    description: props.form.event?.description ?? '',
    location: props.form.event?.location ?? '',
    date: props.form.event?.date ?? null,
    starts_at: props.form.event?.starts_at ?? null,
    end_date: props.form.event?.end_date ?? null,
    ends_at: props.form.event?.ends_at ?? null,
});

// Moving the start resets every acceptance to pending, so the page says so before saving
const moved = computed(
    () =>
        editing.value &&
        (eventForm.date !== props.form.event?.date ||
            eventForm.starts_at !== props.form.event?.starts_at),
);

const backHref = computed(() =>
    props.form.event
        ? showEvent(props.form.event.slug).url
        : showGroup(props.form.group_slug).url,
);
const backLabel = computed(() =>
    props.form.event
        ? t('nav.backTo', { name: props.form.event.title })
        : t('event.back', { group: props.form.group_name }),
);

function submit(): void {
    const options = { preserveScroll: true };

    if (props.form.event) {
        eventForm.patch(updateEvent(props.form.event.slug).url, options);

        return;
    }

    eventForm.post(storeEvent(props.form.group_slug).url, options);
}

const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) max-w-(--field-max-width) rounded-(--control-radius) px-3.5 text-[15px]';
const buttonClass = 'h-(--control-height) px-4';
</script>

<template>
    <Head :title="editing ? t('event.edit.title') : t('newEvent.title')" />

    <FormPage
        :title="editing ? t('event.edit.title') : t('newEvent.title')"
        :subtitle="editing ? t('event.edit.dateHint') : t('newEvent.sub')"
        :back-href="backHref"
        :back-label="backLabel"
    >
        <form
            id="event-form"
            class="flex flex-col gap-(--space-stack)"
            novalidate
            data-feature="F-10"
            @submit.prevent="submit"
        >
            <FormField
                :label="t('newEvent.name')"
                control="event-title"
                required
                :error="eventForm.errors.title"
            >
                <template #default="{ describedBy, invalid }">
                    <Input
                        id="event-title"
                        v-model="eventForm.title"
                        required
                        autocomplete="off"
                        :placeholder="t('newEvent.namePlaceholder')"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid || undefined"
                        :class="inputClass"
                        @input="eventForm.clearErrors('title')"
                    />
                </template>
            </FormField>

            <div class="grid gap-(--space-stack) sm:grid-cols-2 sm:max-w-(--field-max-width)">
                <FormField
                    :label="t('newEvent.date')"
                    control="event-date"
                    required
                    :error="eventForm.errors.date"
                >
                    <template #default="{ describedBy, invalid }">
                        <DatePicker
                            id="event-date"
                            v-model="eventForm.date"
                            required
                            :described-by="describedBy"
                            :invalid="invalid"
                            @update:model-value="eventForm.clearErrors('date')"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="t('newEvent.time')"
                    control="event-time"
                    group
                    :hint="t('newEvent.timeHint')"
                    :error="eventForm.errors.starts_at"
                >
                    <template #default="{ describedBy, invalid }">
                        <TimePicker
                            id="event-time"
                            v-model="eventForm.starts_at"
                            :described-by="describedBy"
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="t('newEvent.end')"
                    control="event-end-date"
                    :hint="t('newEvent.timeHint')"
                    :error="eventForm.errors.end_date"
                >
                    <template #default="{ describedBy, invalid }">
                        <DatePicker
                            id="event-end-date"
                            v-model="eventForm.end_date"
                            :min="eventForm.date"
                            :described-by="describedBy"
                            :invalid="invalid"
                            @update:model-value="eventForm.clearErrors('end_date')"
                        />
                    </template>
                </FormField>
                <FormField
                    :label="t('newEvent.endTime')"
                    control="event-end-time"
                    group
                    :hint="t('newEvent.timeHint')"
                    :error="eventForm.errors.ends_at"
                >
                    <template #default="{ describedBy, invalid }">
                        <TimePicker
                            id="event-end-time"
                            v-model="eventForm.ends_at"
                            :described-by="describedBy"
                            :invalid="invalid"
                        />
                    </template>
                </FormField>
            </div>

            <FormField
                :label="t('newEvent.location')"
                control="event-location"
                :error="eventForm.errors.location"
            >
                <template #default="{ describedBy, invalid }">
                    <Input
                        id="event-location"
                        v-model="eventForm.location"
                        autocomplete="off"
                        :placeholder="t('newEvent.locationPlaceholder')"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid || undefined"
                        :class="inputClass"
                    />
                </template>
            </FormField>

            <FormField
                :label="t('newEvent.description')"
                control="event-description"
                :error="eventForm.errors.description"
            >
                <template #default="{ describedBy, invalid }">
                    <Textarea
                        id="event-description"
                        v-model="eventForm.description"
                        :placeholder="t('newEvent.descriptionPlaceholder')"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid || undefined"
                        class="bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft max-w-(--field-max-width) min-h-24 rounded-(--control-radius) px-3.5 py-2.5 text-[15px]"
                    />
                </template>
            </FormField>

            <div
                v-if="moved"
                class="bg-warning-soft text-warning flex max-w-(--field-max-width) items-center gap-3 rounded-md px-3.5 py-3 text-sm"
                role="status"
            >
                <TriangleAlert class="size-5 flex-none" aria-hidden="true" />
                <span>{{ t('event.edit.moveWarning') }}</span>
            </div>

            <div
                v-if="!editing"
                class="bg-primary-soft text-primary-soft-fg flex max-w-(--field-max-width) items-center gap-3 rounded-md px-3.5 py-3 text-sm"
                data-feature="F-11"
            >
                <Users class="size-6 flex-none" aria-hidden="true" />
                <b class="font-semibold">{{
                    t('newEvent.audience', { n: Math.max(form.member_count - 1, 0) })
                }}</b>
            </div>
        </form>

        <template #actions>
            <Button as-child variant="outline" size="lg" :class="buttonClass">
                <Link :href="backHref">{{ t('dialog.cancel') }}</Link>
            </Button>
            <Button
                type="submit"
                form="event-form"
                size="lg"
                :class="buttonClass"
                :disabled="eventForm.processing"
            >
                {{ editing ? t('event.edit.save') : t('newEvent.create') }}
            </Button>
        </template>
    </FormPage>
</template>
