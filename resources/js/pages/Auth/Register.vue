<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import GuestLayout from '@/layouts/GuestLayout.vue';
import { store as registerUser } from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import { create as loginRoute } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';

defineOptions({ layout: GuestLayout });

const page = usePage();

const form = useForm({ email: '' });

function submit() {
    form.post(registerUser().url);
}
</script>

<template>
    <Head title="Register" />

    <div class="flex flex-col gap-6">
        <h1 class="text-surface-900 dark:text-surface-0 text-xl font-semibold">
            Create an account
        </h1>

        <Message v-if="page.props.flash.status" severity="success">
            {{ page.props.flash.status }}
        </Message>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium">Email</label>
                <InputText
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    autofocus
                    :invalid="!!form.errors.email"
                />
                <small v-if="form.errors.email" class="text-red-500">
                    {{ form.errors.email }}
                </small>
            </div>

            <Button
                type="submit"
                label="Send magic link"
                :loading="form.processing"
            />
        </form>

        <p class="text-surface-500 text-center text-sm">
            Already have an account?
            <Link :href="loginRoute().url" class="text-primary font-medium"
                >Log in</Link
            >
        </p>
    </div>
</template>
