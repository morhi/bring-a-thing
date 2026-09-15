<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Password from 'primevue/password';
import Message from 'primevue/message';
import AppLayout from '@/layouts/AppLayout.vue';
import { updatePassword } from '@/actions/App/Http/Controllers/SettingsController';

defineOptions({ layout: AppLayout });

defineProps<{
    hasPassword: boolean;
}>();

const page = usePage();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.patch(updatePassword().url, {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Settings" />

    <div class="mx-auto flex max-w-md flex-col gap-6">
        <h1 class="text-surface-900 dark:text-surface-0 text-xl font-semibold">
            Account settings
        </h1>

        <Message v-if="page.props.flash.status" severity="success">
            {{ page.props.flash.status }}
        </Message>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">
                {{ hasPassword ? 'Change password' : 'Set a password' }}
            </h2>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div v-if="hasPassword" class="flex flex-col gap-2">
                    <label for="current_password" class="text-sm font-medium"
                        >Current password</label
                    >
                    <Password
                        id="current_password"
                        v-model="form.current_password"
                        autocomplete="current-password"
                        :feedback="false"
                        toggle-mask
                        :invalid="!!form.errors.current_password"
                    />
                    <small
                        v-if="form.errors.current_password"
                        class="text-red-500"
                    >
                        {{ form.errors.current_password }}
                    </small>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password" class="text-sm font-medium"
                        >New password</label
                    >
                    <Password
                        id="password"
                        v-model="form.password"
                        autocomplete="new-password"
                        toggle-mask
                        :invalid="!!form.errors.password"
                    />
                    <small v-if="form.errors.password" class="text-red-500">
                        {{ form.errors.password }}
                    </small>
                </div>

                <div class="flex flex-col gap-2">
                    <label
                        for="password_confirmation"
                        class="text-sm font-medium"
                        >Confirm password</label
                    >
                    <Password
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        autocomplete="new-password"
                        :feedback="false"
                        toggle-mask
                    />
                </div>

                <Button type="submit" label="Save" :loading="form.processing" />
            </form>
        </div>
    </div>
</template>
