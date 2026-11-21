<script setup lang="ts">
import { MessageSquareText } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

/**
 * Open the dialog for the own note on an option (F-06).
 *
 * Without a note it stays hidden until the surrounding `group/note` element is hovered or focused,
 * so the compact cells keep their calm look; touch screens without hover and an existing note always show it.
 */
const props = withDefaults(
    defineProps<{
        note?: string | null;
        // Spoken option description, so each button names its option
        optionLabel: string;
        size?: 'xs' | 'md' | 'sm';
        // Off where the button stands alone, like the day panel on touch screens
        subtle?: boolean;
    }>(),
    { note: null, size: 'sm', subtle: true },
);

const emit = defineEmits<{ open: [] }>();

const { t } = useI18n();

const label = computed(() =>
    [
        props.note ? t('poll.note.edit') : t('poll.note.add'),
        props.optionLabel,
        props.note,
    ]
        .filter(Boolean)
        .join(': '),
);
</script>

<template>
    <button
        type="button"
        :aria-label="label"
        :title="note ?? undefined"
        data-feature="F-06"
        :class="
            cn(
                'focus-visible:outline-focus-ring inline-grid shrink-0 cursor-pointer place-items-center rounded-md transition-opacity duration-(--duration-fast) focus-visible:outline-2 focus-visible:outline-offset-1',
                'hover:bg-surface-sunken',
                { xs: 'size-4', md: 'size-6', sm: 'size-7' }[size],
                note && 'text-primary',
                !note && 'text-fg-soft',
                !note &&
                    subtle &&
                    'opacity-0 group-focus-within/note:opacity-100 group-hover/note:opacity-100 focus-visible:opacity-100 [@media(hover:none)]:opacity-100',
            )
        "
        @click="emit('open')"
    >
        <MessageSquareText
            :class="size === 'xs' ? 'size-3' : 'size-4'"
            :fill="note ? 'currentColor' : 'none'"
            :fill-opacity="note ? 0.2 : 0"
            aria-hidden="true"
        />
    </button>
</template>
