<script setup lang="ts">
import { ref } from 'vue';
import type {
    ClaimEntry,
    ClaimId,
    ClaimRequest,
} from '@/components/app/claim-controls.vue';
import CommentThread from '@/components/app/comment-thread.vue';
import type { ThreadComment } from '@/components/app/comment-thread.vue';
import ItemRow from '@/components/app/item-row.vue';

const others: ClaimEntry[] = [
    {
        id: 1,
        accountId: 11,
        name: 'Anna Berger',
        quantity: 6,
        isOwn: false,
        canRemove: true,
    },
    {
        id: 2,
        accountId: 12,
        name: 'Ben Carter',
        quantity: 4,
        isOwn: false,
        canRemove: true,
    },
];

const comments: ThreadComment[] = [
    {
        id: 1,
        authorName: 'Anna Berger',
        createdAt: '2026-10-24T18:05:00+02:00',
        body: 'Vegan, please.',
        canDelete: true,
    },
];

const liveClaims = ref<ClaimEntry[]>([
    {
        id: 10,
        accountId: 11,
        name: 'Anna Berger',
        quantity: 1,
        isOwn: false,
        canRemove: false,
    },
]);
const commentsOpen = ref(true);
const draft = ref('');
let nextId = 100;

function onClaim(request: ClaimRequest) {
    liveClaims.value = [
        ...liveClaims.value.filter((claim) => !claim.isOwn),
        {
            id: nextId++,
            accountId: 10,
            name: 'Maurice Hildebrandt',
            quantity: request.quantity,
            isOwn: true,
            canRemove: true,
        },
    ];
}

function onRelease(id: ClaimId) {
    liveClaims.value = liveClaims.value.filter((claim) => claim.id !== id);
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="item-row" class="text-xl">ItemRow</h2>

        <ul class="flex flex-col gap-3">
            <li>
                <ItemRow
                    v-model:comments-open="commentsOpen"
                    :item="{
                        id: 'live',
                        name: 'Potato salad',
                        quantity: 3,
                        unit: 'kg',
                        notes: 'Without mayonnaise.',
                        fields: [{ name: 'Allergens', value: 'Mustard' }],
                        attendanceDay: { label: '14 Nov 2026', href: '#' },
                    }"
                    :claims="liveClaims"
                    :comment-count="comments.length"
                    can-comment
                    can-edit
                    @claim="onClaim"
                    @release="onRelease"
                >
                    <template #comments>
                        <CommentThread
                            v-model="draft"
                            :comments="comments"
                            compact
                        />
                    </template>
                </ItemRow>
            </li>
            <li>
                <ItemRow
                    :item="{
                        id: 'complete',
                        name: 'Chairs',
                        quantity: 10,
                        unit: null,
                    }"
                    :claims="others"
                    can-comment
                    can-edit
                />
            </li>
            <li>
                <ItemRow
                    :item="{
                        id: 'task',
                        name: 'Set up the PA system',
                        quantity: null,
                    }"
                    :claims="[]"
                    can-comment
                    attendance-warning="You have not answered for 14 Nov 2026 yet."
                />
            </li>
            <li>
                <ItemRow
                    :item="{
                        id: 'locked',
                        name: 'Drinks',
                        quantity: 24,
                        unit: 'bottles',
                    }"
                    :claims="others.slice(0, 1)"
                    locked
                    can-comment
                    :comment-count="2"
                />
            </li>
            <li>
                <ItemRow
                    :item="{
                        id: 'long',
                        name: 'Homemade sparkling elderflower lemonade in returnable glass bottles with matching lids',
                        quantity: 1250.5,
                        unit: 'millilitres',
                        notes: 'A very long note that wraps over several lines on a narrow screen and should never cause horizontal scrolling on the page.',
                    }"
                    :claims="[]"
                    :candidates="[{ id: 13, name: 'Chiara Rossi' }]"
                />
            </li>
        </ul>
    </section>
</template>
