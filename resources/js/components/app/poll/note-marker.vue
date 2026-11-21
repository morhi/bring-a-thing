<script setup lang="ts">
import { MessageSquareText } from '@lucide/vue';
import { computed } from 'vue';
import type { PollOptionNote } from '@/components/app/poll/types';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

/**
 * Mark that other people left notes on an option; hover or keyboard focus shows them (F-06).
 */
const props = defineProps<{
    notes: PollOptionNote[];
    class?: string;
}>();

const { t } = useI18n();

const lines = computed(() =>
    props.notes.map((entry) =>
        t('poll.note.marker', { name: entry.name, note: entry.note }),
    ),
);
</script>

<template>
    <TooltipProvider v-if="notes.length" :delay-duration="150">
        <Tooltip>
            <TooltipTrigger as-child>
                <!-- Focusable so keyboard users reach the text; the label carries it for screen readers -->
                <span
                    tabindex="0"
                    role="img"
                    :aria-label="lines.join('; ')"
                    data-feature="F-06"
                    :class="
                        cn(
                            'focus-visible:outline-focus-ring inline-grid size-4 shrink-0 cursor-help place-items-center rounded-sm focus-visible:outline-2 focus-visible:outline-offset-1',
                            props.class,
                        )
                    "
                >
                    <MessageSquareText
                        class="text-fg-muted size-3"
                        aria-hidden="true"
                    />
                </span>
            </TooltipTrigger>
            <TooltipContent
                class="flex-col items-start gap-0.5 whitespace-pre-line"
            >
                <span v-for="(line, index) in lines" :key="index">{{
                    line
                }}</span>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
