<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AuthCard from '@/components/cp/AuthCard.vue';
import { login } from '@/routes/cp';
import { update } from '@/routes/cp/password';

// From a link made on the users page: an invitation, or a new password.
defineProps<{
    valid: boolean;
    token: string;
    // Who the link is for, when it still works.
    user: { name: string; email: string } | null;
    // Has not chosen a password before.
    invited: boolean;
}>();
</script>

<template>
    <Head :title="invited ? 'Welcome' : 'Choose a password'" />
    <AuthCard>
        <div
            v-if="!valid"
            class="cp-card space-y-3 p-6 text-sm text-neutral-700 dark:text-neutral-300"
        >
            <p class="font-medium text-neutral-900 dark:text-neutral-100">
                This link doesn’t work anymore
            </p>
            <p>
                It has expired or was already used. Ask an admin for a new one.
            </p>
            <Link
                :href="login()"
                class="inline-block text-brand hover:underline"
            >
                Log in
            </Link>
        </div>

        <Form
            v-else
            v-bind="update.form()"
            :reset-on-error="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="cp-card space-y-4 p-6"
        >
            <div class="text-sm text-neutral-700 dark:text-neutral-300">
                <p class="font-medium text-neutral-900 dark:text-neutral-100">
                    {{
                        invited
                            ? `Welcome, ${user?.name}`
                            : 'Choose a new password'
                    }}
                </p>
                <p class="mt-1">
                    {{
                        invited
                            ? 'Choose a password to log in with'
                            : 'For logging in with'
                    }}
                    <span class="font-medium">{{ user?.email }}</span
                    >.
                </p>
            </div>

            <input type="hidden" name="token" :value="token" />
            <input type="hidden" name="email" :value="user?.email" />
            <!-- For password managers, which save it with the password. -->
            <input
                type="text"
                name="username"
                :value="user?.email"
                autocomplete="username"
                hidden
                readonly
            />

            <div class="space-y-1.5">
                <label for="password" class="cp-label">Password</label>
                <input
                    id="password"
                    v-focus
                    type="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="cp-input"
                />
                <p v-if="errors.password" class="cp-error">
                    {{ errors.password }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="password_confirmation" class="cp-label">
                    Confirm password
                </label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="cp-input"
                />
            </div>

            <button
                type="submit"
                :disabled="processing"
                class="cp-btn-primary w-full"
            >
                {{ invited ? 'Choose password and log in' : 'Save and log in' }}
            </button>
        </Form>
    </AuthCard>
</template>
