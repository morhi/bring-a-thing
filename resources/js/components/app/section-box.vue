<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useI18n } from '@/composables/use-i18n';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title: string;
        // Heading id; the section is labelled by it
        id: string;
        count?: number | null;
        // Adds an icon-only toggle left of the title that hides the content
        collapsible?: boolean;
        // Initial state of a collapsible section
        defaultOpen?: boolean;
        // Mobile only: the action slot drops below the title as a full-width column
        stackActions?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        count: null,
        class: undefined,
        defaultOpen: true,
        stackActions: false,
    },
);

const { t } = useI18n();

// Set on every visit; the state lives only as long as the page
const open = ref(props.defaultOpen);

// A live reload that fills or empties the section moves it to its new default
watch(
    () => props.defaultOpen,
    (defaultOpen) => {
        open.value = defaultOpen;
    },
);
const collapsed = computed(() => !!props.collapsible && !open.value);
</script>

<template>
    <Collapsible v-model:open="open" as-child :disabled="!collapsible">
        <section
            :aria-labelledby="id"
            :class="
                cn(
                    'box @container flex min-w-0 flex-col gap-[18px] p-3.5 shadow-(--box-shadow) sm:px-[26px] sm:pt-6 sm:pb-[26px]',
                    // Collapsed: only the header row remains, without divider, gap or extra bottom padding
                    collapsed && 'gap-0 sm:pb-6',
                    props.class,
                )
            "
        >
            <div
                :class="[
                    'border-border flex flex-wrap justify-between gap-4',
                    collapsed ? '' : 'border-b pb-3.5',
                    collapsible ? 'items-center' : 'items-baseline',
                ]"
            >
                <div
                    :class="[
                        'flex min-w-0 items-center gap-2',
                        // Grows from its content width, so meta wraps below instead of overlapping the title
                        $slots.meta && 'flex-auto',
                    ]"
                >
                    <CollapsibleTrigger v-if="collapsible" as-child>
                        <!-- The button base drops the outline, so the trigger restores the app's focus ring -->
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="t('common.toggleSection', { title })"
                            class="focus-visible:outline-focus-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-solid"
                        >
                            <ChevronDown
                                :class="[
                                    'transition-transform motion-reduce:transition-none',
                                    open ? '' : '-rotate-90',
                                ]"
                                aria-hidden="true"
                            />
                        </Button>
                    </CollapsibleTrigger>
                    <h2 :id="id" class="text-h2 min-w-0 break-words">
                        {{ title
                        }}<span
                            v-if="count"
                            class="text-fg-soft ml-1.5 text-[15px] font-medium tabular-nums"
                            >{{ count }}</span
                        >
                    </h2>
                </div>
                <div
                    v-if="$slots.meta"
                    class="flex min-w-0 items-center gap-2.5 sm:-mr-1.5"
                >
                    <slot name="meta" />
                </div>
                <div
                    v-if="$slots.action"
                    :class="[
                        'flex flex-wrap gap-2.5',
                        stackActions && 'max-sm:w-full max-sm:flex-col',
                    ]"
                >
                    <slot name="action" />
                </div>
            </div>
            <CollapsibleContent v-if="collapsible">
                <slot />
            </CollapsibleContent>
            <slot v-else />
        </section>
    </Collapsible>
</template>
