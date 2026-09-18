<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatPollOption } from '@/lib/pollDate';
import { responseFor } from '@/lib/attendance';
import type { Auth, Poll, PollOption, PollResponseStatus } from '@/types';
import { show as showGroup } from '@/actions/App/Http/Controllers/GroupController';
import { edit as editPoll } from '@/actions/App/Http/Controllers/PollController';
import { store as storeResponse } from '@/actions/App/Http/Controllers/PollResponseController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    poll: Poll;
    canManage: boolean;
    bestOptionIds: number[];
}>();

const page = usePage<{ auth: Auth }>();
const currentUserId = computed(() => page.props.auth.user?.id ?? null);
const toast = useToast();

const members = computed(() => props.poll.group?.members ?? []);
const options = computed(() => props.poll.options ?? []);

function isBest(option: PollOption): boolean {
    return props.bestOptionIds.includes(option.id);
}

function yesCount(option: PollOption): number {
    return (option.responses ?? []).filter(
        (response) => response.status === 'yes',
    ).length;
}

function statusSeverity(status: PollResponseStatus | null): string {
    if (status === 'yes') return 'success';
    if (status === 'no') return 'danger';
    if (status === 'maybe') return 'warn';
    return 'secondary';
}

function statusLabel(status: PollResponseStatus | null): string {
    if (status === 'yes') return 'Yes';
    if (status === 'no') return 'No';
    if (status === 'maybe') return 'Maybe';
    return '–';
}

const statusChoices: { status: PollResponseStatus; label: string }[] = [
    { status: 'yes', label: 'Yes' },
    { status: 'no', label: 'No' },
    { status: 'maybe', label: 'Maybe' },
];

function submitResponse(option: PollOption, status: PollResponseStatus) {
    if (props.poll.closed_at) {
        toast.add({
            severity: 'error',
            summary: 'This poll is closed.',
            life: 6000,
        });

        return;
    }

    router.post(
        storeResponse({ poll: props.poll, option }).url,
        { status },
        {
            preserveScroll: true,
            onError: (errors) => {
                toast.add({
                    severity: 'error',
                    summary:
                        Object.values(errors)[0] ?? 'Could not save response.',
                    life: 6000,
                });
            },
        },
    );
}
</script>

<template>
    <Head :title="poll.title" />

    <div class="flex flex-col gap-6">
        <div class="flex items-start justify-between">
            <div class="flex flex-col gap-2">
                <Link
                    v-if="poll.group"
                    :href="showGroup(poll.group).url"
                    class="text-surface-500 hover:text-surface-900 dark:hover:text-surface-0 text-sm"
                >
                    {{ poll.group.name }}
                </Link>
                <h1
                    class="text-surface-900 dark:text-surface-0 text-xl font-semibold"
                >
                    {{ poll.title }}
                </h1>
                <div class="flex items-center gap-2">
                    <Tag
                        :value="
                            poll.type === 'date_finder'
                                ? 'Date finder'
                                : 'Attendance'
                        "
                        severity="secondary"
                    />
                    <Tag
                        :value="
                            poll.granularity === 'hour'
                                ? 'Hour slots'
                                : 'Day options'
                        "
                        severity="secondary"
                    />
                    <Tag
                        v-if="poll.closed_at"
                        value="Closed"
                        severity="secondary"
                    />
                </div>
            </div>
            <Link v-if="canManage" :href="editPoll(poll).url">
                <Button label="Settings" severity="secondary" text />
            </Link>
        </div>

        <div
            class="dark:bg-surface-900 overflow-x-auto rounded-lg bg-white p-6 shadow-sm"
        >
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr>
                        <th
                            class="border-surface-200 dark:border-surface-700 sticky left-0 border-b p-2 text-left"
                        >
                            Member
                        </th>
                        <th
                            v-for="option in options"
                            :key="option.id"
                            class="border-surface-200 dark:border-surface-700 border-b p-2 text-center whitespace-nowrap"
                        >
                            <div class="flex flex-col items-center gap-1">
                                <span>{{ formatPollOption(option) }}</span>
                                <Tag
                                    v-if="option.id === poll.chosen_option_id"
                                    value="Chosen"
                                    severity="success"
                                />
                                <Tag
                                    v-else-if="
                                        !poll.closed_at &&
                                        poll.type === 'date_finder' &&
                                        isBest(option)
                                    "
                                    value="Best"
                                    severity="success"
                                />
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="member in members" :key="member.id">
                        <td
                            class="border-surface-200 dark:border-surface-700 sticky left-0 border-b p-2 whitespace-nowrap"
                        >
                            {{ member.name ?? member.email }}
                        </td>
                        <td
                            v-for="option in options"
                            :key="option.id"
                            class="border-surface-200 dark:border-surface-700 border-b p-2 text-center"
                        >
                            <div
                                v-if="member.id === currentUserId"
                                class="flex justify-center gap-1"
                            >
                                <Tag
                                    v-for="choice in statusChoices"
                                    :key="choice.status"
                                    :value="choice.label"
                                    :severity="
                                        responseFor(option, member.id) ===
                                        choice.status
                                            ? statusSeverity(choice.status)
                                            : 'secondary'
                                    "
                                    :class="[
                                        poll.closed_at
                                            ? 'cursor-not-allowed'
                                            : 'cursor-pointer',
                                        {
                                            'opacity-50':
                                                responseFor(
                                                    option,
                                                    member.id,
                                                ) !== choice.status,
                                        },
                                    ]"
                                    @click="
                                        submitResponse(option, choice.status)
                                    "
                                />
                            </div>
                            <Tag
                                v-else
                                :value="
                                    statusLabel(responseFor(option, member.id))
                                "
                                :severity="
                                    statusSeverity(
                                        responseFor(option, member.id),
                                    )
                                "
                            />
                        </td>
                    </tr>
                    <tr>
                        <td class="sticky left-0 p-2 font-medium">Yes votes</td>
                        <td
                            v-for="option in options"
                            :key="option.id"
                            class="p-2 text-center font-medium"
                        >
                            {{ yesCount(option) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
