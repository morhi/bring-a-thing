<script setup lang="ts">
import { computed } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        name?: string | null;
        email?: string | null;
        // 1 to 5; derived from the name or email when omitted so one person keeps one color
        tone?: number;
        size?: 'sm' | 'default' | 'lg';
        class?: HTMLAttributes['class'];
    }>(),
    { name: null, email: null, tone: undefined, size: 'default' },
);

const { t } = useI18n();

// A shadow account has only an email until the person names themselves
const isShadow = computed(() => !props.name);

const label = computed(() => props.name || props.email || t('common.noName'));

const initials = computed(() => {
    if (props.name) {
        return props.name
            .split(/\s+/)
            .filter(Boolean)
            .map((part) => Array.from(part)[0])
            .slice(0, 2)
            .join('')
            .toUpperCase();
    }

    return props.email ? props.email.slice(0, 2).toUpperCase() : '?';
});

const toneIndex = computed(() => {
    if (props.tone) {
        return ((Math.round(props.tone) - 1) % 5 + 5) % 5;
    }

    const seed = props.name || props.email || '';
    let hash = 0;

    for (const char of seed) {
        hash = (hash * 31 + char.codePointAt(0)!) >>> 0;
    }

    return hash % 5;
});

const toneClasses = [
    'bg-success-soft text-success',
    'bg-warning-soft text-warning',
    'bg-info-soft text-info',
    'bg-danger-soft text-danger',
    'bg-surface-sunken text-fg-muted',
];

const textSize = { sm: 'text-[10px]', default: 'text-xs', lg: 'text-base' };
</script>

<template>
    <Avatar
        role="img"
        :aria-label="label"
        :title="label"
        :size="size"
        :class="cn('overflow-hidden', props.class)"
    >
        <AvatarFallback
            :class="
                cn(
                    'font-bold tracking-wide',
                    textSize[size],
                    isShadow
                        ? 'border-2 border-dashed border-border-strong bg-transparent text-fg-muted'
                        : toneClasses[toneIndex],
                )
            "
        >
            {{ initials }}
        </AvatarFallback>
    </Avatar>
</template>
