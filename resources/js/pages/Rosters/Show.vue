<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Roster } from '@/types';
import {
    duplicate as duplicateRoster,
    edit as editRoster,
} from '@/actions/App/Http/Controllers/RosterController';
import { show as showGroup } from '@/actions/App/Http/Controllers/GroupController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    roster: Roster;
    canManage: boolean;
}>();

const confirm = useConfirm();

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString(undefined, {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function confirmDuplicate() {
    confirm.require({
        header: 'Duplicate roster?',
        message: `Create a new roster from "${props.roster.title}"? Items are not copied yet.`,
        acceptLabel: 'Duplicate',
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () => router.post(duplicateRoster(props.roster).url),
    });
}
</script>

<template>
    <Head :title="roster.title" />

    <div class="flex flex-col gap-6">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-2">
                <Link
                    v-if="roster.group"
                    :href="showGroup(roster.group).url"
                    class="text-surface-500 hover:text-surface-900 dark:hover:text-surface-0 text-sm"
                >
                    {{ roster.group.name }}
                </Link>
                <h1
                    class="text-surface-900 dark:text-surface-0 text-xl font-semibold"
                >
                    {{ roster.title }}
                </h1>
                <Tag v-if="roster.date" :value="formatDate(roster.date)" />
            </div>
            <div class="flex gap-2">
                <Button
                    label="Duplicate"
                    severity="secondary"
                    text
                    @click="confirmDuplicate"
                />
                <Link v-if="canManage" :href="editRoster(roster).url">
                    <Button label="Settings" severity="secondary" text />
                </Link>
            </div>
        </div>

        <p
            v-if="roster.description"
            class="text-surface-700 dark:text-surface-300 max-w-2xl text-sm"
        >
            {{ roster.description }}
        </p>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">Items</h2>
            <div
                class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-8 text-center text-sm"
            >
                Items are coming soon.
            </div>
        </div>
    </div>
</template>
