<script setup lang="ts">
import { Vote } from '@lucide/vue';
import { show as showPoll } from '@/actions/App/Http/Controllers/PollController';
import DataRow from '@/components/app/data-row.vue';
import { Badge } from '@/components/ui/badge';
import { useI18n } from '@/composables/use-i18n';

defineProps<{
    poll: App.Data.DashboardPollData;
}>();

const { t } = useI18n();

// Anything short of a complete answer still needs the person, so it shares the warning tone
const statusClass: Record<App.Enums.DashboardPollStatus, string> = {
    unanswered: 'border-transparent bg-warning-soft text-warning',
    partial: 'border-transparent bg-warning-soft text-warning',
    answered: 'border-transparent bg-success-soft text-success',
};
</script>

<template>
    <DataRow
        :href="showPoll(poll.slug).url"
        :icon="Vote"
        count-class="w-[8.5rem]"
        data-feature="F-06"
    >
        <template #title>{{ poll.title }}</template>
        <template #context>{{ poll.group_name }}</template>
        <template #count>{{
            t('dash.polls.answered', {
                answered: poll.answered,
                total: poll.total,
            })
        }}</template>
        <template #badges>
            <Badge v-if="poll.is_closed" variant="outline">{{
                t('group.polls.closed')
            }}</Badge>
            <Badge v-else variant="outline" :class="statusClass[poll.status]">{{
                t(`dash.polls.status.${poll.status}`)
            }}</Badge>
        </template>
    </DataRow>
</template>
