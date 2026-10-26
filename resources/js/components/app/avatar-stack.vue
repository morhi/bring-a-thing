<script setup lang="ts">
import { computed } from 'vue';
import UserAvatar from '@/components/app/user-avatar.vue';
import { useI18n } from '@/composables/use-i18n';

export type AvatarStackPerson = {
    id: number | string;
    name?: string | null;
    email?: string | null;
};

const props = defineProps<{
    people: AvatarStackPerson[];
    // How many avatars show before the "+n" counter; the caller decides
    max: number;
}>();

const { t } = useI18n();

const shown = computed(() => props.people.slice(0, Math.max(0, props.max)));
const rest = computed(() => props.people.length - shown.value.length);
const names = computed(() => props.people.map((person) => person.name || person.email).filter(Boolean).join(', '));
</script>

<template>
    <span class="inline-flex items-center" role="group" :aria-label="names">
        <UserAvatar
            v-for="person in shown"
            :key="person.id"
            :name="person.name"
            :email="person.email"
            size="sm"
            class="-ml-2.5 ring-2 ring-surface-raised first:ml-0"
        />
        <span v-if="rest > 0" class="ml-1.5 text-[13px] font-semibold text-fg-muted">{{ t('common.more', { n: rest }) }}</span>
    </span>
</template>
