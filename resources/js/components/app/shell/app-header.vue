<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';
import { dashboard, login, welcome } from '@/routes';
import AccountMenu from './account-menu.vue';
import LanguageSwitch from './language-switch.vue';
import MainNav from './main-nav.vue';
import MobileMenu from './mobile-menu.vue';
import { navItems } from './nav-items';
import NotificationsBell from './notifications-bell.vue';
import ThemeToggle from './theme-toggle.vue';
import Wordmark from './wordmark.vue';

const props = withDefaults(
    defineProps<{
        // website: guest pages (start, join, shared list) without nav and account menu
        variant?: 'app' | 'website';
        user?: { name: string | null; email: string | null } | null;
        currentUrl?: string;
        // null while notifications are not shared yet: the bell stays hidden
        unread?: number | null;
        notificationsOpen?: boolean;
    }>(),
    {
        variant: 'app',
        user: null,
        currentUrl: '',
        unread: null,
        notificationsOpen: false,
    },
);

defineEmits<{ toggleNotifications: [] }>();

const { t } = useI18n();

const isApp = computed(() => props.variant === 'app' && props.user !== null);
const items = computed(() => (isApp.value ? navItems() : []));
const menuItems = computed(() =>
    items.value.filter((item) => item.key !== 'nav.dashboard'),
);
const homeHref = computed(() => (props.user ? dashboard().url : welcome().url));
</script>

<template>
    <header
        class="border-border bg-surface-raised/90 shadow-header sticky top-[env(safe-area-inset-top,0px)] z-20 border-b backdrop-blur-[10px]"
    >
        <div
            :class="[
                'content-width flex items-center gap-2 sm:gap-4',
                variant === 'website' ? 'min-h-[72px]' : 'min-h-16',
            ]"
        >
            <Wordmark :href="homeHref" class="shrink-0" />

            <!-- Wordmark plus nav would crowd 375 px; nav, language and theme move into the menu below lg -->
            <MainNav
                v-if="isApp"
                :items="items"
                :current-url="currentUrl"
                class="ml-4 hidden lg:block"
            />

            <span class="flex-1" />

            <div class="flex items-center gap-1.5">
                <div class="hidden items-center gap-1.5 lg:flex">
                    <LanguageSwitch />
                    <ThemeToggle />
                </div>
                <NotificationsBell
                    v-if="isApp && unread !== null"
                    :unread="unread"
                    :expanded="notificationsOpen"
                    @toggle="$emit('toggleNotifications')"
                />
                <AccountMenu
                    v-if="isApp && user"
                    :user="user"
                    :items="menuItems"
                />
                <template v-if="!isApp">
                    <Button
                        v-if="user"
                        as-child
                        size="lg"
                        class="rounded-full px-4"
                    >
                        <Link
                            :href="dashboard().url"
                            class="text-primary-fg hover:text-primary-fg no-underline"
                            >{{ t('nav.dashboard') }}</Link
                        >
                    </Button>
                    <Button v-else as-child size="lg" class="rounded-full px-4">
                        <Link
                            :href="login().url"
                            class="text-primary-fg hover:text-primary-fg no-underline"
                            >{{ t('shell.login') }}</Link
                        >
                    </Button>
                </template>
                <div class="lg:hidden">
                    <MobileMenu :items="items" :current-url="currentUrl" />
                </div>
            </div>
        </div>
    </header>
</template>
