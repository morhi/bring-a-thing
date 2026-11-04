<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Copy, ExternalLink, Link as LinkIcon } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import { toast } from 'vue-sonner';
import FormField from '@/components/app/form-field.vue';
import SectionBox from '@/components/app/section-box.vue';
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
 * Invite link card shared by groups, events and polls (F-04, F-26, F-27).
 *
 * Shows the link with copy and open actions, or the enable button when no
 * link exists. Regenerating and revoking ask for confirmation first.
 */
const props = defineProps<{
    // Unique per page, ties the card heading and the field label together
    id: string;
    // Null when no link exists yet
    url: string | null;
    createUrl: string;
    regenerateUrl: string;
    revokeUrl: string;
    hint: string;
}>();

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
        title: t('groupSettings.link.revokeTitle'),
        text: t('groupSettings.link.revokeText'),
        action: t('groupSettings.link.revoke'),
        run: () => router.delete(props.revokeUrl, visitOptions),
    });
}

function confirmRegenerate(): void {
    ask({
        title: t('groupSettings.link.regenerateTitle'),
        text: t('groupSettings.link.regenerateText'),
        action: t('groupSettings.link.regenerate'),
        run: () => router.post(props.regenerateUrl, {}, visitOptions),
    });
}

const inputClass =
    'bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring h-(--control-height) rounded-(--control-radius) px-3.5 text-[15px]';
const buttonClass = 'h-(--control-height) px-4';
</script>

<template>
    <SectionBox :id="`${id}-title`" :title="t('groupSettings.link.title')">
        <template #action>
            <p class="text-fg-soft max-w-[70ch] text-sm">{{ hint }}</p>
        </template>
        <SectionBoxItem
            v-if="url"
            class="flex flex-col gap-(--space-stack) p-5 sm:p-6"
        >
            <FormField
                :label="t('groupSettings.link.url')"
                :control="`${id}-url`"
            >
                <div class="flex flex-wrap gap-2">
                    <Input
                        :id="`${id}-url`"
                        :model-value="url"
                        readonly
                        :class="[
                            inputClass,
                            'bg-surface-muted text-fg-muted min-w-0 flex-[1_1_220px] text-ellipsis',
                        ]"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        :class="buttonClass"
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
                <Button as-child variant="ghost">
                    <a :href="url" target="_blank" rel="noopener">
                        <ExternalLink aria-hidden="true" />
                        {{ t('groupSettings.link.openJoinPage') }}
                    </a>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    @click="confirmRegenerate"
                >
                    {{ t('groupSettings.link.regenerate') }}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    @click="confirmRevoke"
                >
                    {{ t('groupSettings.link.revoke') }}
                </Button>
            </div>
        </SectionBoxItem>
        <SectionBoxItem
            v-else
            class="flex flex-col items-start gap-4 p-5 sm:p-6"
        >
            <p class="text-fg-muted">{{ t('groupSettings.link.none') }}</p>
            <Button
                type="button"
                size="lg"
                :class="buttonClass"
                @click="createLink"
            >
                <LinkIcon aria-hidden="true" />
                {{ t('groupSettings.link.create') }}
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
    </SectionBox>
</template>
