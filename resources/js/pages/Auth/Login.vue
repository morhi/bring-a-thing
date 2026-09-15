<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {
    needsName,
    store as sendMagicLink,
} from '@/actions/App/Http/Controllers/Auth/MagicLinkController';
import { store as loginWithPassword } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { create as registerRoute } from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';

defineOptions({ layout: GuestLayout });

const magicLinkForm = useForm({ name: '', email: '' });
const passwordForm = useForm({ email: '', password: '' });

const usePasswordLogin = ref(false);
const emailNeedsName = ref(false);

const isValidEmail = (email: string) =>
    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

let lookupTimeout: ReturnType<typeof setTimeout>;

watch(
    () => magicLinkForm.email,
    (email) => {
        clearTimeout(lookupTimeout);

        if (!isValidEmail(email)) {
            emailNeedsName.value = false;
            return;
        }

        lookupTimeout = setTimeout(async () => {
            const response = await fetch(needsName({ query: { email } }).url);
            const data = await response.json();
            emailNeedsName.value = data.needsName;
        }, 400);
    },
);

function requestMagicLink() {
    magicLinkForm.post(sendMagicLink().url);
}

function loginWithPasswordSubmit() {
    passwordForm.post(loginWithPassword().url, {
        onError: () => passwordForm.reset('password'),
    });
}
</script>

<template>
    <Head title="Log in" />

    <div class="flex flex-col gap-6">
        <h1 class="text-surface-900 dark:text-surface-0 text-xl font-semibold">
            Log in
        </h1>

        <form
            v-if="!usePasswordLogin"
            class="flex flex-col gap-4"
            @submit.prevent="requestMagicLink"
        >
            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium">Email</label>
                <InputText
                    id="email"
                    v-model="magicLinkForm.email"
                    type="email"
                    autocomplete="email"
                    autofocus
                    :invalid="!!magicLinkForm.errors.email"
                />
                <small v-if="magicLinkForm.errors.email" class="text-red-500">
                    {{ magicLinkForm.errors.email }}
                </small>
            </div>

            <div
                v-if="emailNeedsName || magicLinkForm.errors.name"
                class="flex flex-col gap-2"
            >
                <label for="name" class="text-sm font-medium">Name</label>
                <InputText
                    id="name"
                    v-model="magicLinkForm.name"
                    autocomplete="name"
                    :invalid="!!magicLinkForm.errors.name"
                />
                <small v-if="magicLinkForm.errors.name" class="text-red-500">
                    {{ magicLinkForm.errors.name }}
                </small>
            </div>

            <Button
                type="submit"
                label="Send magic link"
                :loading="magicLinkForm.processing"
            />

            <Button
                type="button"
                label="Log in with a password instead"
                severity="secondary"
                text
                @click="usePasswordLogin = true"
            />
        </form>

        <form
            v-else
            class="flex flex-col gap-4"
            @submit.prevent="loginWithPasswordSubmit"
        >
            <div class="flex flex-col gap-2">
                <label for="password-email" class="text-sm font-medium"
                    >Email</label
                >
                <InputText
                    id="password-email"
                    v-model="passwordForm.email"
                    type="email"
                    autocomplete="email"
                    autofocus
                    :invalid="!!passwordForm.errors.email"
                />
                <small v-if="passwordForm.errors.email" class="text-red-500">
                    {{ passwordForm.errors.email }}
                </small>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium"
                    >Password</label
                >
                <Password
                    id="password"
                    v-model="passwordForm.password"
                    autocomplete="current-password"
                    :feedback="false"
                    toggle-mask
                    :invalid="!!passwordForm.errors.password"
                />
                <small v-if="passwordForm.errors.password" class="text-red-500">
                    {{ passwordForm.errors.password }}
                </small>
            </div>

            <Button
                type="submit"
                label="Log in"
                :loading="passwordForm.processing"
            />

            <Button
                type="button"
                label="Use a magic link instead"
                severity="secondary"
                text
                @click="usePasswordLogin = false"
            />
        </form>

        <p class="text-surface-500 text-center text-sm">
            No account yet?
            <Link :href="registerRoute().url" class="text-primary font-medium"
                >Register</Link
            >
        </p>
    </div>
</template>
