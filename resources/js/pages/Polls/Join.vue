<script setup lang="ts">
import { storePoll } from '@/actions/App/Http/Controllers/EntityJoinController';
import EntityJoin from '@/components/app/entity-join.vue';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';

defineOptions({ layout: WebsiteLayout });

/**
 * Join page behind a poll's invite link; null `join` is the "Not found" state.
 */
defineProps<{ join: App.Data.EntityJoinData | null }>();
</script>

<template>
    <EntityJoin
        :join="join"
        kind="poll"
        :submit-url="(token) => storePoll(token).url"
    />
</template>
