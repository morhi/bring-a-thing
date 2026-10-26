<script lang="ts">
// Module scope: when a visit swaps the layout, both layouts' instances see the same flash object once
const shownFlashes = new WeakSet<object>();
</script>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage();

// Each response brings a new flash object, so the same message flashed twice in a row still shows twice
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash || shownFlashes.has(flash)) {
            return;
        }

        shownFlashes.add(flash);

        if (flash.success) {
            toast.success(flash.success, { duration: 4000 });
        }

        if (flash.error) {
            toast.error(flash.error, { duration: 6000 });
        }
    },
    { immediate: true },
);
</script>

<template>
    <!-- renders no markup; only triggers toasts -->
</template>
