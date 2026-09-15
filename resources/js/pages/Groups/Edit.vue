<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
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

const form = useForm({
    name: props.group.name,
});

function submit() {
    form.patch(updateGroup(props.group).url);
}

function destroy() {
    router.delete(destroyGroup(props.group).url);
}
</script>

<template>
    <Head :title="`${group.name} settings`" />

    <div class="mx-auto flex max-w-md flex-col gap-6">
        <h1 class="text-surface-900 dark:text-surface-0 text-xl font-semibold">
            Group settings
        </h1>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <form class="flex flex-col gap-4" @submit.prevent="submit">
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
            <p class="text-surface-500 mb-4 text-sm">
                Deleting a group removes it and its membership for everyone.
                This cannot be undone.
            </p>
            <Button label="Delete group" severity="danger" @click="destroy" />
        </div>
    </div>
</template>
