<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { note as storeNote } from '@/actions/App/Http/Controllers/PollResponseController';
import FormField from '@/components/app/form-field.vue';
import { formatDate } from '@/components/app/poll/dates';
import type { PollGridOption } from '@/components/app/poll/types';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';

/**
 * Edit the current person's note on their own answer to one option (F-06).
 */
const props = defineProps<{
    pollSlug: string;
    // The option whose note is edited; null while the dialog is closed
    option: PollGridOption | null;
    note: string | null;
    maxLength: number;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t, locale } = useI18n();

const form = useForm({ note: '' });
const confirmRemoveOpen = ref(false);

// Each opening starts from the saved note, so a cancelled edit does not linger
watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.note = props.note ?? '';
});

const title = computed(() => {
    if (!props.option) {
        return '';
    }

    const date = formatDate(props.option.date, locale.value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
    const time =
        props.option.start && props.option.end
            ? ` ${props.option.start}–${props.option.end}`
            : '';

    return t('poll.note.title', { date: `${date}${time}` });
});

const counter = computed(() => `${form.note.length} / ${props.maxLength}`);

function submit(): void {
    if (!props.option) {
        return;
    }

    form.put(
        storeNote({ poll: props.pollSlug, option: String(props.option.id) })
            .url,
        {
            only: ['page'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                open.value = false;
            },
        },
    );
}

function save(): void {
    // An emptied field would delete the note, so it takes the same confirmation as Remove
    if (props.note && form.note.trim() === '') {
        confirmRemoveOpen.value = true;

        return;
    }

    submit();
}

function remove(): void {
    form.note = '';
    submit();
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="box-float max-h-[calc(100dvh-2rem)] overflow-y-auto p-6 sm:max-w-md"
        >
            <DialogHeader>
                <DialogTitle class="font-display text-xl">{{
                    title
                }}</DialogTitle>
                <DialogDescription class="sr-only">{{
                    title
                }}</DialogDescription>
            </DialogHeader>
            <form
                id="poll-note-form"
                class="flex flex-col gap-(--space-stack)"
                novalidate
                data-feature="F-06"
                @submit.prevent="save"
            >
                <FormField
                    :label="t('poll.note.label')"
                    control="poll-note"
                    :hint="counter"
                    :error="form.errors.note"
                >
                    <template #default="{ describedBy, invalid }">
                        <Input
                            id="poll-note"
                            v-model="form.note"
                            :maxlength="maxLength"
                            autocomplete="off"
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid || undefined"
                            class="bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring h-(--control-height) rounded-(--control-radius) px-3.5 text-[15px]"
                        />
                    </template>
                </FormField>
            </form>
            <DialogFooter class="flex-wrap gap-2">
                <Button
                    v-if="note"
                    type="button"
                    variant="ghost"
                    class="text-danger sm:mr-auto"
                    :disabled="form.processing"
                    @click="confirmRemoveOpen = true"
                >
                    {{ t('poll.note.remove') }}
                </Button>
                <Button type="button" variant="outline" @click="open = false">{{
                    t('dialog.cancel')
                }}</Button>
                <Button
                    type="submit"
                    form="poll-note-form"
                    :disabled="form.processing"
                >
                    {{ t('poll.note.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="confirmRemoveOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t('poll.note.removeTitle')
                }}</AlertDialogTitle>
                <AlertDialogDescription>{{
                    t('poll.note.removeText')
                }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('dialog.cancel') }}</AlertDialogCancel>
                <AlertDialogAction variant="destructive" @click="remove">{{
                    t('poll.note.remove')
                }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
