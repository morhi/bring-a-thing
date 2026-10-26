<script setup lang="ts">
import { MonitorIcon, MoonIcon, SunIcon } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/use-i18n';
import { themes, useTheme } from './use-theme';
import type { Theme } from './use-theme';

const { t } = useI18n();
const { theme, setTheme } = useTheme();

const icons = { system: MonitorIcon, light: SunIcon, dark: MoonIcon };

const currentIcon = computed(() => icons[theme.value]);

function select(value: unknown): void {
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
                :aria-label="`${t('shell.theme')}: ${t(`shell.theme.${theme}`)}`"
            >
                <component
                    :is="currentIcon"
                    class="size-5"
                    aria-hidden="true"
                />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="box-float w-48 rounded-xl p-1.5 shadow-lg ring-0"
        >
            <DropdownMenuLabel>{{ t('shell.theme') }}</DropdownMenuLabel>
            <DropdownMenuRadioGroup
                :model-value="theme"
                @update:model-value="select"
            >
                <DropdownMenuRadioItem
                    v-for="option in themes"
                    :key="option"
                    :value="option"
                    class="min-h-9 px-2.5"
                >
                    <component
                        :is="icons[option]"
                        class="text-fg-soft"
                        aria-hidden="true"
                    />
                    {{ t(`shell.theme.${option}`) }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
