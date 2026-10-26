<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import 'vue-sonner/style.css';
import FlashToasts from '@/components/FlashToasts.vue';
import AppHeader from '@/components/app/shell/app-header.vue';
import SiteFooter from '@/components/app/shell/site-footer.vue';
import SkipLink from '@/components/app/shell/skip-link.vue';
import { Toaster } from '@/components/ui/sonner';

const page = usePage();
</script>

<template>
    <div class="flex min-h-dvh flex-col">
        <SkipLink />
        <AppHeader
            variant="website"
            :user="page.props.auth.user"
            :current-url="page.url"
        />
        <main
            id="main"
            tabindex="-1"
            class="content-width flex-1 pt-8 pb-16 outline-none"
        >
            <slot />
        </main>
        <SiteFooter />
        <Toaster position="bottom-center" />
        <FlashToasts />
    </div>
</template>
