<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { store } from '@/routes/cp/login';
</script>

<template>
    <Head title="Log in" />
    <div
        class="flex min-h-screen items-center justify-center bg-neutral-50 px-4 dark:bg-neutral-950"
    >
        <div class="w-full max-w-sm">
            <h1
                class="mb-6 text-center text-xl font-semibold text-neutral-900 dark:text-neutral-100"
            >
                {{ $page.props.name }}
            </h1>

            <Form
                v-bind="store.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="space-y-4 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
            >
                <div class="space-y-1.5">
                    <label
                        for="email"
                        class="block text-sm font-medium text-neutral-700 dark:text-neutral-300"
                    >
                        Email
                    </label>
                    <input
                        id="email"
                        v-focus
                        type="email"
                        name="email"
                        required
                        autocomplete="email"
                        class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200 focus:outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:focus:ring-neutral-800"
                    />
                    <p
                        v-if="errors.email"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
                        {{ errors.email }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="password"
                        class="block text-sm font-medium text-neutral-700 dark:text-neutral-300"
                    >
                        Password
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-neutral-500 focus:ring-2 focus:ring-neutral-200 focus:outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100 dark:focus:ring-neutral-800"
                    />
                    <p
                        v-if="errors.password"
                        class="text-sm text-red-600 dark:text-red-400"
                    >
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
                    class="w-full rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700 disabled:opacity-50 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-300"
                >
                    Log in
                </button>
            </Form>
        </div>
    </div>
</template>
