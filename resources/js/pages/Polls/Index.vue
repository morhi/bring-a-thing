<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, Plus, Vote } from '@lucide/vue';
import {
    createWithoutGroup as createPoll,
    show as showPoll,
} from '@/actions/App/Http/Controllers/PollController';
import { index as pollsIndex } from '@/actions/App/Http/Controllers/PollsController';
import EmptyState from '@/components/app/empty-state.vue';
import PollRow from '@/components/app/poll-row.vue';
import SectionBox from '@/components/app/section-box.vue';
import { Badge } from '@/components/ui/badge';
import { formatInstant } from '@/components/app/poll/dates';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';

defineOptions({ layout: AppLayout });

// Mirrors App.Data.PollsIndexData
const props = defineProps<{
    polls: App.Data.DashboardPollData[];
    filters: App.Data.PollsFiltersData;
}>();

const { t, locale } = useI18n();

const filterControls: {
    key: keyof App.Data.PollsFiltersData;
    label: string;
}[] = [
    { key: 'unanswered', label: 'polls.filter.unanswered' },
    { key: 'mine', label: 'polls.filter.mine' },
    { key: 'all', label: 'polls.filter.all' },
];

// Anything short of a complete answer still needs the person, so it shares the warning tone
const statusClass: Record<App.Enums.DashboardPollStatus, string> = {
    unanswered: 'border-transparent bg-warning-soft text-warning',
    partial: 'border-transparent bg-warning-soft text-warning',
    answered: 'border-transparent bg-success-soft text-success',
};

/**
 * Reload the page with one filter switched; the backend applies the filters.
 */
function setFilter(key: keyof App.Data.PollsFiltersData, value: boolean): void {
    const filters = { ...props.filters, [key]: value };
    // Unset filters stay out of the URL, so the default page keeps a clean address
    const query = Object.fromEntries(
        Object.entries(filters)
            .filter(([, active]) => active)
            .map(([name]) => [name, 1]),
    );

    router.get(pollsIndex.url({ query }), undefined, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <Head :title="t('polls.title')" />

    <div class="flex flex-col gap-10" data-feature="F-31">
        <header class="flex flex-col gap-2.5">
            <Link
                :href="dashboard().url"
                class="text-fg-muted hover:text-fg -mt-0.5 mb-1 inline-flex min-h-6 w-fit items-center gap-1.5 rounded-sm text-sm font-semibold no-underline"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ t('nav.backToDashboard') }}
            </Link>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-h1">{{ t('polls.title') }}</h1>
                    <p class="text-fg-muted max-w-[60ch]">
                        {{ t('polls.lede') }}
                    </p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="createPoll().url" class="no-underline"
                        ><Plus aria-hidden="true" /> {{ t('polls.new') }}</Link
                    >
                </Button>
            </div>
        </header>

        <SectionBox
            id="polls-table-title"
            :title="t('dash.polls')"
            :count="polls.length"
        >
            <template #action>
                <div
                    role="group"
                    :aria-label="t('polls.filters')"
                    class="flex flex-wrap gap-2"
                >
                    <Button
                        v-for="control in filterControls"
                        :key="control.key"
                        type="button"
                        size="lg"
                        :variant="filters[control.key] ? 'default' : 'outline'"
                        :aria-pressed="filters[control.key]"
                        :class="[
                            'rounded-full px-3.5',
                            filters[control.key] &&
                                'text-primary-fg hover:bg-primary/90',
                        ]"
                        @click="setFilter(control.key, !filters[control.key])"
                    >
                        <!-- The check keeps the active state readable without relying on color -->
                        <Check v-if="filters[control.key]" aria-hidden="true" />
                        {{ t(control.label) }}
                    </Button>
                </div>
            </template>
            <!-- Narrow cards stack the polls as rows; the table only shows where its columns fit -->
            <div v-if="polls.length" class="@container min-w-0">
                <ul
                    class="divide-border flex min-w-0 flex-col divide-y @[720px]:hidden"
                >
                    <li v-for="poll in polls" :key="poll.slug">
                        <PollRow :poll="poll" />
                    </li>
                </ul>
                <table
                    class="hidden w-full border-collapse text-sm @[720px]:table"
                >
                    <thead>
                        <tr class="text-fg-muted text-left">
                            <th scope="col" class="py-2 pr-4 font-semibold">
                                {{ t('polls.column.poll') }}
                            </th>
                            <th
                                scope="col"
                                class="w-56 min-w-40 py-2 pr-4 font-semibold"
                            >
                                {{ t('polls.column.group') }}
                            </th>
                            <th scope="col" class="py-2 pr-4 font-semibold">
                                {{ t('polls.column.answered') }}
                            </th>
                            <th scope="col" class="py-2 font-semibold">
                                {{ t('polls.column.status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-border divide-y">
                        <tr
                            v-for="poll in polls"
                            :key="poll.slug"
                            class="align-middle"
                        >
                            <td class="py-3 pr-4">
                                <span class="flex items-start gap-2">
                                    <Vote
                                        class="text-fg-soft mt-0.5 size-4 flex-none"
                                        aria-hidden="true"
                                    />
                                    <span class="flex min-w-0 flex-col gap-0.5">
                                        <Link
                                            :href="showPoll(poll.slug).url"
                                            class="text-fg hover:text-primary font-semibold break-words no-underline"
                                            >{{ poll.title }}</Link
                                        >
                                        <span
                                            v-if="poll.answer_deadline"
                                            class="text-fg-muted text-[13px]"
                                            >{{
                                                t('dash.polls.until', {
                                                    date: formatInstant(
                                                        poll.answer_deadline,
                                                        locale,
                                                        {
                                                            day: 'numeric',
                                                            month: 'short',
                                                        },
                                                    ),
                                                })
                                            }}</span
                                        >
                                    </span>
                                </span>
                            </td>
                            <td class="text-fg-muted py-3 pr-4">
                                <span
                                    :title="
                                        poll.group_name ??
                                        t('newList.standalone')
                                    "
                                    class="block max-w-56 truncate whitespace-nowrap"
                                    >{{
                                        poll.group_name ??
                                        t('newList.standalone')
                                    }}</span
                                >
                            </td>
                            <td
                                class="text-fg-muted py-3 pr-4 whitespace-nowrap tabular-nums"
                            >
                                {{
                                    t('dash.polls.answered', {
                                        answered: poll.answered,
                                        total: poll.total,
                                    })
                                }}
                            </td>
                            <td class="py-3">
                                <Badge
                                    v-if="poll.is_closed"
                                    variant="outline"
                                    >{{ t('group.polls.closed') }}</Badge
                                >
                                <Badge
                                    v-else
                                    variant="outline"
                                    :class="statusClass[poll.status]"
                                    >{{
                                        t(`dash.polls.status.${poll.status}`)
                                    }}</Badge
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <EmptyState
                v-else
                :icon="Vote"
                :title="t('polls.empty.title')"
                :text="t('polls.empty.text')"
            />
        </SectionBox>
    </div>
</template>
