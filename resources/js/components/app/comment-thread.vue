<script lang="ts">
export type ThreadComment = {
    id: number | string;
    authorName: string | null;
    // ISO 8601 timestamp.
    createdAt: string;
    body: string;
    canDelete: boolean;
};
</script>

<script setup lang="ts">
import { MessageSquare, X } from '@lucide/vue';
import { computed, onMounted, ref, useId } from 'vue';
import EmptyState from '@/components/app/empty-state.vue';
import UserAvatar from '@/components/app/user-avatar.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        comments: ThreadComment[];
        canPost?: boolean;
        compact?: boolean;
        error?: string | null;
        processing?: boolean;
    }>(),
    { canPost: true, compact: false, error: null, processing: false },
);

const emit = defineEmits<{
    submit: [body: string];
    delete: [id: ThreadComment['id']];
}>();

// The draft lives with the parent, so it can clear it after a successful post.
const draft = defineModel<string>({ default: '' });

const { t, locale } = useI18n();

const controlId = useId();
const hintId = useId();
const errorId = useId();

// Resolved after mount so server and client render the same hint.
const modifierKey = ref('Ctrl');

onMounted(() => {
    if (!/Mac|iPhone|iPad/.test(navigator.userAgent)) {
        return;
    }

    modifierKey.value = 'Cmd';
});

const sorted = computed(() =>
    [...props.comments].sort((a, b) => a.createdAt.localeCompare(b.createdAt)),
);
const canSend = computed(() => draft.value.trim() !== '' && !props.processing);

function formatWhen(iso: string): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    const isGerman = locale.value === 'de';
    const day = date.toLocaleDateString(
        isGerman ? 'de-CH' : 'en-GB',
        isGerman
            ? { day: '2-digit', month: '2-digit', year: 'numeric' }
            : { day: 'numeric', month: 'short', year: 'numeric' },
    );
    const time = date.toLocaleTimeString(isGerman ? 'de-CH' : 'en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    });

    return `${day}, ${time}`;
}

function send() {
    if (!canSend.value) {
        return;
    }

    emit('submit', draft.value.trim());
}

function onKeydown(event: KeyboardEvent) {
    if (event.key !== 'Enter' || !(event.metaKey || event.ctrlKey)) {
        return;
    }

    event.preventDefault();
    send();
}

const deleteTarget = ref<ThreadComment['id'] | null>(null);
const confirmOpen = ref(false);

function askDelete(comment: ThreadComment) {
    deleteTarget.value = comment.id;
    confirmOpen.value = true;
}

function confirmDelete() {
    if (deleteTarget.value === null) {
        return;
    }

    emit('delete', deleteTarget.value);
    confirmOpen.value = false;
}
</script>

<template>
    <div
        :class="cn('flex flex-col', compact ? 'gap-3' : 'gap-4')"
        data-feature="F-20"
    >
        <ul
            v-if="sorted.length"
            :class="cn('flex flex-col', compact ? 'gap-3' : 'gap-3.5')"
        >
            <li
                v-for="comment in sorted"
                :key="comment.id"
                class="flex items-start gap-2.5"
            >
                <UserAvatar
                    :name="comment.authorName"
                    size="sm"
                    aria-hidden="true"
                />
                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <div
                        class="text-fg-soft flex flex-wrap items-center gap-2 text-[13px]"
                    >
                        <span
                            class="text-fg min-w-0 text-sm font-semibold [overflow-wrap:anywhere]"
                            >{{
                                comment.authorName || t('common.noName')
                            }}</span
                        >
                        <time :datetime="comment.createdAt">{{
                            formatWhen(comment.createdAt)
                        }}</time>
                        <Button
                            v-if="comment.canDelete"
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="ml-auto"
                            :disabled="processing"
                            :aria-label="t('list.comments.delete')"
                            @click="askDelete(comment)"
                        >
                            <X />
                        </Button>
                    </div>
                    <p
                        class="text-[15px] [overflow-wrap:anywhere] whitespace-pre-wrap"
                    >
                        {{ comment.body }}
                    </p>
                </div>
            </li>
        </ul>
        <EmptyState
            v-else
            :title="t('list.comments.none')"
            :icon="MessageSquare"
        />

        <form
            v-if="canPost"
            class="flex flex-col items-start gap-2"
            @submit.prevent="send"
        >
            <div class="flex w-full flex-col gap-2">
                <Label :for="controlId">{{ t('list.comments.write') }}</Label>
                <Textarea
                    :id="controlId"
                    v-model="draft"
                    rows="2"
                    :class="compact ? 'min-h-14' : 'min-h-16'"
                    :placeholder="t('list.comments.placeholder')"
                    :aria-invalid="error ? 'true' : undefined"
                    :aria-describedby="error ? `${errorId} ${hintId}` : hintId"
                    @keydown="onKeydown"
                />
                <p
                    v-if="error"
                    :id="errorId"
                    role="alert"
                    class="text-danger text-sm"
                >
                    {{ error }}
                </p>
                <p :id="hintId" class="text-fg-muted text-sm">
                    {{ t('list.comments.hint', { key: modifierKey }) }}
                </p>
            </div>
            <Button
                type="submit"
                variant="secondary"
                size="sm"
                :disabled="!canSend"
                >{{ t('list.comments.send') }}</Button
            >
        </form>

        <AlertDialog v-model:open="confirmOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{
                        t('list.comments.deleteTitle')
                    }}</AlertDialogTitle>
                    <AlertDialogDescription>{{
                        t('list.comments.deleteText')
                    }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{
                        t('dialog.cancel')
                    }}</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        @click="confirmDelete"
                        >{{ t('list.comments.delete') }}</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
