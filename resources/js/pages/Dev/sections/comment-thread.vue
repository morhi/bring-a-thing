<script setup lang="ts">
import { ref } from 'vue';
import type { ThreadComment } from '@/components/app/comment-thread.vue';
import CommentThread from '@/components/app/comment-thread.vue';

const sample: ThreadComment[] = [
    {
        id: 1,
        authorName: 'Anna Berger',
        createdAt: '2026-10-24T18:05:00+02:00',
        body: 'Are the chairs for inside or the terrace?',
        canDelete: false,
    },
    {
        id: 2,
        authorName: 'Maurice Hildebrandt',
        createdAt: '2026-10-24T19:42:00+02:00',
        body: 'Terrace. Folding ones are best.',
        canDelete: true,
    },
    {
        id: 3,
        authorName: null,
        createdAt: '2026-10-25T08:10:00+02:00',
        body: 'I can bring four.',
        canDelete: false,
    },
];

const long: ThreadComment[] = [
    {
        id: 4,
        authorName: 'Maximiliane Theresia von Hohenzollern-Sigmaringen',
        createdAt: '2026-10-25T21:30:00+02:00',
        body: 'A long comment that wraps over several lines to check spacing.\nIt keeps line breaks.\n\nAnd a very long word: Donaudampfschifffahrtsgesellschaftskapitänsmützenabzeichenhersteller.',
        canDelete: true,
    },
];

// Interactive sample: posting and deleting change the local list, an empty send shows the server error.
const live = ref<ThreadComment[]>([...sample]);
const draft = ref('');
const liveError = ref<string | null>(null);
let nextId = 100;

function onSubmit(body: string) {
    if (body.length > 500) {
        liveError.value = 'The comment may not be longer than 500 characters.';

        return;
    }

    liveError.value = null;
    live.value = [
        ...live.value,
        {
            id: nextId++,
            authorName: 'Maurice Hildebrandt',
            createdAt: new Date().toISOString(),
            body,
            canDelete: true,
        },
    ];
    draft.value = '';
}

function onDelete(id: ThreadComment['id']) {
    live.value = live.value.filter((comment) => comment.id !== id);
}
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="comment-thread" class="text-xl">CommentThread</h2>

        <div class="box flex flex-col gap-3 p-5">
            <p class="text-fg-muted text-sm font-semibold">
                Interactive (Cmd/Ctrl+Enter sends, over 500 characters returns
                an error)
            </p>
            <CommentThread
                v-model="draft"
                :comments="live"
                :error="liveError"
                @submit="onSubmit"
                @delete="onDelete"
            />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="box flex flex-col gap-3 p-5">
                <p class="text-fg-muted text-sm font-semibold">Empty</p>
                <CommentThread :comments="[]" />
            </div>
            <div class="box flex flex-col gap-3 p-5">
                <p class="text-fg-muted text-sm font-semibold">
                    Compact (item), read-only (cannot post)
                </p>
                <CommentThread :comments="sample" compact :can-post="false" />
            </div>
            <div class="box flex flex-col gap-3 p-5">
                <p class="text-fg-muted text-sm font-semibold">
                    Long text and long name
                </p>
                <CommentThread :comments="long" :can-post="false" />
            </div>
            <div class="box flex flex-col gap-3 p-5">
                <p class="text-fg-muted text-sm font-semibold">
                    Error and processing
                </p>
                <CommentThread
                    :comments="sample.slice(0, 1)"
                    model-value="Hello"
                    error="The comment field is required."
                    processing
                />
            </div>
        </div>
    </section>
</template>
