<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import ArrowLeft from '@primeicons/vue/arrow-left';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Roster } from '@/types';
import {
    destroy as destroyRoster,
    show as showRoster,
    update as updateRoster,
} from '@/actions/App/Http/Controllers/RosterController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    roster: Roster;
}>();

const confirm = useConfirm();

const form = useForm({
    title: props.roster.title,
    description: props.roster.description ?? '',
    date: props.roster.date,
});

function submit() {
    form.patch(updateRoster(props.roster).url);
}

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
                        v-model="form.date"
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
