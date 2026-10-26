<script setup lang="ts">
import FormPage from '@/components/app/form-page.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 class="text-xl">FormPage</h2>
        <p class="text-fg-muted">Page frame for create and edit pages. The page supplies the h1; here it is shown inside a bordered preview, and the sticky bar sticks to the bottom of the viewport.</p>
        <div v-for="mode in ['', 'dark']" :key="mode" :class="mode" class="flex flex-col gap-8 rounded-2xl bg-ground p-5 text-fg">
            <p class="text-sm font-semibold text-fg-muted">{{ mode || 'light' }} (create)</p>
            <FormPage title="New poll" subtitle="Propose dates and let the group answer yes, maybe or no.">
                <div class="flex flex-col gap-2">
                    <Label :for="`fp-title-${mode}`">Title</Label>
                    <Input :id="`fp-title-${mode}`" class="max-w-field" />
                </div>
                <template #actions>
                    <Button variant="outline">Cancel</Button>
                    <Button>Create poll</Button>
                </template>
            </FormPage>
            <p class="text-sm font-semibold text-fg-muted">{{ mode || 'light' }} (edit, long title, no actions slot)</p>
            <FormPage
                title="Edit the rehearsal weekend with the whole extended choir including guests and a very long name"
                back-label="Back to Choir"
                back-href="#"
            >
                <p class="text-fg-muted">Body only; the action bar is omitted when the slot is empty.</p>
            </FormPage>
        </div>
    </section>
</template>
