<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { useI18n } from '@/composables/use-i18n';

defineProps<{
    title: string;
    subtitle?: string;
    // Parent page to return to; without it the back control is a button that emits "back"
    backHref?: string;
    // Defaults to the plain "Back"; edit pages name the parent ("Back to Choir")
    backLabel?: string;
}>();

defineEmits<{ back: [] }>();

const { t } = useI18n();

const backClasses =
    '-my-0.5 inline-flex min-h-6 w-fit items-center gap-1.5 rounded-sm text-sm font-semibold text-fg-muted hover:text-fg';
</script>

<template>
    <div class="flex flex-col gap-8">
        <header class="flex flex-col gap-2.5">
            <Link v-if="backHref" :href="backHref" :class="backClasses">
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ backLabel ?? t('nav.back') }}
            </Link>
            <button
                v-else
                type="button"
                :class="backClasses"
                @click="$emit('back')"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {{ backLabel ?? t('nav.back') }}
            </button>
            <h1 class="text-h1">{{ title }}</h1>
            <p v-if="subtitle" class="text-fg-muted max-w-[60ch]">
                {{ subtitle }}
            </p>
        </header>
        <div
            class="box shadow-card flex flex-col gap-4 rounded-3xl px-4 pt-[18px] pb-5 sm:px-8 sm:pt-7 sm:pb-8"
        >
            <slot />
        </div>
        <div
            v-if="$slots.actions"
            class="border-border bg-surface-raised/90 sticky bottom-[calc(12px+env(safe-area-inset-bottom,0px))] -mt-2 mb-6 flex flex-wrap justify-end gap-2.5 rounded-md border px-3.5 py-3 shadow-md backdrop-blur-sm"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
