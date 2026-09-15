<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useConfirm } from 'primevue/useconfirm';
import ArrowLeft from '@primeicons/vue/arrow-left';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Group } from '@/types';
import {
    destroy as destroyGroup,
    show as showGroup,
    update as updateGroup,
} from '@/actions/App/Http/Controllers/GroupController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    group: Group;
}>();

const confirm = useConfirm();

const form = useForm({
    name: props.group.name,
});

function submit() {
    form.patch(updateGroup(props.group).url);
}

function confirmDestroy() {
    confirm.require({
        header: 'Delete group?',
        message: `Deleting "${props.group.name}" removes it and its membership for everyone. This cannot be undone.`,
        acceptLabel: 'Delete group',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () => form.delete(destroyGroup(props.group).url),
    });
}
</script>

<template>
    <Head :title="`${group.name} settings`" />

    <div class="flex flex-col gap-6">
        <Link
            :href="showGroup(group).url"
            class="text-surface-500 hover:text-surface-900 dark:hover:text-surface-0 flex items-center gap-2 text-sm"
        >
            <ArrowLeft style="width: 0.875rem; height: 0.875rem" />
            {{ group.name }}
        </Link>

        <h1 class="text-surface-900 dark:text-surface-0 text-xl font-semibold">
            Group settings
        </h1>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <form class="flex max-w-md flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-col gap-2">
                    <label for="name" class="text-sm font-medium">Name</label>
                    <InputText
                        id="name"
                        v-model="form.name"
                        :invalid="!!form.errors.name"
                    />
                    <small v-if="form.errors.name" class="text-red-500">
                        {{ form.errors.name }}
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
                        @click="router.visit(showGroup(group).url)"
                    />
                </div>
            </form>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-2 text-lg font-medium text-red-600">Danger zone</h2>
            <p class="text-surface-500 mb-4 max-w-md text-sm">
                Deleting a group removes it and its membership for everyone.
                This cannot be undone.
            </p>
            <Button
                label="Delete group"
                severity="danger"
                @click="confirmDestroy"
            />
        </div>
    </div>
</template>
