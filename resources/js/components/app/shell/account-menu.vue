<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOutIcon } from '@lucide/vue';
import UserAvatar from '@/components/app/user-avatar.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/use-i18n';
import type { Locale } from '@/lib/i18n';
import { logout } from '@/routes';
import type { NavItem } from './nav-items';
import { themes, useTheme } from './use-theme';
import type { Theme } from './use-theme';

defineProps<{
    user: { name: string | null; email: string | null };
    items: NavItem[];
}>();

const { t, locale, setLocale } = useI18n();
const { theme, setTheme } = useTheme();

// Language names stay in their own language, so everyone finds theirs
const languages: { code: Locale; label: string }[] = [
    { code: 'en', label: 'English' },
    { code: 'de', label: 'Deutsch' },
];

function selectLanguage(value: unknown): void {
    if (value !== 'en' && value !== 'de') {
        return;
    }

    setLocale(value);
}

function selectTheme(value: unknown): void {
    if (typeof value !== 'string' || !themes.includes(value as Theme)) {
        return;
    }

    setTheme(value as Theme);
}

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
            :collision-padding="16"
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
            <DropdownMenuLabel
                class="text-fg-muted px-3 text-xs font-semibold"
                >{{ t('welcome.language') }}</DropdownMenuLabel
            >
            <DropdownMenuRadioGroup
                :model-value="locale"
                @update:model-value="selectLanguage"
            >
                <DropdownMenuRadioItem
                    v-for="language in languages"
                    :key="language.code"
                    :value="language.code"
                    :lang="language.code"
                    class="min-h-10 px-3 text-[15px]"
                >
                    {{ language.label }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
            <DropdownMenuSeparator />
            <DropdownMenuLabel
                class="text-fg-muted px-3 text-xs font-semibold"
                >{{ t('shell.theme') }}</DropdownMenuLabel
            >
            <DropdownMenuRadioGroup
                :model-value="theme"
                @update:model-value="selectTheme"
            >
                <DropdownMenuRadioItem
                    v-for="option in themes"
                    :key="option"
                    :value="option"
                    class="min-h-10 px-3 text-[15px]"
                >
                    {{ t(`shell.theme.${option}`) }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
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
