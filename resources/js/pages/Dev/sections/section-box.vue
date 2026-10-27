<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import EmptyState from '@/components/app/empty-state.vue';
import SectionBox from '@/components/app/section-box.vue';
import SectionBoxItem from '@/components/app/section-box-item.vue';
import { Button } from '@/components/ui/button';
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="section-box" class="text-xl">SectionBox</h2>
        <p class="text-fg-muted">
            One raised container per page section with a divided heading row.
            SectionBoxItem is the tinted level 2 box for rows inside it; as a
            link or button it lifts on hover. Also shows the text-h1 and text-h2
            heading scale.
        </p>
        <div
            v-for="mode in ['', 'dark']"
            :key="mode"
            :class="mode"
            class="bg-ground text-fg flex flex-col gap-8 rounded-2xl p-5"
        >
            <p class="text-fg-muted text-sm font-semibold">
                {{ mode || 'light' }}
            </p>
            <div class="flex flex-col gap-2">
                <p class="text-h1">Page title (text-h1)</p>
                <p class="text-h2">Section title (text-h2)</p>
            </div>

            <SectionBox :id="`sb-plain-${mode}`" title="Title only">
                <p class="text-fg-muted">
                    Default slot with plain text content.
                </p>
            </SectionBox>

            <SectionBox :id="`sb-groups-${mode}`" title="Groups" :count="3">
                <template #action>
                    <Button type="button" variant="outline" size="sm">
                        <Plus aria-hidden="true" />
                        New group
                    </Button>
                </template>
                <div
                    class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,260px),1fr))] gap-4"
                >
                    <SectionBoxItem as="button" class="p-5">
                        <span class="font-display text-lg font-[640]"
                            >Choir</span
                        >
                        <span class="text-fg-muted block text-sm"
                            >Button item, 12 members</span
                        >
                    </SectionBoxItem>
                    <SectionBoxItem :as="Link" href="#section-box" class="p-5">
                        <span class="font-display text-lg font-[640]"
                            >Climbing</span
                        >
                        <span class="text-fg-muted block text-sm"
                            >Link item, 5 members</span
                        >
                    </SectionBoxItem>
                    <SectionBoxItem as="a" href="#section-box" class="p-5">
                        <span class="font-display text-lg font-[640]"
                            >A very long group name that wraps on a narrow
                            screen</span
                        >
                        <span class="text-fg-muted block text-sm"
                            >Anchor item</span
                        >
                    </SectionBoxItem>
                </div>
            </SectionBox>

            <SectionBox :id="`sb-rows-${mode}`" title="Static rows" :count="2">
                <ul class="flex flex-col gap-3">
                    <SectionBoxItem as="li">
                        List item row, not interactive
                    </SectionBoxItem>
                    <SectionBoxItem as="li">
                        Second row with
                        <a href="#section-box">an inline link</a>
                    </SectionBoxItem>
                </ul>
            </SectionBox>

            <SectionBox :id="`sb-empty-${mode}`" title="Lists" :count="0">
                <template #action>
                    <Button type="button" variant="outline" size="sm">
                        <Plus aria-hidden="true" />
                        New list
                    </Button>
                </template>
                <EmptyState
                    title="No lists yet"
                    text="Count zero hides the number; the empty state fills the box."
                />
            </SectionBox>

            <SectionBox
                :id="`sb-long-${mode}`"
                title="A very long section title that keeps going to check how the heading row wraps next to its action"
                :count="128"
            >
                <template #action>
                    <Button type="button" size="sm">Action</Button>
                </template>
                <div class="flex flex-col gap-3">
                    <SectionBoxItem>Div item (default)</SectionBoxItem>
                </div>
            </SectionBox>
        </div>
    </section>
</template>
