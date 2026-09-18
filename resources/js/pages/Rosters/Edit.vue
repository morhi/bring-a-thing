<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import ArrowLeft from '@primeicons/vue/arrow-left';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatPollOption } from '@/lib/pollDate';
import type { CustomField, Roster } from '@/types';
import {
    destroy as destroyRoster,
    show as showRoster,
    update as updateRoster,
} from '@/actions/App/Http/Controllers/RosterController';
import {
    destroy as destroyCustomField,
    store as storeCustomField,
    update as updateCustomField,
} from '@/actions/App/Http/Controllers/CustomFieldController';
import {
    destroy as disableSharing,
    regenerate as regenerateShareLink,
    store as enableSharing,
} from '@/actions/App/Http/Controllers/RosterSharingController';
import { show as showSharedRoster } from '@/actions/App/Http/Controllers/SharedRosterController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    roster: Roster;
}>();

const confirm = useConfirm();
const toast = useToast();

// --- Sharing: a public link anyone can use to view and claim items ---
const shareUrl = computed(() =>
    props.roster.share_token
        ? `${window.location.origin}${showSharedRoster(props.roster.share_token).url}`
        : null,
);

function showSharingError(errors: Record<string, string>) {
    const message = Object.values(errors)[0] ?? 'Something went wrong.';
    toast.add({ severity: 'error', summary: message, life: 6000 });
}

function enableRosterSharing() {
    router.post(
        enableSharing(props.roster).url,
        {},
        { preserveScroll: true, onError: showSharingError },
    );
}

function confirmDisableSharing() {
    confirm.require({
        header: 'Disable sharing?',
        message: 'The current link will stop working immediately.',
        acceptLabel: 'Disable sharing',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () =>
            router.delete(disableSharing(props.roster).url, {
                preserveScroll: true,
                onError: showSharingError,
            }),
    });
}

function confirmRegenerateShareLink() {
    confirm.require({
        header: 'Regenerate link?',
        message:
            'The current link will stop working immediately, and a new one takes its place.',
        acceptLabel: 'Regenerate link',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () =>
            router.post(
                regenerateShareLink(props.roster).url,
                {},
                { preserveScroll: true, onError: showSharingError },
            ),
    });
}

async function copyShareUrl() {
    if (!shareUrl.value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(shareUrl.value);
        toast.add({ severity: 'success', summary: 'Link copied.', life: 3000 });
    } catch {
        toast.add({
            severity: 'error',
            summary: 'Could not copy the link.',
            life: 6000,
        });
    }
}

const form = useForm({
    title: props.roster.title,
    description: props.roster.description ?? '',
    date: props.roster.date,
    members_can_add_items: props.roster.members_can_add_items,
    attendance_poll_option_id: props.roster.attendance_poll_option_id,
});

// --- Attendance-day link, gates claim-eligibility warnings; only offered when the group has an attendance poll ---
const attendanceDayOptions = computed(() =>
    (props.roster.group?.polls ?? []).flatMap((poll) =>
        (poll.options ?? []).map((option) => ({
            id: option.id,
            label: `${poll.title}: ${formatPollOption(option)}`,
        })),
    ),
);

function submit() {
    form.patch(updateRoster(props.roster).url);
}

// Bridges form.date's string type with PrimeVue's Date-only DatePicker
// typing; update-model-type="string" makes the runtime value a string
// regardless of what the component's types declare.
const dateModel = computed<Date>({
    get: () => form.date as unknown as Date,
    set: (value) => {
        form.date = value as unknown as string | null;
    },
});

function confirmDestroy() {
    confirm.require({
        header: 'Delete roster?',
        message: `Deleting "${props.roster.title}" removes it for everyone. This cannot be undone.`,
        acceptLabel: 'Delete roster',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () => form.delete(destroyRoster(props.roster).url),
    });
}

// --- Custom fields management ---
const newFieldForm = useForm({ name: '' });

function submitNewField() {
    newFieldForm.post(storeCustomField(props.roster).url, {
        preserveScroll: true,
        onSuccess: () => newFieldForm.reset(),
    });
}

const editingFieldId = ref<number | null>(null);
const renameForm = useForm({ name: '' });

function startRename(field: CustomField) {
    editingFieldId.value = field.id;
    renameForm.name = field.name;
}

function submitRename(field: CustomField) {
    renameForm.patch(
        updateCustomField({ roster: props.roster, customField: field }).url,
        {
            preserveScroll: true,
            onSuccess: () => (editingFieldId.value = null),
        },
    );
}

function confirmDeleteField(field: CustomField) {
    confirm.require({
        header: 'Remove custom field?',
        message: `Remove "${field.name}"? Its values on existing items are removed too.`,
        acceptLabel: 'Remove',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () =>
            router.delete(
                destroyCustomField({ roster: props.roster, customField: field })
                    .url,
                {
                    preserveScroll: true,
                },
            ),
    });
}
</script>

<template>
    <Head :title="`${roster.title} settings`" />

    <div class="flex flex-col gap-6">
        <Link
            :href="showRoster(roster).url"
            class="text-surface-500 hover:text-surface-900 dark:hover:text-surface-0 flex items-center gap-2 text-sm"
        >
            <ArrowLeft style="width: 0.875rem; height: 0.875rem" />
            {{ roster.title }}
        </Link>

        <h1 class="text-surface-900 dark:text-surface-0 text-xl font-semibold">
            Roster settings
        </h1>

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

                <div class="flex flex-col gap-2">
                    <label for="description" class="text-sm font-medium">
                        Description
                    </label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        :invalid="!!form.errors.description"
                    />
                    <small v-if="form.errors.description" class="text-red-500">
                        {{ form.errors.description }}
                    </small>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="date" class="text-sm font-medium">
                        Date (optional)
                    </label>
                    <DatePicker
                        id="date"
                        v-model="dateModel"
                        date-format="yy-mm-dd"
                        update-model-type="string"
                        show-icon
                        show-button-bar
                        :invalid="!!form.errors.date"
                    />
                    <small v-if="form.errors.date" class="text-red-500">
                        {{ form.errors.date }}
                    </small>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox
                        v-model="form.members_can_add_items"
                        input-id="members-can-add-items"
                        binary
                    />
                    <label for="members-can-add-items" class="text-sm">
                        {{
                            roster.group
                                ? 'Allow group members, and anyone with the shared link, to add things to this roster'
                                : 'Allow anyone with the shared link to add things to this roster'
                        }}
                    </label>
                </div>

                <div
                    v-if="attendanceDayOptions.length > 0"
                    class="flex flex-col gap-2"
                >
                    <label
                        for="attendance-poll-option"
                        class="text-sm font-medium"
                    >
                        Attendance day (optional)
                    </label>
                    <Select
                        id="attendance-poll-option"
                        v-model="form.attendance_poll_option_id"
                        :options="attendanceDayOptions"
                        option-label="label"
                        option-value="id"
                        placeholder="Not linked"
                        show-clear
                        :invalid="!!form.errors.attendance_poll_option_id"
                    />
                    <small class="text-surface-500">
                        Members who haven't confirmed attendance for that day
                        see a warning when claiming a thing, but can still claim
                        it.
                    </small>
                    <small
                        v-if="form.errors.attendance_poll_option_id"
                        class="text-red-500"
                    >
                        {{ form.errors.attendance_poll_option_id }}
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
                        @click="router.visit(showRoster(roster).url)"
                    />
                </div>
            </form>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">Custom fields</h2>
            <ul
                v-if="roster.custom_fields?.length"
                class="mb-4 flex flex-col gap-2"
            >
                <li
                    v-for="field in roster.custom_fields"
                    :key="field.id"
                    class="flex max-w-md items-center gap-2"
                >
                    <template v-if="editingFieldId === field.id">
                        <InputText
                            v-model="renameForm.name"
                            size="small"
                            class="flex-1"
                            :invalid="!!renameForm.errors.name"
                        />
                        <Button
                            label="Save"
                            size="small"
                            :loading="renameForm.processing"
                            @click="submitRename(field)"
                        />
                        <Button
                            label="Cancel"
                            severity="secondary"
                            text
                            size="small"
                            @click="editingFieldId = null"
                        />
                    </template>
                    <template v-else>
                        <span
                            class="text-surface-900 dark:text-surface-0 flex-1 text-sm"
                        >
                            {{ field.name }}
                        </span>
                        <Button
                            label="Rename"
                            severity="secondary"
                            text
                            size="small"
                            @click="startRename(field)"
                        />
                        <Button
                            label="Remove"
                            severity="danger"
                            text
                            size="small"
                            @click="confirmDeleteField(field)"
                        />
                    </template>
                </li>
            </ul>
            <p v-else class="text-surface-500 mb-4 text-sm">
                No custom fields defined yet.
            </p>
            <form
                class="flex max-w-md items-start gap-3"
                @submit.prevent="submitNewField"
            >
                <div class="flex flex-1 flex-col gap-2">
                    <InputText
                        v-model="newFieldForm.name"
                        placeholder="e.g. Allergens"
                        :invalid="!!newFieldForm.errors.name"
                    />
                    <small v-if="newFieldForm.errors.name" class="text-red-500">
                        {{ newFieldForm.errors.name }}
                    </small>
                </div>
                <Button
                    type="submit"
                    label="Add field"
                    :loading="newFieldForm.processing"
                />
            </form>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-2 text-lg font-medium">Sharing</h2>
            <p class="text-surface-500 mb-4 max-w-md text-sm">
                Anyone with the link can view this list and claim things,
                without needing an account first. They only need to log in or
                sign up at the point they claim something.
            </p>

            <div v-if="shareUrl" class="flex max-w-md flex-col gap-3">
                <div class="flex items-center gap-2">
                    <InputText
                        :model-value="shareUrl"
                        readonly
                        class="flex-1"
                    />
                    <Button
                        label="Copy"
                        severity="secondary"
                        text
                        @click="copyShareUrl"
                    />
                </div>
                <div class="flex gap-3">
                    <Button
                        label="Regenerate link"
                        severity="secondary"
                        text
                        size="small"
                        @click="confirmRegenerateShareLink"
                    />
                    <Button
                        label="Disable sharing"
                        severity="danger"
                        text
                        size="small"
                        @click="confirmDisableSharing"
                    />
                </div>
            </div>
            <Button
                v-else
                label="Enable sharing"
                @click="enableRosterSharing"
            />
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-2 text-lg font-medium text-red-600">Danger zone</h2>
            <p class="text-surface-500 mb-4 max-w-md text-sm">
                Deleting a roster removes it for everyone. This cannot be
                undone.
            </p>
            <Button
                label="Delete roster"
                severity="danger"
                @click="confirmDestroy"
            />
        </div>
    </div>
</template>
