<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { dashboard } from '@/routes';
import { store as storeGroup } from '@/actions/App/Http/Controllers/GroupController';
import FormField from '@/components/app/form-field.vue';
import FormPage from '@/components/app/form-page.vue';
import InviteField from '@/components/app/invite-field.vue';
import type { InviteSelection } from '@/components/app/invite-field.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/use-i18n';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{ form: App.Data.GroupFormData }>();

const { t } = useI18n();

const invites = ref<InviteSelection[]>([]);
const groupForm = useForm({
    name: '',
    invites: [] as { email: string; name: string | null }[],
});

const inviteError = computed(() => {
    const key = Object.keys(groupForm.errors).find((name) =>
        name.startsWith('invites'),
    );

    return key ? (groupForm.errors as Record<string, string>)[key] : null;
});

function create(): void {
    groupForm
        .transform((data) => ({
            ...data,
            invites: invites.value.map((selection) => ({
                email: selection.email,
                name: selection.name ?? null,
            })),
        }))
        .post(storeGroup().url);
}

// The dashboard is the only page that opens "New group", so back and cancel return there
const cancelHref = dashboard().url;
</script>

<template>
    <Head :title="t('newGroup.title')" />

    <FormPage
        :title="t('newGroup.title')"
        :subtitle="t('newGroup.sub')"
        :back-href="cancelHref"
    >
        <form
            id="new-group-form"
            class="flex max-w-[560px] flex-col gap-(--space-stack)"
            novalidate
            data-feature="F-03"
            @submit.prevent="create"
        >
            <FormField
                :label="t('newGroup.name')"
                control="group-name"
                required
                :error="groupForm.errors.name"
            >
                <template #default="{ describedBy, invalid }">
                    <Input
                        id="group-name"
                        v-model="groupForm.name"
                        required
                        autocomplete="off"
                        :placeholder="t('newGroup.namePlaceholder')"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid || undefined"
                        class="bg-control border-control-border hover:border-fg-soft focus-visible:border-primary-ring placeholder:text-fg-soft h-(--control-height) max-w-(--field-max-width) rounded-(--control-radius) px-3.5 text-[15px]"
                        @input="groupForm.clearErrors('name')"
                    />
                </template>
            </FormField>
            <InviteField
                id="group-invite"
                v-model="invites"
                :label="t('newGroup.invites')"
                :hint="t('newGroup.invitesHint')"
                :error="inviteError"
                :friends="props.form.friends"
                :link-available="false"
                :disabled="groupForm.processing"
            />
        </form>
        <template #actions>
            <Button
                as-child
                variant="outline"
                size="lg"
                class="h-(--control-height) px-4"
            >
                <Link :href="cancelHref">{{ t('dialog.cancel') }}</Link>
            </Button>
            <Button
                type="submit"
                form="new-group-form"
                size="lg"
                class="h-(--control-height) px-4"
                :disabled="groupForm.processing"
            >
                {{ t('newGroup.create') }}
            </Button>
        </template>
    </FormPage>
</template>
