<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { show as showEvent } from '@/actions/App/Http/Controllers/EventController';
import { store as storeFollowUpPoll } from '@/actions/App/Http/Controllers/EventFollowUpPollController';
import { show as showGroup } from '@/actions/App/Http/Controllers/GroupController';
import {
    close as closePoll,
    destroy as destroyPoll,
    reopen as reopenPoll,
    show as showPoll,
    store as storePoll,
    update as updatePoll,
} from '@/actions/App/Http/Controllers/PollController';
import ChoiceGroup from '@/components/app/choice-group.vue';
import type { ChoiceOption } from '@/components/app/choice-group.vue';
import FormField from '@/components/app/form-field.vue';
import FormPage from '@/components/app/form-page.vue';
import OptionCalendar from '@/components/app/option-calendar.vue';
import OptionRows from '@/components/app/poll/option-rows.vue';
import type {
    OptionCalendarMode,
    OptionDraft,
} from '@/components/app/poll/types';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ form: App.Data.PollFormData }>();

const { t } = useI18n();

// The same page serves "New poll", "Follow-up poll" and, with a poll, "Edit poll" (F-06, F-08, F-12)
const poll = computed(() => props.form.poll);
const editing = computed(() => poll.value !== null);
const closed = computed(() => !!poll.value?.is_closed);
const sourceEvent = computed(() => props.form.source_event);

function draftsOf(pollData: App.Data.PollData | null): OptionDraft[] {
    return (pollData?.options ?? []).map((option) => ({
        id: option.slug,
        date: option.date,
        start: option.starts_at,
        end: option.ends_at,
        label: option.label ?? '',
        locked: option.is_schedule_locked,
        fixed: option.is_fixed,
        answerCount: option.yes_count + option.maybe_count + option.no_count,
    }));
}

const type = ref<App.Enums.PollType>(poll.value?.type ?? 'date_finder');
// The calendar speaks "day" and "slots"; the backend stores the same choice as granularity "day" and "hour"
const mode = ref<OptionCalendarMode>(
    poll.value?.uses_time_slots ? 'slots' : 'day',
);
const options = ref<OptionDraft[]>(draftsOf(poll.value));
const defaultSlot = computed(() => ({
    start: props.form.default_slot_start,
    end: props.form.default_slot_end,
}));

// Switching granularity converts what is there instead of discarding it; only possible before creation
watch(mode, (next) => {
    if (next === 'day') {
        const seen = new Set<string>();
        options.value = options.value
            .filter(
                (option) =>
                    option.date &&
                    !seen.has(option.date) &&
                    seen.add(option.date),
            )
            .map((option) => ({
                date: option.date,
                start: null,
                end: null,
                label: option.label,
            }));

        return;
    }

    options.value = options.value.map((option) => ({
        date: option.date,
        start: option.start ?? defaultSlot.value.start,
        end: option.end ?? defaultSlot.value.end,
        label: option.label,
    }));
});

const validCount = computed(
    () =>
        options.value.filter(
            (option) =>
                option.date &&
                (mode.value === 'day' || (option.start && option.end)),
        ).length,
);

const typeOptions = computed<ChoiceOption[]>(() => [
    {
        value: 'date_finder',
        title: t('newPoll.type.dateFinder'),
        hint: t('newPoll.type.dateFinderHint'),
        feature: 'F-06',
    },
    {
        value: 'attendance',
        title: t('newPoll.type.attendance'),
        hint: t('newPoll.type.attendanceHint'),
        feature: 'F-07',
    },
]);

const modeOptions = computed<ChoiceOption[]>(() => [
    {
        value: 'day',
        title: t('newPoll.mode.day'),
        hint: t('newPoll.mode.dayHint'),
    },
    {
        value: 'slots',
        title: t('newPoll.mode.slots'),
        hint: t('newPoll.mode.slotsHint'),
    },
]);

const pageTitle = computed(() => {
    if (editing.value) {
        return t('poll.edit.title');
    }

    return sourceEvent.value ? t('newPoll.followUpTitle') : t('newPoll.title');
});

const subtitle = computed(() => {
    if (poll.value) {
        return poll.value.title;
    }

    return sourceEvent.value ? t('newPoll.followUpSub') : t('newPoll.sub');
});

// Cancel and back return to where the page was opened from
const back = computed(() => {
    if (poll.value) {
        return { href: showPoll(poll.value.slug).url, name: poll.value.title };
    }

    if (sourceEvent.value) {
        return {
            href: showEvent(sourceEvent.value.slug).url,
            name: sourceEvent.value.title,
        };
    }

    return {
        href: showGroup(props.form.group_slug).url,
        name: props.form.group_name,
    };
});

const status = computed(() => {
    if (!closed.value) {
        return { label: t('group.polls.open'), variant: 'default' as const };
    }

    if (poll.value?.options.some((option) => option.is_fixed)) {
        return { label: t('group.polls.fixed'), variant: 'secondary' as const };
    }

    return { label: t('group.polls.closed'), variant: 'outline' as const };
});

// A date finder closes by fixing dates, unless it already has a fixed option (F-08, F-09)
const canClose = computed(
    () =>
        !!poll.value &&
        !closed.value &&
        (poll.value.type === 'attendance' ||
            poll.value.options.some((option) => option.is_fixed)),
);

const optionsHint = computed(() => {
    if (closed.value) {
        return t('poll.settings.optionsLocked');
    }

    return editing.value ? t('poll.edit.optionsHint') : null;
});

const pollForm = useForm({ title: poll.value?.title ?? '' });
const optionsError = ref<string | null>(null);

const optionsServerError = computed(() => {
    const key = Object.keys(pollForm.errors).find(
        (name) => name.startsWith('options') || name.startsWith('remove'),
    );

    return key ? (pollForm.errors as Record<string, string>)[key] : null;
});

function optionRows(withSlug: boolean): Record<string, string | null>[] {
    return options.value.map((option) => ({
        ...(withSlug && option.id ? { slug: String(option.id) } : {}),
        date: option.date,
        ...(mode.value === 'slots'
            ? { starts_at: option.start, ends_at: option.end }
            : {}),
        label: option.label.trim() || null,
    }));
}

function save(): void {
    optionsError.value = null;

    if (!closed.value && options.value.length === 0) {
        optionsError.value = t('newPoll.optionsRequired');

        // The early return skips the server, so the title error has to come from here too
        if (!pollForm.title.trim()) {
            pollForm.setError('title', t('newPoll.titleRequired'));
        }

        return;
    }

    if (poll.value) {
        update(poll.value);

        return;
    }

    pollForm
        .transform((data) => ({
            ...data,
            type: type.value,
            granularity: mode.value === 'slots' ? 'hour' : 'day',
            options: optionRows(false),
        }))
        .post(
            sourceEvent.value
                ? storeFollowUpPoll(sourceEvent.value.slug).url
                : storePoll(props.form.group_slug).url,
        );
}

// A closed poll keeps its options (F-08), so only the title is sent then
function update(current: App.Data.PollData): void {
    const keptSlugs = options.value.map((option) => String(option.id ?? ''));
    const remove = current.options
        .map((option) => option.slug)
        .filter((slug) => !keptSlugs.includes(slug));

    pollForm
        .transform((data) =>
            closed.value
                ? data
                : { ...data, options: optionRows(true), remove },
        )
        .patch(updatePoll(current.slug).url);
}

// Options with answers lose them on removal, so the rows ask first (F-08)
const removing = ref<{ index: number; option: OptionDraft } | null>(null);
const removeOpen = ref(false);

function requestRemove(index: number, option: OptionDraft): void {
    removing.value = { index, option };
    removeOpen.value = true;
}

function confirmRemove(): void {
    const entry = removing.value;

    if (!entry) {
        return;
    }

    options.value = options.value.filter((option) => option !== entry.option);
    removing.value = null;
}

function showRequestError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? t('group.requestFailed'));
}

// Closing and reopening keep the unsaved title; the option rows follow the reloaded poll
function changeStatus(current: App.Data.PollData, reopen: boolean): void {
    const visit = {
        preserveScroll: true,
        preserveState: true,
        onError: showRequestError,
        onSuccess: () => (options.value = draftsOf(props.form.poll)),
    };

    if (reopen) {
        router.delete(reopenPoll(current.slug).url, visit);

        return;
    }

    router.post(closePoll(current.slug).url, {}, visit);
}

const totalAnswers = computed(() =>
    (poll.value?.options ?? []).reduce(
        (sum, option) =>
            sum + option.yes_count + option.maybe_count + option.no_count,
        0,
    ),
);
const deleteOpen = ref(false);

function deletePoll(current: App.Data.PollData): void {
    router.delete(destroyPoll(current.slug).url, { onError: showRequestError });
}

const buttonClass = 'h-(--control-height) px-4';
const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) max-w-(--field-max-width) rounded-(--control-radius) px-3.5 text-[15px]';
</script>

<template>
    <Head :title="poll ? t('poll.edit.title') : pageTitle" />

    <FormPage
        :title="pageTitle"
        :subtitle="subtitle"
        :back-href="back.href"
        :back-label="t('nav.backTo', { name: back.name })"
    >
        <form
            id="poll-form"
            class="flex flex-col gap-8"
            novalidate
            :data-feature="editing ? 'F-08' : 'F-06'"
            @submit.prevent="save"
        >
            <div
                class="bg-primary-soft text-primary-soft-fg flex items-center gap-3 rounded-md px-3.5 py-3 text-sm"
                :data-feature="sourceEvent ? 'F-12' : 'F-06'"
            >
                <Users class="size-6 flex-none" aria-hidden="true" />
                <span class="flex min-w-0 flex-1 flex-col gap-px">
                    <b v-if="sourceEvent" class="font-semibold">{{
                        t('newPoll.audienceEvent', { event: sourceEvent.title })
                    }}</b>
                    <b v-else class="font-semibold">{{
                        t('newPoll.audienceGroup', { group: form.group_name })
                    }}</b>
                    <span>{{
                        sourceEvent
                            ? t('newPoll.audienceEventCount', {
                                  n: form.member_count,
                              })
                            : t('newPoll.audienceGroupCount', {
                                  n: form.member_count,
                              })
                    }}</span>
                </span>
            </div>

            <FormField
                :label="t('newPoll.name')"
                control="poll-title"
                required
                :error="pollForm.errors.title"
            >
                <template #default="{ describedBy, invalid }">
                    <Input
                        id="poll-title"
                        v-model="pollForm.title"
                        required
                        autocomplete="off"
                        :placeholder="t('newPoll.namePlaceholder')"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid || undefined"
                        :class="inputClass"
                        @input="pollForm.clearErrors('title')"
                    />
                </template>
            </FormField>

            <div
                v-if="poll"
                class="flex flex-wrap gap-2"
                :title="t('newPoll.modeLocked')"
            >
                <Badge variant="outline">{{
                    poll.type === 'attendance'
                        ? t('group.polls.attendance')
                        : t('group.polls.dateFinder')
                }}</Badge>
                <Badge variant="outline">{{
                    mode === 'slots'
                        ? t('newPoll.mode.slots')
                        : t('newPoll.mode.day')
                }}</Badge>
                <span class="sr-only">{{ t('newPoll.modeLocked') }}</span>
            </div>

            <template v-else>
                <FormField :label="t('newPoll.type')" control="poll-type" group>
                    <ChoiceGroup
                        v-model="type"
                        name="poll-type"
                        :options="typeOptions"
                    />
                </FormField>

                <FormField :label="t('newPoll.mode')" control="poll-mode" group>
                    <ChoiceGroup
                        v-model="mode"
                        name="poll-mode"
                        :options="modeOptions"
                    />
                </FormField>
            </template>

            <FormField
                v-if="poll"
                :label="t('poll.edit.status')"
                control="poll-status"
                group
                data-feature="F-08"
            >
                <div class="flex flex-wrap items-center gap-2.5">
                    <Badge :variant="status.variant">{{ status.label }}</Badge>
                    <Button
                        v-if="closed"
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="changeStatus(poll, true)"
                        >{{ t('poll.actions.reopen') }}</Button
                    >
                    <Button
                        v-else-if="canClose"
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="changeStatus(poll, false)"
                        >{{ t('poll.actions.close') }}</Button
                    >
                    <span v-else class="text-fg-muted text-sm">{{
                        t('poll.edit.closesByFixing')
                    }}</span>
                </div>
            </FormField>

            <FormField
                v-if="!closed"
                :label="t('newPoll.options')"
                control="poll-options"
                group
                required
                :error="optionsError ?? optionsServerError"
            >
                <OptionCalendar
                    v-model="options"
                    :mode="mode"
                    :today="form.today"
                    :default-slot="defaultSlot"
                />
            </FormField>

            <FormField
                :label="t('newPoll.selected', { n: validCount })"
                control="poll-rows"
                group
                :hint="optionsHint"
            >
                <OptionRows
                    v-model="options"
                    :mode="mode"
                    :show-meta="editing"
                    :disabled="closed"
                    id-prefix="poll-option"
                    @request-remove="requestRemove"
                />
            </FormField>
        </form>

        <template v-if="poll">
            <hr class="border-border border-t" />
            <section
                class="flex flex-col items-start gap-2.5"
                aria-labelledby="poll-delete-title"
                data-feature="F-08"
            >
                <h2 id="poll-delete-title" class="text-h2">
                    {{ t('poll.settings.delete') }}
                </h2>
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    @click="deleteOpen = true"
                    >{{ t('poll.settings.delete') }}</Button
                >
            </section>
        </template>

        <template #actions>
            <Button as-child variant="outline" size="lg" :class="buttonClass">
                <Link :href="back.href">{{ t('dialog.cancel') }}</Link>
            </Button>
            <Button
                type="submit"
                form="poll-form"
                size="lg"
                :class="buttonClass"
                :disabled="pollForm.processing"
            >
                {{ editing ? t('poll.edit.save') : t('newPoll.create') }}
            </Button>
        </template>
    </FormPage>

    <AlertDialog v-model:open="removeOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t('poll.settings.removeTitle')
                }}</AlertDialogTitle>
                <AlertDialogDescription>{{
                    t('poll.settings.removeConfirm', {
                        n: removing?.option.answerCount ?? 0,
                    })
                }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{
                    t('poll.settings.keep')
                }}</AlertDialogCancel>
                <AlertDialogAction
                    variant="destructive"
                    @click="confirmRemove"
                    >{{ t('poll.settings.remove') }}</AlertDialogAction
                >
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <AlertDialog v-if="poll" v-model:open="deleteOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t('poll.settings.delete')
                }}</AlertDialogTitle>
                <AlertDialogDescription>{{
                    t('poll.settings.deleteConfirm', {
                        title: poll.title,
                        n: totalAnswers,
                    })
                }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('dialog.cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    variant="destructive"
                    @click="deletePoll(poll)"
                    >{{ t('poll.settings.delete') }}</AlertDialogAction
                >
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
