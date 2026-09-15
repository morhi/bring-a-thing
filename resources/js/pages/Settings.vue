<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    updatePassword,
    updateProfile,
} from '@/actions/App/Http/Controllers/SettingsController';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    name: string | null;
    hasPassword: boolean;
}>();

const profileForm = useForm({
    name: props.name ?? '',
});

function submitProfile() {
    profileForm.patch(updateProfile().url);
}

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submitPassword() {
    passwordForm.patch(updatePassword().url, {
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <Head title="Settings" />

    <div class="flex flex-col gap-6">
        <h1 class="text-surface-900 dark:text-surface-0 text-xl font-semibold">
            Account settings
        </h1>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">Profile</h2>

            <form
                class="flex max-w-md flex-col gap-4"
                @submit.prevent="submitProfile"
            >
                <div class="flex flex-col gap-2">
                    <label for="name" class="text-sm font-medium">Name</label>
                    <InputText
                        id="name"
                        v-model="profileForm.name"
                        :invalid="!!profileForm.errors.name"
                    />
                    <small v-if="profileForm.errors.name" class="text-red-500">
                        {{ profileForm.errors.name }}
                    </small>
                </div>

                <Button
                    type="submit"
                    label="Save"
                    :loading="profileForm.processing"
                />
            </form>
        </div>

        <div class="dark:bg-surface-900 rounded-lg bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-lg font-medium">
                {{ hasPassword ? 'Change password' : 'Set a password' }}
            </h2>

            <form
                class="flex max-w-md flex-col gap-4"
                @submit.prevent="submitPassword"
            >
                <div v-if="hasPassword" class="flex flex-col gap-2">
                    <label for="current_password" class="text-sm font-medium"
                        >Current password</label
                    >
                    <Password
                        id="current_password"
                        v-model="passwordForm.current_password"
                        autocomplete="current-password"
                        :feedback="false"
                        toggle-mask
                        :invalid="!!passwordForm.errors.current_password"
                    />
                    <small
                        v-if="passwordForm.errors.current_password"
                        class="text-red-500"
                    >
                        {{ passwordForm.errors.current_password }}
                    </small>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password" class="text-sm font-medium"
                        >New password</label
                    >
                    <Password
                        id="password"
                        v-model="passwordForm.password"
                        autocomplete="new-password"
                        toggle-mask
                        :invalid="!!passwordForm.errors.password"
                    />
                    <small
                        v-if="passwordForm.errors.password"
                        class="text-red-500"
                    >
                        {{ passwordForm.errors.password }}
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
                        v-model="passwordForm.password_confirmation"
                        autocomplete="new-password"
                        :feedback="false"
                        toggle-mask
                    />
                </div>

                <Button
                    type="submit"
                    label="Save"
                    :loading="passwordForm.processing"
                />
            </form>
        </div>
    </div>
</template>
