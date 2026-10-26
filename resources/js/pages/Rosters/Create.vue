<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Copy, Link2, Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyField,
    store as storeField,
    update as updateField,
} from '@/actions/App/Http/Controllers/CustomFieldController';
import { show as showEvent } from '@/actions/App/Http/Controllers/EventController';
import { show as showGroup } from '@/actions/App/Http/Controllers/GroupController';
import {
    destroy as destroyRoster,
    duplicate as duplicateRoster,
    show as showRoster,
    store as storeRoster,
    update as updateRoster,
} from '@/actions/App/Http/Controllers/RosterController';
import {
    destroy as disableSharing,
    regenerate as regenerateSharing,
    store as enableSharing,
} from '@/actions/App/Http/Controllers/RosterSharingController';
import ChoiceGroup from '@/components/app/choice-group.vue';
import type { ChoiceOption } from '@/components/app/choice-group.vue';
import DatePicker from '@/components/app/date-picker.vue';
import FormField from '@/components/app/form-field.vue';
import FormPage from '@/components/app/form-page.vue';
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
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOptGroup,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { attendanceDayLabel } from '@/lib/roster-items';
import { dashboard } from '@/routes';

defineOptions({ layout: AppLayout });

/**
 * "New list" page, and the same page in edit mode as "List edit" (section 6; F-13, F-14, F-16 to F-19).
 */
const props = defineProps<{ form: App.Data.RosterFormData }>();

const { t, locale } = useI18n();

const roster = computed(() => props.form.roster);
const editing = computed(() => roster.value !== null);

const listForm = useForm({
    title: props.form.roster?.title ?? '',
    description: props.form.roster?.description ?? '',
    date: props.form.roster?.date ?? (null as string | null),
    members_can_add_items: props.form.roster?.members_can_add_items ?? true,
    attendance_poll_option_id: (props.form.roster?.attendance_poll_option_id ??
        '') as string | number,
    placement: props.form.event
        ? 'event'
        : props.form.group
          ? 'group'
          : 'standalone',
});

// Placement (F-13): the event or group the page was opened from, or any own group when opened from the dashboard
const placementOptions = computed<ChoiceOption[]>(() => {
    if (props.form.event) {
        return [
            {
                value: 'event',
                title: t('newList.inEvent', { event: props.form.event.title }),
                hint: t('newList.inEventHint'),
            },
            {
                value: 'standalone',
                title: t('newList.standalone'),
                hint: t('newList.standaloneHint'),
            },
        ];
    }

    if (props.form.group) {
        return [
            {
                value: 'group',
                title: t('newList.inGroup', { group: props.form.group.title }),
                hint: t('newList.inGroupHint'),
            },
            {
                value: 'standalone',
                title: t('newList.standalone'),
                hint: t('newList.standaloneHint'),
            },
        ];
    }

    return [
        {
            value: 'standalone',
            title: t('newList.standalone'),
            hint: t('newList.standaloneHint'),
        },
        ...props.form.groups.map((group) => ({
            value: `group-${group.id}`,
            title: t('newList.inGroup', { group: group.title }),
            hint: t('newList.inGroupHint'),
        })),
    ];
});

const back = computed(() => {
    if (roster.value) {
        return {
            href: showRoster(roster.value.slug).url,
            label: t('nav.backTo', { name: roster.value.title }),
        };
    }

    if (props.form.event) {
        return {
            href: showEvent(props.form.event.slug).url,
            label: t('list.back.event', { event: props.form.event.title }),
        };
    }

    if (props.form.group) {
        return {
            href: showGroup(props.form.group.slug).url,
            label: t('list.back.group', { group: props.form.group.title }),
        };
    }

    return { href: dashboard().url, label: t('nav.backToDashboard') };
});

function placementIds(placement: string): {
    group_id: number | null;
    event_id: number | null;
} {
    if (placement === 'event' && props.form.event) {
        return { group_id: null, event_id: props.form.event.id };
    }

    if (placement === 'group' && props.form.group) {
        return { group_id: props.form.group.id, event_id: null };
    }

    if (placement.startsWith('group-')) {
        return { group_id: Number(placement.slice(6)), event_id: null };
    }

    return { group_id: null, event_id: null };
}

function save(): void {
    if (roster.value) {
        listForm
            .transform(({ placement: _placement, ...data }) => ({
                ...data,
                description: data.description.trim() || null,
                attendance_poll_option_id:
                    data.attendance_poll_option_id === ''
                        ? null
                        : Number(data.attendance_poll_option_id),
            }))
            .patch(updateRoster(roster.value.slug).url);

        return;
    }

    listForm
        .transform(
            ({ placement, attendance_poll_option_id: _option, ...data }) => ({
                ...data,
                description: data.description.trim() || null,
                ...placementIds(placement),
            }),
        )
        .post(storeRoster().url);
}

const placementError = computed(() => {
    const errors = listForm.errors as Record<string, string | undefined>;

    return errors.group_id ?? errors.event_id ?? null;
});

const attendanceGroups = computed(() => {
    const byPoll = new Map<string, App.Data.AttendanceOptionData[]>();

    for (const option of props.form.attendance_options) {
        byPoll.set(option.poll_title, [
            ...(byPoll.get(option.poll_title) ?? []),
            option,
        ]);
    }

    return [...byPoll.entries()].map(([title, options]) => ({
        title,
        options,
    }));
});

function showError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? t('list.notFound'));
}

// Custom fields (F-16): they apply at once, the details above on save

const fieldForm = useForm({ name: '' });

function addField(): void {
    if (!roster.value || !fieldForm.name.trim()) {
        return;
    }

    fieldForm.post(storeField(roster.value.slug).url, {
        preserveScroll: true,
        onSuccess: () => fieldForm.reset(),
    });
}

function renameField(field: App.Data.CustomFieldData, event: Event): void {
    const name = (event.target as HTMLInputElement).value.trim();

    if (!roster.value || !name || name === field.name) {
        return;
    }

    router.patch(
        updateField([roster.value.slug, field.slug]).url,
        { name },
        { preserveScroll: true, onError: showError },
    );
}

// Confirmations: removing a field, regenerating or disabling the link and deleting the list

type Confirmation = {
    title: string;
    text: string;
    action: string;
    danger: boolean;
    run: () => void;
};
const confirmation = ref<Confirmation | null>(null);
const confirmOpen = ref(false);

function ask(next: Confirmation): void {
    confirmation.value = next;
    confirmOpen.value = true;
}

function confirm(): void {
    confirmation.value?.run();
    confirmOpen.value = false;
}

function askRemoveField(field: App.Data.CustomFieldData): void {
    ask({
        title: t('list.fields.remove'),
        text: t('list.fields.confirmPlain', { name: field.name }),
        action: t('list.fields.confirmButton'),
        danger: true,
        run: () =>
            router.delete(destroyField([roster.value!.slug, field.slug]).url, {
                preserveScroll: true,
                onError: showError,
            }),
    });
}

// Share link (F-19)

function enableLink(): void {
    router.post(
        enableSharing(roster.value!.slug).url,
        {},
        { preserveScroll: true, onError: showError },
    );
}

function askRegenerate(): void {
    ask({
        title: t('list.share.regenerateTitle'),
        text: t('list.share.regenerateText'),
        action: t('list.share.regenerate'),
        danger: false,
        run: () =>
            router.post(
                regenerateSharing(roster.value!.slug).url,
                {},
                { preserveScroll: true, onError: showError },
            ),
    });
}

function askDisable(): void {
    ask({
        title: t('list.share.disableTitle'),
        text: t('list.share.disableText'),
        action: t('list.share.disable'),
        danger: true,
        run: () =>
            router.delete(disableSharing(roster.value!.slug).url, {
                preserveScroll: true,
                onError: showError,
            }),
    });
}

async function copyLink(url: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(url);
        toast.success(t('list.share.copied'));
    } catch {
        toast.error(t('list.share.copyFailed'), {
            description: url,
            duration: 10000,
        });
    }
}

// Duplicate (F-17) and delete (F-13)

function duplicate(): void {
    router.post(
        duplicateRoster(roster.value!.slug).url,
        {},
        { onError: showError },
    );
}

function askDelete(): void {
    ask({
        title: t('list.settings.deleteTitle'),
        text: t('list.settings.deleteText', { title: roster.value!.title }),
        action: t('list.settings.delete'),
        danger: true,
        run: () =>
            router.delete(destroyRoster(roster.value!.slug).url, {
                onError: showError,
            }),
    });
}

const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) max-w-(--field-max-width) rounded-(--control-radius) px-3.5 text-[15px]';
const smallInputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-9 min-w-0 flex-1 rounded-(--control-radius) px-3 text-[15px]';
</script>

<template>
    <Head :title="editing ? t('list.edit.title') : t('newList.title')" />

    <FormPage
        :title="editing ? t('list.edit.title') : t('newList.title')"
        :subtitle="roster ? roster.title : t('newList.sub')"
        :back-href="back.href"
        :back-label="back.label"
    >
        <form
            id="list-form"
            class="flex max-w-[560px] flex-col gap-(--space-stack)"
            novalidate
            data-feature="F-13"
            @submit.prevent="save"
        >
            <FormField
                :label="t('newList.name')"
                control="list-title"
                required
                :error="listForm.errors.title"
            >
                <template #default="{ describedBy, invalid }">
                    <Input
                        id="list-title"
                        v-model="listForm.title"
                        required
                        autocomplete="off"
                        :placeholder="t('newList.namePlaceholder')"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid || undefined"
                        :class="inputClass"
                    />
                </template>
            </FormField>
            <FormField
                :label="t('newList.description')"
                control="list-description"
                :error="listForm.errors.description"
            >
                <template #default="{ describedBy, invalid }">
                    <Textarea
                        id="list-description"
                        v-model="listForm.description"
                        rows="3"
                        class="max-w-(--field-max-width)"
                        :placeholder="t('newList.descriptionPlaceholder')"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid || undefined"
                    />
                </template>
            </FormField>
            <FormField
                :label="t('newList.date')"
                control="list-date"
                :hint="t('newList.dateHint')"
                :error="listForm.errors.date"
            >
                <template #default="{ describedBy, invalid }">
                    <DatePicker
                        id="list-date"
                        v-model="listForm.date"
                        :described-by="describedBy"
                        :invalid="invalid"
                    />
                </template>
            </FormField>
            <FormField
                v-if="!editing && placementOptions.length > 1"
                :label="t('newList.where')"
                control="list-where"
                group
                :error="placementError"
            >
                <ChoiceGroup
                    v-model="listForm.placement"
                    name="list-where"
                    :options="placementOptions"
                    :invalid="!!placementError"
                />
            </FormField>
            <div class="flex items-start gap-3" data-feature="F-14">
                <Checkbox
                    id="list-members-add"
                    v-model="listForm.members_can_add_items"
                    class="mt-1 size-5"
                    aria-describedby="list-members-add-hint"
                />
                <span class="flex flex-col gap-0.5">
                    <label for="list-members-add" class="font-semibold">{{
                        t('newList.membersCanAdd')
                    }}</label>
                    <span
                        id="list-members-add-hint"
                        class="text-fg-muted text-sm"
                        >{{ t('newList.membersCanAddHint') }}</span
                    >
                </span>
            </div>
            <FormField
                v-if="editing && attendanceGroups.length"
                :label="t('list.attendance.listLabel')"
                control="list-attendance"
                :hint="t('list.attendance.listHint')"
                :error="listForm.errors.attendance_poll_option_id"
                data-feature="F-18"
            >
                <template #default="{ describedBy, invalid }">
                    <NativeSelect
                        id="list-attendance"
                        v-model="listForm.attendance_poll_option_id"
                        class="w-full max-w-(--field-max-width)"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid || undefined"
                    >
                        <NativeSelectOption value="">{{
                            t('list.attendance.none')
                        }}</NativeSelectOption>
                        <NativeSelectOptGroup
                            v-for="poll in attendanceGroups"
                            :key="poll.title"
                            :label="poll.title"
                        >
                            <NativeSelectOption
                                v-for="option in poll.options"
                                :key="option.id"
                                :value="option.id"
                                >{{
                                    attendanceDayLabel(option, locale)
                                }}</NativeSelectOption
                            >
                        </NativeSelectOptGroup>
                    </NativeSelect>
                </template>
            </FormField>
        </form>

        <template v-if="roster">
            <template v-if="form.can_manage_fields">
                <Separator class="my-2" />
                <section
                    class="flex max-w-[560px] flex-col gap-3"
                    aria-labelledby="fields-title"
                    data-feature="F-16"
                >
                    <h2 id="fields-title" class="text-xl">
                        {{ t('list.fields.title') }}
                    </h2>
                    <p class="text-fg-muted text-sm">
                        {{ t('list.fields.hint') }}
                    </p>
                    <ul
                        v-if="form.custom_fields.length"
                        class="flex flex-col gap-2"
                    >
                        <li
                            v-for="field in form.custom_fields"
                            :key="field.id"
                            class="flex items-center gap-2"
                        >
                            <Input
                                :model-value="field.name"
                                :aria-label="t('list.fields.name')"
                                aria-describedby="fields-rename-hint"
                                :class="smallInputClass"
                                @change="renameField(field, $event)"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="t('list.fields.remove')"
                                @click="askRemoveField(field)"
                            >
                                <X />
                            </Button>
                        </li>
                    </ul>
                    <p
                        v-if="form.custom_fields.length"
                        id="fields-rename-hint"
                        class="text-fg-soft text-[13px]"
                    >
                        {{ t('list.fields.renameHint') }}
                    </p>
                    <form
                        class="flex items-center gap-2"
                        novalidate
                        @submit.prevent="addField"
                    >
                        <Input
                            v-model="fieldForm.name"
                            :placeholder="t('list.fields.placeholder')"
                            :aria-label="t('list.fields.add')"
                            :aria-invalid="
                                fieldForm.errors.name ? 'true' : undefined
                            "
                            :class="smallInputClass"
                        />
                        <Button
                            type="submit"
                            variant="secondary"
                            size="sm"
                            :disabled="
                                fieldForm.processing || !fieldForm.name.trim()
                            "
                        >
                            <Plus aria-hidden="true" />
                            {{ t('list.fields.add') }}
                        </Button>
                    </form>
                    <p
                        v-if="fieldForm.errors.name"
                        class="text-danger text-sm"
                        role="alert"
                    >
                        {{ fieldForm.errors.name }}
                    </p>
                </section>
            </template>

            <template v-if="form.can_share">
                <Separator class="my-2" />
                <section
                    class="flex max-w-[560px] flex-col gap-3"
                    aria-labelledby="share-title"
                    data-feature="F-19"
                >
                    <h2 id="share-title" class="text-xl">
                        {{ t('list.share.title') }}
                    </h2>
                    <p class="text-fg-muted text-sm">
                        {{ t('list.share.hint') }}
                    </p>
                    <template v-if="roster.share_url">
                        <div class="flex flex-wrap items-center gap-2">
                            <Input
                                :model-value="roster.share_url"
                                readonly
                                :aria-label="t('list.share.url')"
                                :class="smallInputClass"
                                @focus="
                                    ($event.target as HTMLInputElement).select()
                                "
                            />
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="copyLink(roster.share_url)"
                            >
                                <Copy aria-hidden="true" />
                                {{ t('list.share.copy') }}
                            </Button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="askRegenerate"
                                >{{ t('list.share.regenerate') }}</Button
                            >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="askDisable"
                                >{{ t('list.share.disable') }}</Button
                            >
                        </div>
                    </template>
                    <Button
                        v-else
                        type="button"
                        variant="secondary"
                        size="sm"
                        class="w-fit"
                        @click="enableLink"
                    >
                        <Link2 aria-hidden="true" />
                        {{ t('list.share.enable') }}
                    </Button>
                </section>
            </template>

            <template v-if="form.can_duplicate">
                <Separator class="my-2" />
                <section
                    class="flex max-w-[560px] flex-col items-start gap-3"
                    aria-labelledby="duplicate-title"
                    data-feature="F-17"
                >
                    <h2 id="duplicate-title" class="text-xl">
                        {{ t('list.settings.duplicate') }}
                    </h2>
                    <p class="text-fg-muted text-sm">
                        {{ t('list.settings.duplicateHint') }}
                    </p>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="duplicate"
                        >{{ t('list.settings.duplicate') }}</Button
                    >
                </section>
            </template>

            <template v-if="form.can_delete">
                <Separator class="my-2" />
                <section
                    class="flex max-w-[560px] flex-col items-start gap-3"
                    aria-labelledby="delete-title"
                    data-feature="F-13"
                >
                    <h2 id="delete-title" class="text-xl">
                        {{ t('list.settings.delete') }}
                    </h2>
                    <p class="text-fg-muted text-sm">
                        {{ t('list.settings.deleteHint') }}
                    </p>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        @click="askDelete"
                        >{{ t('list.settings.delete') }}</Button
                    >
                </section>
            </template>
        </template>

        <AlertDialog v-model:open="confirmOpen">
            <AlertDialogContent v-if="confirmation">
                <AlertDialogHeader>
                    <AlertDialogTitle>{{
                        confirmation.title
                    }}</AlertDialogTitle>
                    <AlertDialogDescription>{{
                        confirmation.text
                    }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{
                        t('dialog.cancel')
                    }}</AlertDialogCancel>
                    <AlertDialogAction
                        :variant="
                            confirmation.danger ? 'destructive' : 'default'
                        "
                        @click="confirm"
                        >{{ confirmation.action }}</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <template #actions>
            <Button
                as-child
                variant="outline"
                size="lg"
                class="h-(--control-height) px-4"
            >
                <Link :href="back.href">{{ t('dialog.cancel') }}</Link>
            </Button>
            <Button
                type="submit"
                form="list-form"
                size="lg"
                class="h-(--control-height) px-4"
                :disabled="listForm.processing"
            >
                {{ editing ? t('list.edit.save') : t('newList.create') }}
            </Button>
        </template>
    </FormPage>
</template>
