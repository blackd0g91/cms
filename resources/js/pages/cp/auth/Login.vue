<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AuthCard from '@/components/cp/AuthCard.vue';
import { store } from '@/routes/cp/login';
</script>

<template>
    <Head title="Log in" />
    <AuthCard subtitle="Log in to the control panel">
        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="cp-card space-y-4 p-6"
        >
            <div class="space-y-1.5">
                <label for="email" class="cp-label">Email</label>
                <input
                    id="email"
                    v-focus
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    class="cp-input"
                />
                <p v-if="errors.email" class="cp-error">{{ errors.email }}</p>
            </div>

            <div class="space-y-1.5">
                <label for="password" class="cp-label">Password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="cp-input"
                />
                <p v-if="errors.password" class="cp-error">
                    {{ errors.password }}
                </p>
            </div>

            <label
                class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300"
            >
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    class="rounded border-neutral-300 dark:border-neutral-700"
                />
                Remember me
            </label>

            <button
                type="submit"
                :disabled="processing"
                class="cp-btn-primary w-full"
            >
                Log in
            </button>
        </Form>
    </AuthCard>
</template>
