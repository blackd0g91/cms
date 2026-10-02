<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
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

const inputClass =
    'block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200 focus:outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:focus:ring-neutral-800';
const labelClass =
    'block text-sm font-medium text-neutral-700 dark:text-neutral-300';
</script>

<template>
    <Head :title="invited ? 'Welcome' : 'Choose a password'" />
    <div
        class="flex min-h-screen items-center justify-center bg-neutral-50 px-4 dark:bg-neutral-950"
    >
        <div class="w-full max-w-sm">
            <h1
                class="mb-6 text-center text-xl font-semibold text-neutral-900 dark:text-neutral-100"
            >
                {{ $page.props.name }}
            </h1>

            <div
                v-if="!valid"
                class="space-y-3 rounded-lg border border-neutral-200 bg-white p-6 text-sm text-neutral-700 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300"
            >
                <p class="font-medium text-neutral-900 dark:text-neutral-100">
                    This link doesn’t work anymore
                </p>
                <p>
                    It has expired or was already used. Ask an admin for a new
                    one.
                </p>
                <Link :href="login()" class="inline-block underline">
                    Log in
                </Link>
            </div>

            <Form
                v-else
                v-bind="update.form()"
                :reset-on-error="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
            >
                <div class="text-sm text-neutral-700 dark:text-neutral-300">
                    <p
                        class="font-medium text-neutral-900 dark:text-neutral-100"
                    >
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
                    <label for="password" :class="labelClass">Password</label>
                    <input
                        id="password"
                        v-focus
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        :class="inputClass"
                    />
                    <p
                        v-if="errors.password"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
                        {{ errors.password }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label for="password_confirmation" :class="labelClass">
                        Confirm password
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        :class="inputClass"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="processing"
                    class="w-full rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 disabled:opacity-50 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-300"
                >
                    {{
                        invited
                            ? 'Choose password and log in'
                            : 'Save and log in'
                    }}
                </button>
            </Form>
        </div>
    </div>
</template>
