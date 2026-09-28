<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '@/components/cp/PageHeader.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { password, update } from '@/routes/cp/account';

defineOptions({ layout: CpLayout });

const page = usePage();

const profile = useForm({
    name: page.props.auth.user.name,
    email: page.props.auth.user.email,
});

const passwords = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const saveProfile = () => {
    profile.submit(update(), {
        preserveScroll: true,
        onSuccess: () => profile.defaults(),
    });
};

const savePassword = () => {
    passwords.submit(password(), {
        preserveScroll: true,
        onFinish: () =>
            passwords.reset(
                'current_password',
                'password',
                'password_confirmation',
            ),
    });
};
</script>

<template>
    <Head title="Account" />
    <PageHeader title="Account" />

    <div class="max-w-xl space-y-6">
        <form class="cp-card space-y-4 p-6" @submit.prevent="saveProfile">
            <h2 class="font-semibold">Profile</h2>

            <div class="space-y-1.5">
                <label for="name" class="cp-label">Name</label>
                <input
                    id="name"
                    v-model="profile.name"
                    type="text"
                    autocomplete="name"
                    class="cp-input"
                />
                <p v-if="profile.errors.name" class="cp-error">
                    {{ profile.errors.name }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="email" class="cp-label">Email</label>
                <input
                    id="email"
                    v-model="profile.email"
                    type="email"
                    autocomplete="email"
                    class="cp-input"
                />
                <p class="text-xs text-neutral-500">Used to log in.</p>
                <p v-if="profile.errors.email" class="cp-error">
                    {{ profile.errors.email }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="cp-btn-primary"
                    :disabled="profile.processing || !profile.isDirty"
                >
                    Save profile
                </button>
                <span
                    v-if="profile.recentlySuccessful"
                    class="text-sm text-neutral-500"
                >
                    Saved.
                </span>
            </div>
        </form>

        <form class="cp-card space-y-4 p-6" @submit.prevent="savePassword">
            <h2 class="font-semibold">Password</h2>

            <div class="space-y-1.5">
                <label for="current_password" class="cp-label">
                    Current password
                </label>
                <input
                    id="current_password"
                    v-model="passwords.current_password"
                    type="password"
                    autocomplete="current-password"
                    class="cp-input"
                />
                <p v-if="passwords.errors.current_password" class="cp-error">
                    {{ passwords.errors.current_password }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="password" class="cp-label">New password</label>
                <input
                    id="password"
                    v-model="passwords.password"
                    type="password"
                    autocomplete="new-password"
                    class="cp-input"
                />
                <p v-if="passwords.errors.password" class="cp-error">
                    {{ passwords.errors.password }}
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="password_confirmation" class="cp-label">
                    Confirm new password
                </label>
                <input
                    id="password_confirmation"
                    v-model="passwords.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="cp-input"
                />
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="cp-btn-primary"
                    :disabled="passwords.processing"
                >
                    Change password
                </button>
                <span
                    v-if="passwords.recentlySuccessful"
                    class="text-sm text-neutral-500"
                >
                    Password changed.
                </span>
            </div>
        </form>
    </div>
</template>
