<script setup lang="ts">
import { X } from '@lucide/vue';
import { computed } from 'vue';
import UserAvatar from '@/components/app/user-avatar.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';

const props = withDefaults(
    defineProps<{
        name: string | null;
        quantity?: number | null;
        unit?: string | null;
        isOwn?: boolean;
        canRemove?: boolean;
        disabled?: boolean;
    }>(),
    {
        quantity: null,
        unit: null,
        isOwn: false,
        canRemove: false,
        disabled: false,
    },
);

defineEmits<{ remove: [] }>();

const { t, locale } = useI18n();

const displayName = computed(() => props.name || t('common.noName'));

const amount = computed(() => {
    if (props.quantity === null) {
        return '';
    }

    const number = props.quantity.toLocaleString(
        locale.value === 'de' ? 'de-CH' : 'en-GB',
    );

    return props.unit ? `${number} ${props.unit}` : number;
});
</script>

<template>
    <div
        class="flex min-h-9 items-center gap-2.5 py-1 text-[15px]"
        data-feature="F-15"
    >
        <UserAvatar :name="name" size="sm" aria-hidden="true" />
        <span class="min-w-0 flex-1 break-words">
            {{ displayName }}
            <span v-if="isOwn" class="text-fg-soft"
                >({{ t('group.member.you') }})</span
            >
        </span>
        <span
            v-if="amount"
            class="font-semibold whitespace-nowrap tabular-nums"
            >{{ amount }}</span
        >
        <Button
            v-if="canRemove"
            type="button"
            variant="ghost"
            size="icon-sm"
            :disabled="disabled"
            :aria-label="
                isOwn
                    ? t('list.claim.removeOwn')
                    : t('list.claim.removeFor', { name: displayName })
            "
            @click="$emit('remove')"
        >
            <X />
        </Button>
    </div>
</template>
