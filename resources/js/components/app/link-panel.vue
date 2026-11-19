<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Copy, Link as LinkIcon } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { toast } from 'vue-sonner';
import FormField from '@/components/app/form-field.vue';
import SectionBoxItem from '@/components/app/section-box-item.vue';
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
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';

/**
 * Link body shared by the invite link card and the calendar feed settings (F-04, F-26, F-27, F-34).
 *
 * Shows the link with copy and extra actions, or the enable button when no
 * link exists. Regenerating and revoking ask for confirmation first.
 */
const props = defineProps<{
    // Unique per page, ties the field label and input together
    id: string;
    // Null when no link exists yet
    url: string | null;
    createUrl: string;
    regenerateUrl: string;
    revokeUrl: string;
    // The calendar feed regenerates through PUT, invite links through POST
    regenerateMethod?: 'post' | 'put';
    texts?: Partial<LinkTexts>;
}>();

type LinkTexts = {
    none: string;
    create: string;
    revoke: string;
    revokeTitle: string;
    revokeText: string;
    regenerateTitle: string;
    regenerateText: string;
};

const text = computed<LinkTexts>(() => ({
    none: t('groupSettings.link.none'),
    create: t('groupSettings.link.create'),
    revoke: t('groupSettings.link.revoke'),
    revokeTitle: t('groupSettings.link.revokeTitle'),
    revokeText: t('groupSettings.link.revokeText'),
    regenerateTitle: t('groupSettings.link.regenerateTitle'),
    regenerateText: t('groupSettings.link.regenerateText'),
    ...props.texts,
}));

const { t } = useI18n();

const copied = ref(false);
let copiedTimer: ReturnType<typeof setTimeout> | undefined;

function showRequestError(errors: Record<string, string>): void {
    toast.error(Object.values(errors)[0] ?? t('group.requestFailed'), {
        duration: 6000,
    });
}

const visitOptions = { preserveScroll: true, onError: showRequestError };

async function copyLink(): Promise<void> {
    if (!props.url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.url);
    } catch {
        toast.error(t('group.link.copyFailed'), {
            description: props.url,
            duration: 10000,
        });

        return;
    }

    copied.value = true;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => (copied.value = false), 2000);
}

onBeforeUnmount(() => clearTimeout(copiedTimer));

function createLink(): void {
    router.post(props.createUrl, {}, visitOptions);
}

type Confirmation = {
    title: string;
    text: string;
    action: string;
    run: () => void;
};

const confirmation = ref<Confirmation | null>(null);
const confirmOpen = ref(false);

function ask(entry: Confirmation): void {
    confirmation.value = entry;
    confirmOpen.value = true;
}

function confirmRevoke(): void {
    ask({
        title: text.value.revokeTitle,
        text: text.value.revokeText,
        action: text.value.revoke,
        run: () => router.delete(props.revokeUrl, visitOptions),
    });
}

function confirmRegenerate(): void {
    ask({
        title: text.value.regenerateTitle,
        text: text.value.regenerateText,
        action: t('groupSettings.link.regenerate'),
        run: () =>
            router.visit(props.regenerateUrl, {
                ...visitOptions,
                method: props.regenerateMethod ?? 'post',
            }),
    });
}

const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring h-(--control-height) rounded-(--control-radius) px-3.5 text-[15px]';
</script>

<template>
    <div class="flex min-w-0 flex-col gap-(--space-stack)">
        <SectionBoxItem
            v-if="url"
            class="flex flex-col gap-(--space-stack) p-5 sm:p-6"
        >
            <FormField
                :label="t('groupSettings.link.url')"
                :control="`${id}-url`"
            >
                <div class="flex items-center gap-2">
                    <Input
                        :id="`${id}-url`"
                        :model-value="url"
                        readonly
                        :class="[
                            inputClass,
                            'bg-surface-muted text-fg-muted min-w-0 flex-1 text-ellipsis',
                        ]"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        class="h-(--control-height)"
                        @click="copyLink"
                    >
                        <Check v-if="copied" aria-hidden="true" />
                        <Copy v-else aria-hidden="true" />
                        {{
                            copied
                                ? t('group.link.copied')
                                : t('groupSettings.link.copy')
                        }}
                    </Button>
                    <span class="sr-only" aria-live="polite">{{
                        copied ? t('group.link.copied') : ''
                    }}</span>
                </div>
            </FormField>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <slot name="actions" />
                <Button
                    type="button"
                    variant="outline"
                    size="lg"
                    @click="confirmRegenerate"
                >
                    {{ t('groupSettings.link.regenerate') }}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    size="lg"
                    @click="confirmRevoke"
                >
                    {{ text.revoke }}
                </Button>
            </div>
        </SectionBoxItem>
        <SectionBoxItem
            v-else
            class="flex flex-col items-start gap-4 p-5 sm:p-6"
        >
            <p class="text-fg-muted">{{ text.none }}</p>
            <Button type="button" size="lg" @click="createLink">
                <LinkIcon aria-hidden="true" />
                {{ text.create }}
            </Button>
        </SectionBoxItem>

        <AlertDialog v-model:open="confirmOpen">
            <AlertDialogContent v-if="confirmation">
                <AlertDialogHeader>
                    <AlertDialogTitle>{{
                        confirmation.title
                    }}</AlertDialogTitle>
                    <AlertDialogDescription>{{
                        confirmation.text
                    }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{
                        t('dialog.cancel')
                    }}</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        @click="confirmation.run()"
                        >{{ confirmation.action }}</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
