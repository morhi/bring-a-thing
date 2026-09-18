<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import type { Auth, Comment } from '@/types';

const props = defineProps<{
    rosterId: number;
    commentableType: 'roster' | 'roster_item';
    commentableId: number;
    comments: Comment[];
    storeUrl: string;
    destroyUrl: (comment: Comment) => string;
    canManageParent: boolean;
}>();

const page = usePage<{ auth: Auth }>();
const currentUserId = computed(() => page.props.auth.user?.id ?? null);
const confirm = useConfirm();

const localComments = ref<Comment[]>(props.comments ?? []);
watch(
    () => props.comments,
    (value) => {
        localComments.value = value ?? [];
    },
);

useEcho<{ comment: Comment }>(
    `roster.${props.rosterId}`,
    '.comment.posted',
    (e) => {
        if (
            e.comment.commentable_type !== props.commentableType ||
            e.comment.commentable_id !== props.commentableId
        ) {
            return;
        }

        if (
            !localComments.value.some((comment) => comment.id === e.comment.id)
        ) {
            localComments.value = [...localComments.value, e.comment];
        }
    },
);

useEcho<{
    id: number;
    commentable_type: string;
    commentable_id: number;
}>(`roster.${props.rosterId}`, '.comment.deleted', (e) => {
    if (
        e.commentable_type !== props.commentableType ||
        e.commentable_id !== props.commentableId
    ) {
        return;
    }

    localComments.value = localComments.value.filter(
        (comment) => comment.id !== e.id,
    );
});

function avatarLabel(
    name: string | null | undefined,
    email?: string | null,
): string {
    return (name ?? email ?? '?').charAt(0).toUpperCase();
}

function formatTimestamp(timestamp: string): string {
    return new Date(timestamp).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function canDelete(comment: Comment): boolean {
    return comment.user_id === currentUserId.value || props.canManageParent;
}

const form = useForm({ body: '' });

function submit() {
    form.post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function confirmDelete(comment: Comment) {
    confirm.require({
        header: 'Remove comment?',
        message: 'This cannot be undone.',
        acceptLabel: 'Remove',
        acceptProps: { severity: 'danger' },
        rejectLabel: 'Cancel',
        rejectProps: { severity: 'secondary', text: true },
        accept: () =>
            router.delete(props.destroyUrl(comment), { preserveScroll: true }),
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            v-if="localComments.length === 0"
            class="border-surface-200 dark:border-surface-700 text-surface-500 rounded-lg border border-dashed p-6 text-center text-sm"
        >
            No comments yet.
        </div>
        <ul v-else class="flex flex-col gap-3">
            <li
                v-for="comment in localComments"
                :key="comment.id"
                class="flex items-start gap-3"
            >
                <Avatar
                    :label="
                        avatarLabel(comment.user?.name, comment.user?.email)
                    "
                    shape="circle"
                    size="small"
                />
                <div class="flex flex-1 flex-col">
                    <div class="flex items-center gap-2">
                        <span
                            class="text-surface-900 dark:text-surface-0 text-sm font-medium"
                        >
                            {{ comment.user?.name ?? comment.user?.email }}
                        </span>
                        <span class="text-surface-500 text-xs">
                            {{ formatTimestamp(comment.created_at) }}
                        </span>
                    </div>
                    <p class="text-surface-700 dark:text-surface-300 text-sm">
                        {{ comment.body }}
                    </p>
                </div>
                <Button
                    v-if="canDelete(comment)"
                    severity="danger"
                    text
                    size="small"
                    label="Remove"
                    @click="confirmDelete(comment)"
                />
            </li>
        </ul>

        <form class="flex items-start gap-3" @submit.prevent="submit">
            <div class="flex flex-1 flex-col gap-2">
                <Textarea
                    v-model="form.body"
                    rows="2"
                    placeholder="Write a comment..."
                    :invalid="!!form.errors.body"
                />
                <small v-if="form.errors.body" class="text-red-500">
                    {{ form.errors.body }}
                </small>
            </div>
            <Button
                type="submit"
                label="Post"
                size="small"
                :loading="form.processing"
                :disabled="!form.body.trim()"
            />
        </form>
    </div>
</template>
