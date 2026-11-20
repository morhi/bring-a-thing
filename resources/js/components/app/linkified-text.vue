<script setup lang="ts">
import { computed } from 'vue';
import { linkify } from '@/lib/linkify';

const props = defineProps<{
    text: string;
}>();

const segments = computed(() => linkify(props.text));
</script>

<template>
    <template v-for="(segment, index) in segments" :key="index">
        <a
            v-if="segment.type === 'link'"
            :href="segment.href"
            target="_blank"
            rel="noopener noreferrer"
            class="text-primary hover:text-primary-hover rounded-sm [overflow-wrap:anywhere] underline"
            >{{ segment.text }}</a
        >
        <template v-else>{{ segment.text }}</template>
    </template>
</template>
