<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Group, Roster } from '@/types';
import {
    show as showGroup,
    store as storeGroup,
} from '@/actions/App/Http/Controllers/GroupController';
import {
    show as showRoster,
    store as storeRoster,
} from '@/actions/App/Http/Controllers/RosterController';

defineOptions({ layout: AppLayout });

defineProps<{
    groups: Group[];
    rosters: Roster[];
}>();

const showCreateGroup = ref(false);
const showCreateRoster = ref(false);

const createGroupForm = useForm({ name: '' });

function submitCreateGroup() {
    createGroupForm.post(storeGroup().url, {
        onSuccess: () => {
            createGroupForm.reset();
            showCreateGroup.value = false;
        },
    });
}

const createRosterForm = useForm({ title: '', description: '' });

function submitCreateRoster() {
    createRosterForm.post(storeRoster().url, {
        onSuccess: () => {
            createRosterForm.reset();
            showCreateRoster.value = false;
        },
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-8">
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2
                    class="text-surface-900 dark:text-surface-0 text-lg font-semibold"
                >
                    Groups
                </h2>
                <Button
                    label="New group"
                    size="small"
                    @click="showCreateGroup = true"
                />
            </div>

            <div
                v-if="groups.length === 0"
                class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-8 text-center text-sm"
            >
                You are not a member of any groups yet.
            </div>
            <ul v-else class="flex flex-col gap-3">
                <li v-for="group in groups" :key="group.id">
                    <Link
                        :href="showGroup(group).url"
                        class="dark:bg-surface-900 text-surface-900 dark:text-surface-0 block rounded-lg bg-white p-4 shadow-sm hover:shadow"
                    >
                        {{ group.name }}
                    </Link>
                </li>
            </ul>
        </section>

        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2
                    class="text-surface-900 dark:text-surface-0 text-lg font-semibold"
                >
                    Standalone rosters
                </h2>
                <Button
                    label="New roster"
                    size="small"
                    @click="showCreateRoster = true"
                />
            </div>

            <div
                v-if="rosters.length === 0"
                class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-8 text-center text-sm"
            >
                You have not created or joined any rosters yet.
            </div>
            <ul v-else class="flex flex-col gap-3">
                <li v-for="roster in rosters" :key="roster.id">
                    <Link
                        :href="showRoster(roster).url"
                        class="dark:bg-surface-900 text-surface-900 dark:text-surface-0 block rounded-lg bg-white p-4 shadow-sm hover:shadow"
                    >
                        {{ roster.title }}
                    </Link>
                </li>
            </ul>
        </section>
    </div>

    <Dialog
        v-model:visible="showCreateGroup"
        modal
        header="New group"
        class="w-full max-w-sm"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submitCreateGroup">
            <div class="flex flex-col gap-2">
                <label for="group-name" class="text-sm font-medium">
                    Name <span class="text-red-500">*</span>
                </label>
                <InputText
                    id="group-name"
                    v-model="createGroupForm.name"
                    autofocus
                    :invalid="!!createGroupForm.errors.name"
                />
                <small v-if="createGroupForm.errors.name" class="text-red-500">
                    {{ createGroupForm.errors.name }}
                </small>
            </div>
            <Button
                type="submit"
                label="Create"
                :loading="createGroupForm.processing"
            />
        </form>
    </Dialog>

    <Dialog
        v-model:visible="showCreateRoster"
        modal
        header="New roster"
        class="w-full max-w-sm"
    >
        <form class="flex flex-col gap-4" @submit.prevent="submitCreateRoster">
            <div class="flex flex-col gap-2">
                <label for="roster-title" class="text-sm font-medium">
                    Title <span class="text-red-500">*</span>
                </label>
                <InputText
                    id="roster-title"
                    v-model="createRosterForm.title"
                    autofocus
                    :invalid="!!createRosterForm.errors.title"
                />
                <small
                    v-if="createRosterForm.errors.title"
                    class="text-red-500"
                >
                    {{ createRosterForm.errors.title }}
                </small>
            </div>
            <div class="flex flex-col gap-2">
                <label for="roster-description" class="text-sm font-medium">
                    Description
                </label>
                <Textarea
                    id="roster-description"
                    v-model="createRosterForm.description"
                    rows="3"
                    :invalid="!!createRosterForm.errors.description"
                />
                <small
                    v-if="createRosterForm.errors.description"
                    class="text-red-500"
                >
                    {{ createRosterForm.errors.description }}
                </small>
            </div>
            <Button
                type="submit"
                label="Create"
                :loading="createRosterForm.processing"
            />
        </form>
    </Dialog>
</template>
