<script setup lang="ts">
import { storeEvent } from '@/actions/App/Http/Controllers/EntityJoinController';
import EntityJoin from '@/components/app/entity-join.vue';
import WebsiteLayout from '@/layouts/WebsiteLayout.vue';

defineOptions({ layout: WebsiteLayout });

/**
 * Join page behind a event's invite link; null `join` is the "Not found" state.
 */
defineProps<{ join: App.Data.EntityJoinData | null }>();
</script>

<template>
    <EntityJoin
        :join="join"
        kind="event"
        :submit-url="(token) => storeEvent(token).url"
    />
</template>
