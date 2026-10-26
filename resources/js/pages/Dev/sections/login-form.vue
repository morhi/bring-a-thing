<script setup lang="ts">
import LoginForm from '@/components/app/login-form.vue';

const states = [
    { label: 'Default', linkError: null },
    { label: 'Expired login link', linkError: 'expired' },
    { label: 'Invalid login link', linkError: 'invalid' },
] as const;
</script>

<template>
    <section class="flex flex-col gap-4">
        <h2 id="login-form" class="text-xl">LoginForm</h2>
        <p class="text-fg-muted">
            One form for login and sign-up (F-01). The email decides the next
            step: password, name or the link right away. Submitting sends real
            requests to the local backend.
        </p>
        <div
            v-for="mode in ['', 'dark']"
            :key="mode"
            :class="mode"
            class="bg-surface-raised text-fg flex flex-col gap-5 rounded-2xl p-5"
        >
            <p class="text-fg-muted text-sm font-semibold">
                {{ mode || 'light' }}
            </p>
            <div class="grid gap-6 lg:grid-cols-3">
                <div
                    v-for="(state, index) in states"
                    :key="state.label"
                    class="flex flex-col gap-2"
                >
                    <p class="text-fg-muted text-sm">{{ state.label }}</p>
                    <LoginForm
                        :expires-in-minutes="15"
                        :link-error="state.linkError"
                        :id-prefix="`dev-login-${mode || 'light'}-${index}`"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
