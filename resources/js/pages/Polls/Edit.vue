<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import ArrowLeft from '@primeicons/vue/arrow-left';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    formatDateOnly,
    formatPollOption,
    formatTimeOnly,
} from '@/lib/pollDate';
import type { Poll, PollOption } from '@/types';
import {
    close as closePoll,
    destroy as destroyPoll,
    reopen as reopenPoll,
    show as showPoll,
    update as updatePoll,
} from '@/actions/App/Http/Controllers/PollController';
import {
    destroy as destroyOption,
    store as storeOption,
} from '@/actions/App/Http/Controllers/PollOptionController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    poll: Poll;
}>();

const confirm = useConfirm();

const form = useForm({ title: props.poll.title });

function submit() {
    form.patch(updatePoll(props.poll).url);
}

function confirmDestroy() {
    confirm.require({
        header: 'Delete poll?',
        message: `Deleting "${props.poll.title}" removes it, its options, and every response. This cannot be undone.`,
        acceptLabel: 'Delete poll',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () => form.delete(destroyPoll(props.poll).url),
    });
}

function confirmDeleteOption(option: PollOption) {
    confirm.require({
        header: 'Remove option?',
        message: 'Removing this option also removes every response on it.',
        acceptLabel: 'Remove',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () =>
            router.delete(destroyOption({ poll: props.poll, option }).url, {
                preserveScroll: true,
            }),
    });
}

const newOptionForm = useForm({
    date: null as Date | null,
    starts_at: null as Date | null,
    ends_at: null as Date | null,
    label: '',
});

function submitNewOption() {
    newOptionForm
        .transform((data) => ({
            date: data.date ? formatDateOnly(data.date) : null,
            starts_at: data.starts_at ? formatTimeOnly(data.starts_at) : null,
            ends_at: data.ends_at ? formatTimeOnly(data.ends_at) : null,
            label: data.label || null,
        }))
        .post(storeOption(props.poll).url, {
            preserveScroll: true,
            onSuccess: () => newOptionForm.reset(),
        });
}

const optionChoices = computed(
    () =>
        props.poll.options?.map((option) => ({
            id: option.id,
            label: formatPollOption(option),
        })) ?? [],
);

const closeForm = useForm({ option_id: null as number | null });

function submitClosePoll() {
    closeForm
        .transform((data) => ({
            option_id:
                props.poll.type === 'date_finder' ? data.option_id : null,
        }))
        .post(closePoll(props.poll).url, { preserveScroll: true });
}

function reopenPollNow() {
    router.delete(reopenPoll(props.poll).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${poll.title} settings`" />

    <div class="flex flex-col gap-6">
        <Link
            :href="showPoll(poll).url"
            class="text-surface-500 hover:text-surface-900 dark:hover:text-surface-0 flex items-center gap-2 text-sm"
        >
            <ArrowLeft style="width: 0.875rem; height: 0.875rem" />
            {{ poll.title }}
        </Link>

        <div class="flex items-center gap-2">
            <h1
                class="text-surface-900 dark:text-surface-0 text-xl font-semibold"
            >
                Poll settings
            </h1>
            <Tag v-if="poll.closed_at" value="Closed" severity="secondary" />
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <form class="flex max-w-md flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-2">
                    <label for="title" class="text-sm font-medium">Title</label>
                    <InputText
                        id="title"
                        v-model="form.title"
                        :invalid="!!form.errors.title"
                    />
                    <small v-if="form.errors.title" class="text-red-500">
                        {{ form.errors.title }}
                    </small>
                </div>

                <div class="flex gap-3">
                    <Button
                        type="submit"
                        label="Save"
                        :loading="form.processing"
                    />
                    <Button
                        type="button"
                        label="Cancel"
                        severity="secondary"
                        text
                        @click="router.visit(showPoll(poll).url)"
                    />
                </div>
            </form>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">Options</h2>
            <ul v-if="poll.options?.length" class="mb-4 flex flex-col gap-2">
                <li
                    v-for="option in poll.options"
                    :key="option.id"
                    class="flex max-w-md items-center gap-2"
                >
                    <span
                        class="text-surface-900 dark:text-surface-0 flex-1 text-sm"
                    >
                        {{ formatPollOption(option) }}
                    </span>
                    <Tag
                        v-if="option.id === poll.chosen_option_id"
                        value="Chosen"
                        severity="success"
                    />
                    <Tag
                        v-if="option.responses?.length"
                        :value="`${option.responses.length} response${option.responses.length === 1 ? '' : 's'}`"
                        severity="secondary"
                    />
                    <Button
                        v-if="!poll.closed_at"
                        label="Remove"
                        severity="danger"
                        text
                        size="small"
                        @click="confirmDeleteOption(option)"
                    />
                </li>
            </ul>
            <p v-else class="text-surface-500 mb-4 text-sm">No options yet.</p>

            <form
                v-if="!poll.closed_at"
                class="flex max-w-md flex-col gap-3"
                @submit.prevent="submitNewOption"
            >
                <div class="flex items-start gap-2">
                    <DatePicker
                        v-model="newOptionForm.date"
                        placeholder="Date"
                        date-format="yy-mm-dd"
                        show-icon
                        class="flex-1"
                        :invalid="!!newOptionForm.errors.date"
                    />
                </div>
                <div
                    v-if="poll.granularity === 'hour'"
                    class="flex items-start gap-2"
                >
                    <DatePicker
                        v-model="newOptionForm.starts_at"
                        time-only
                        placeholder="Start time"
                        class="flex-1"
                        :invalid="!!newOptionForm.errors.starts_at"
                    />
                    <DatePicker
                        v-model="newOptionForm.ends_at"
                        time-only
                        placeholder="End time"
                        class="flex-1"
                        :invalid="!!newOptionForm.errors.ends_at"
                    />
                    <InputText
                        v-model="newOptionForm.label"
                        placeholder="Label (e.g. Lunch)"
                        class="flex-1"
                    />
                </div>
                <small
                    v-if="Object.keys(newOptionForm.errors).length"
                    class="text-red-500"
                >
                    {{ Object.values(newOptionForm.errors)[0] }}
                </small>
                <Button
                    type="submit"
                    label="Add option"
                    :loading="newOptionForm.processing"
                    class="self-start"
                />
            </form>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-2 text-lg font-medium">Voting</h2>

            <template v-if="poll.closed_at">
                <p class="text-surface-500 mb-4 max-w-md text-sm">
                    This poll is closed. No new votes are accepted.
                    <template v-if="poll.chosen_option">
                        The chosen result is
                        {{ formatPollOption(poll.chosen_option) }}.
                    </template>
                </p>
                <Button
                    label="Reopen poll"
                    severity="secondary"
                    @click="reopenPollNow"
                />
            </template>
            <template v-else>
                <p class="text-surface-500 mb-4 max-w-md text-sm">
                    Closing a poll stops new votes.
                    <template v-if="poll.type === 'date_finder'">
                        Choose the result to record for a date-finder poll.
                    </template>
                    You can reopen it again at any time.
                </p>
                <form
                    class="flex max-w-md flex-col gap-3"
                    @submit.prevent="submitClosePoll"
                >
                    <Select
                        v-if="poll.type === 'date_finder'"
                        v-model="closeForm.option_id"
                        :options="optionChoices"
                        option-label="label"
                        option-value="id"
                        placeholder="Choose the result..."
                        :invalid="!!closeForm.errors.option_id"
                    />
                    <small
                        v-if="closeForm.errors.option_id"
                        class="text-red-500"
                    >
                        {{ closeForm.errors.option_id }}
                    </small>
                    <Button
                        type="submit"
                        label="Close poll"
                        severity="secondary"
                        :loading="closeForm.processing"
                        class="self-start"
                    />
                </form>
            </template>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-2 text-lg font-medium text-red-600">Danger zone</h2>
            <p class="text-surface-500 mb-4 max-w-md text-sm">
                Deleting a poll removes it, its options, and every response.
                This cannot be undone.
            </p>
            <Button
                label="Delete poll"
                severity="danger"
                @click="confirmDestroy"
            />
        </div>
    </div>
</template>
