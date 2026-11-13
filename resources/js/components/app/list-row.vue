<script setup lang="ts">
import { Link2, ListChecks } from '@lucide/vue';
import { show as showList } from '@/actions/App/Http/Controllers/RosterController';
import DataRow from '@/components/app/data-row.vue';
import { formatDate } from '@/components/app/poll/dates';
import ProgressBar from '@/components/app/progress-bar.vue';
import { Badge } from '@/components/ui/badge';
import { useI18n } from '@/composables/use-i18n';

defineProps<{
    list: App.Data.DashboardListData;
}>();

const { t, locale } = useI18n();

// The year only shows when it differs, which keeps the narrow meta line short enough for the context
function shortDate(date: string): string {
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
        :href="showList(list.slug).url"
        :icon="ListChecks"
        count-class="w-[3.5rem]"
        data-feature="F-13"
    >
        <template #title>{{ list.title }}</template>
        <template #context>{{
            list.event_title ?? list.group_name ?? t('newList.standalone')
        }}</template>
        <template v-if="list.date" #date>{{ shortDate(list.date) }}</template>
        <template #count>
            <!-- Narrow rows keep only the numbers, so the context stays readable -->
            <span class="hidden w-16 @[60rem]:flex">
                <ProgressBar
                    :done="list.claimed_count"
                    :total="list.item_count"
                    :label="
                        t('dash.lists.claimed', {
                            done: list.claimed_count,
                            total: list.item_count,
                        })
                    "
                />
            </span>
            {{ list.claimed_count }}/{{ list.item_count }}
        </template>
        <template #badges>
            <Badge v-if="list.is_past" variant="outline">{{
                t('list.past')
            }}</Badge>
            <Badge v-if="list.is_shared" variant="outline" data-feature="F-19">
                <Link2 aria-hidden="true" />
                {{ t('dash.lists.shared') }}
            </Badge>
        </template>
    </DataRow>
</template>
