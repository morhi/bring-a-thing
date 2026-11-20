<script setup lang="ts">
import { ExternalLink, Share2 } from '@lucide/vue';
import LinkPanel from '@/components/app/link-panel.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/use-i18n';

/**
 * Share button with its dialog for groups, events, polls and lists (F-04, F-19, F-26, F-27).
 *
 * Shows the link with "Copy" and lets whoever may manage it create,
 * regenerate and revoke it. The page decides who sees the button by
 * mounting this component only for those people.
 */
withDefaults(
    defineProps<{
        // Unique per page, ties the field label and input together
        id: string;
        // Null when no link exists yet
        url: string | null;
        createUrl: string;
        regenerateUrl: string;
        revokeUrl: string;
        hint: string;
        // Overrides the invite link wording, for example for list share links
        texts?: Record<string, string>;
        // Wording of the action that opens the shared page, defaults to the join page
        openLabel?: string;
        // Matches the sibling header buttons of the page
        size?: 'default' | 'sm' | 'lg';
        triggerClass?: string;
    }>(),
    { size: 'lg', triggerClass: 'h-(--control-height) px-4' },
);

const { t } = useI18n();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button
                type="button"
                variant="outline"
                :size="size"
                :class="triggerClass"
            >
                <Share2 aria-hidden="true" />
                {{ t('share.button') }}
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('share.dialogTitle') }}</DialogTitle>
                <DialogDescription>{{ hint }}</DialogDescription>
            </DialogHeader>
            <LinkPanel
                :id="id"
                :url="url"
                :create-url="createUrl"
                :regenerate-url="regenerateUrl"
                :revoke-url="revokeUrl"
                :texts="texts"
            >
                <template #actions>
                    <Button as-child variant="ghost" size="lg">
                        <a
                            :href="url ?? undefined"
                            target="_blank"
                            rel="noopener"
                        >
                            <ExternalLink aria-hidden="true" />
                            {{
                                openLabel ??
                                t('groupSettings.link.openJoinPage')
                            }}
                        </a>
                    </Button>
                </template>
            </LinkPanel>
        </DialogContent>
    </Dialog>
</template>
