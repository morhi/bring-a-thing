<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/use-i18n';

const { locale, setLocale } = useI18n();

const modules = import.meta.glob<{ default: Component }>('./sections/*.vue', {
    eager: true,
});

const sections = Object.entries(modules)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([path, module]) => ({
        name: path.replace('./sections/', '').replace('.vue', ''),
        component: module.default,
    }));

const frames = [
    { label: 'Fluid', width: null },
    { label: '375', width: 375 },
    { label: '1280', width: 1280 },
] as const;

const frame = ref<number | null>(null);
const dark = ref(false);
const toc = ref<{ id: string; label: string }[]>([]);
const sectionsRoot = ref<HTMLElement | null>(null);

const frameStyle = computed(() =>
    frame.value === null ? {} : { width: `${frame.value}px`, maxWidth: '100%' },
);

function changeLocale(code: 'en' | 'de'): void {
    setLocale(code);
    document.documentElement.lang = code;
}

function toggleTheme(): void {
    dark.value = !dark.value;
    document.documentElement.classList.toggle('dark', dark.value);
}

onMounted(() => {
    document.documentElement.lang = locale.value;
    dark.value = document.documentElement.classList.contains('dark');
    toc.value = Array.from(
        sectionsRoot.value?.querySelectorAll<HTMLElement>('h2[id]') ?? [],
    ).map((heading) => ({
        id: heading.id,
        label: heading.textContent?.trim() ?? heading.id,
    }));
});
</script>

<template>
    <Head title="Components" />

    <div class="bg-background text-foreground min-h-screen">
        <div class="content-width space-y-6 py-8">
            <h1 class="font-display text-4xl">Components</h1>

            <div
                class="bg-card border-border flex flex-wrap items-center gap-3 rounded-2xl border p-3"
                role="toolbar"
                aria-label="Page tools"
            >
                <Button
                    type="button"
                    variant="outline"
                    :aria-pressed="dark"
                    @click="toggleTheme"
                >
                    {{ dark ? 'Dark' : 'Light' }}
                </Button>

                <div
                    class="flex items-center gap-1"
                    role="group"
                    aria-label="Language"
                >
                    <Button
                        type="button"
                        v-for="code in ['en', 'de'] as const"
                        :key="code"
                        :variant="locale === code ? 'default' : 'outline'"
                        :aria-pressed="locale === code"
                        @click="changeLocale(code)"
                    >
                        {{ code.toUpperCase() }}
                    </Button>
                </div>

                <div
                    class="flex items-center gap-1"
                    role="group"
                    aria-label="Viewport width"
                >
                    <Button
                        type="button"
                        v-for="option in frames"
                        :key="option.label"
                        :variant="
                            frame === option.width ? 'default' : 'outline'
                        "
                        :aria-pressed="frame === option.width"
                        @click="frame = option.width"
                    >
                        {{ option.label }}
                    </Button>
                </div>

                <p class="text-fg-muted text-sm">
                    The page is responsive; 375 and 1280 constrain the sections
                    wrapper only.
                </p>
            </div>

            <nav v-if="toc.length" aria-label="Sections">
                <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <li v-for="item in toc" :key="item.id">
                        <a
                            :href="`#${item.id}`"
                            class="text-primary underline-offset-4 hover:underline"
                            >{{ item.label }}</a
                        >
                    </li>
                </ul>
            </nav>

            <div
                ref="sectionsRoot"
                class="mx-auto space-y-12"
                :style="frameStyle"
            >
                <component
                    :is="section.component"
                    v-for="section in sections"
                    :key="section.name"
                />
            </div>
        </div>
    </div>
</template>
