<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-8">
        <section>
            <h2
                class="text-surface-900 dark:text-surface-0 mb-3 text-lg font-semibold"
            >
                Groups
            </h2>
            <div
                class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-8 text-center text-sm"
            >
                You are not a member of any groups yet.
            </div>
        </section>

        <section>
            <h2
                class="text-surface-900 dark:text-surface-0 mb-3 text-lg font-semibold"
            >
                Standalone lists
            </h2>
            <div
                class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-8 text-center text-sm"
            >
                You have not created or joined any lists yet.
            </div>
        </section>
    </div>
</template>
