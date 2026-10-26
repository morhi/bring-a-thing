<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MenuIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
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
import { isCurrent } from './nav-items';
import type { NavItem } from './nav-items';
import { themes, useTheme } from './use-theme';
import type { Theme } from './use-theme';

withDefaults(
    defineProps<{
        items?: NavItem[];
        currentUrl: string;
    }>(),
    { items: () => [] },
);

const { t, locale, setLocale } = useI18n();
const { theme, setTheme } = useTheme();

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
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon-lg"
                class="rounded-full"
                :aria-label="t('shell.menu')"
            >
                <MenuIcon class="size-5" aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            :collision-padding="16"
            class="box-float w-[min(18rem,calc(100vw-32px))] rounded-xl p-1.5 shadow-lg ring-0"
        >
            <template v-if="items.length">
                <DropdownMenuItem
                    v-for="item in items"
                    :key="item.key"
                    as-child
                    class="min-h-11 gap-3 px-3 text-[15px]"
                >
                    <Link
                        :href="item.href"
                        :aria-current="
                            isCurrent(item.href, currentUrl)
                                ? 'page'
                                : undefined
                        "
                        class="text-fg aria-[current=page]:text-primary-soft-fg aria-[current=page]:bg-primary-soft no-underline"
                    >
                        <component
                            :is="item.icon"
                            class="text-fg-soft size-[18px]"
                            aria-hidden="true"
                        />
                        {{ t(item.key) }}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
            </template>
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
        </DropdownMenuContent>
    </DropdownMenu>
</template>
