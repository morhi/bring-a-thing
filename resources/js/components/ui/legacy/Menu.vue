<script setup lang="ts">
import { ref } from 'vue';

type MenuItem = { label: string; command?: () => void };

defineProps<{ model: MenuItem[]; popup?: boolean }>();

const open = ref(false);

function toggle() {
    open.value = !open.value;
}

function run(item: MenuItem) {
    open.value = false;
    item.command?.();
}

defineExpose({ toggle });
</script>

<template>
    <div v-if="open" class="box-float absolute right-6 z-50 mt-10 p-2">
        <button
            v-for="item in model"
            :key="item.label"
            type="button"
            class="block w-full rounded-md px-3 py-2 text-left hover:bg-accent"
            @click="run(item)"
        >
            {{ item.label }}
        </button>
    </div>
</template>
