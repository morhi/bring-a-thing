<script setup lang="ts">
import { CalendarDays } from '@lucide/vue';
import { show as showEvent } from '@/actions/App/Http/Controllers/EventController';
import DataRow from '@/components/app/data-row.vue';
import { dayOf, formatDate, timeOf } from '@/components/app/poll/dates';
import { useI18n } from '@/composables/use-i18n';

defineProps<{
    event: App.Data.DashboardEventData;
}>();

const { t, locale } = useI18n();

// The year only shows when it differs, which keeps the narrow meta line short enough for the context
function shortDate(date: string | null): string {
    if (!date) {
        return '';
    }

    const sameYear = date.startsWith(String(new Date().getFullYear()));

    return formatDate(date, locale.value, {
        day: 'numeric',
        month: 'short',
        year: sameYear ? undefined : 'numeric',
    });
}
</script>

<template>
    <DataRow
        :href="showEvent(event.slug).url"
        :icon="CalendarDays"
        count-class="w-[5.5rem]"
        data-feature="F-10"
    >
        <template #title>{{ event.title }}</template>
        <template #context>{{
            event.group_name ?? t('newList.standalone')
        }}</template>
        <template #date
            >{{ shortDate(dayOf(event))
            }}<template v-if="event.starts_at">
                · {{ timeOf(event.starts_at) }}</template
            ></template
        >
        <template #count>{{
            t('dash.upcoming.going', { n: event.accepted_count })
        }}</template>
    </DataRow>
</template>
