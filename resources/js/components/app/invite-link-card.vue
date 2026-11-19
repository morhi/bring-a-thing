<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import LinkPanel from '@/components/app/link-panel.vue';
import SectionBox from '@/components/app/section-box.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';

/**
 * Invite link card shared by groups, events and polls (F-04, F-26, F-27).
 *
 * Wraps the shared link panel in a titled box and adds the open action.
 */
defineProps<{
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
</script>

<template>
    <SectionBox :id="`${id}-title`" :title="t('groupSettings.link.title')">
        <template #action>
            <p class="text-fg-soft max-w-[70ch] text-sm">{{ hint }}</p>
        </template>
        <LinkPanel
            :id="id"
            :url="url"
            :create-url="createUrl"
            :regenerate-url="regenerateUrl"
            :revoke-url="revokeUrl"
        >
            <template #actions>
                <Button as-child variant="ghost" size="lg">
                    <a :href="url ?? undefined" target="_blank" rel="noopener">
                        <ExternalLink aria-hidden="true" />
                        {{ t('groupSettings.link.openJoinPage') }}
                    </a>
                </Button>
            </template>
        </LinkPanel>
    </SectionBox>
</template>
