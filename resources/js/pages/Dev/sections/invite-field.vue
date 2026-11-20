<script setup lang="ts">
import { ref } from 'vue';
import InviteField from '@/components/app/invite-field.vue';
import type {
    InviteFriend,
    InviteSelection,
} from '@/components/app/invite-field.vue';

const friends: InviteFriend[] = [
    { id: 2, name: 'Anna Keller', email: 'anna@example.com' },
    { id: 3, name: 'Ben Huber', email: 'ben@example.com' },
    { id: 4, name: null, email: 'carla@example.com' },
    {
        id: 5,
        name: 'Dominik Alexander Schwarzenbach-Oberholzer',
        email: 'dominik.alexander.schwarzenbach-oberholzer@example.com',
    },
    { id: 6, name: 'Eva Meier', email: 'eva@example.com' },
];

const createInvites = ref<InviteSelection[]>([]);
const groupInvites = ref<InviteSelection[]>([
    { accountId: 2, email: 'anna@example.com', name: 'Anna Keller' },
    { accountId: null, email: 'new.person@example.com' },
    {
        accountId: 5,
        email: 'dominik.alexander.schwarzenbach-oberholzer@example.com',
        name: 'Dominik Alexander Schwarzenbach-Oberholzer',
    },
]);
const settingsInvites = ref<InviteSelection[]>([]);
const log = ref<string[]>([]);

function record(entry: string): void {
    log.value = [entry, ...log.value].slice(0, 4);
}
</script>

<template>
    <section class="space-y-6">
        <h2 id="invite-field" class="font-display text-2xl">InviteField</h2>
        <p class="text-fg-muted text-sm">
            Type a name or email. Arrow keys move through suggestions, Enter or
            comma adds, Escape closes, Backspace on an empty input removes the
            last chip. Ben is already a member; eva@example.com typed as a new
            address shows the disabled state too.
        </p>

        <div class="box grid grid-cols-1 gap-8 p-6">
            <div class="min-w-0 space-y-2">
                <h3 class="font-display text-lg">New group</h3>
                <InviteField
                    id="dev-if-create"
                    v-model="createInvites"
                    label="Invite people now"
                    hint="Optional. You can also invite people later from the group page."
                    :friends="friends"
                />
            </div>

            <div class="min-w-0 space-y-2">
                <h3 class="font-display text-lg">Group page (chips, submit)</h3>
                <InviteField
                    id="dev-if-group"
                    v-model="groupInvites"
                    label="Invite someone"
                    hint="They get a login link by email and appear here right away."
                    submit-label="Invite"
                    :friends="friends"
                    :member-ids="[3]"
                    :member-emails="['ben@example.com', 'eva@example.com']"
                    @submit="record(`submit ${groupInvites.length}`)"
                />
                <p class="text-fg-muted text-sm" aria-live="polite">
                    Events: {{ log.join(', ') || 'none' }}
                </p>
            </div>

            <div class="min-w-0 space-y-2">
                <h3 class="font-display text-lg">Group settings, error</h3>
                <InviteField
                    id="dev-if-error"
                    v-model="settingsInvites"
                    label="Invite someone"
                    error="This email address is not valid."
                    submit-label="Invite"
                    :friends="friends"
                />
            </div>

            <div class="min-w-0 space-y-2">
                <h3 class="font-display text-lg">Disabled</h3>
                <InviteField
                    id="dev-if-disabled"
                    :model-value="[
                        {
                            accountId: 2,
                            email: 'anna@example.com',
                            name: 'Anna Keller',
                        },
                    ]"
                    label="Invite someone"
                    submit-label="Invite"
                    :friends="friends"
                    disabled
                />
            </div>
        </div>
    </section>
</template>
