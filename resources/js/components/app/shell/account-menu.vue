<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOutIcon } from '@lucide/vue';
import UserAvatar from '@/components/app/user-avatar.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/use-i18n';
import { logout } from '@/routes';
import type { NavItem } from './nav-items';

defineProps<{
    user: { name: string | null; email: string | null };
    items: NavItem[];
}>();

const { t } = useI18n();

function logOut(): void {
    router.post(logout().url);
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="hover:bg-surface-sunken aria-expanded:bg-surface-sunken focus-visible:outline-focus-ring inline-flex items-center rounded-full p-1"
            :aria-label="t('nav.menu')"
        >
            <UserAvatar
                :name="user.name"
                :email="user.email"
                aria-hidden="true"
            />
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="box-float w-60 rounded-xl p-1.5 shadow-lg ring-0"
        >
            <DropdownMenuLabel class="flex flex-col gap-px px-3 pt-2.5 pb-2">
                <span
                    v-if="user.name"
                    class="text-fg truncate text-[15px] font-semibold"
                    >{{ user.name }}</span
                >
                <span
                    v-if="user.email"
                    class="text-fg-soft truncate text-[13px] font-normal"
                    >{{ user.email }}</span
                >
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="item in items"
                :key="item.key"
                as-child
                class="min-h-10 justify-between px-3 text-[15px]"
            >
                <Link :href="item.href" class="text-fg no-underline">
                    {{ t(item.key) }}
                    <component
                        :is="item.icon"
                        class="text-fg-soft size-[18px]"
                        aria-hidden="true"
                    />
                </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                class="min-h-10 justify-between px-3 text-[15px]"
                @select="logOut"
            >
                {{ t('nav.logout') }}
                <LogOutIcon
                    class="text-fg-soft size-[18px]"
                    aria-hidden="true"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
