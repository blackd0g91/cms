<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import CpLayout from '@/layouts/CpLayout.vue';
import { edit as accountEdit } from '@/routes/cp/account';
import { destroy, link, store, update } from '@/routes/cp/users';
import type { PasswordLink, UserRole } from '@/types';

defineOptions({ layout: CpLayout });

type Row = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    posts_count: number;
    // Has not chosen a password yet.
    invited: boolean;
    // When their unused link stops working, if they have one.
    link_expires_at: string | null;
    is_me: boolean;
};

defineProps<{
    users: Row[];
    roles: { value: UserRole; label: string }[];
}>();

const page = usePage();

// The link just made, shown until dismissed. It is flash data, which is not
// kept in the browser's history, so it is gone once you leave the page.
const shownLink = ref<PasswordLink | null>(null);
const copied = ref(false);

watch(
    () => page.flash.link,
    (value) => {
        if (value) {
            shownLink.value = value;
            copied.value = false;
        }
    },
    { immediate: true },
);

const copyLink = async () => {
    if (!shownLink.value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(shownLink.value.url);
        copied.value = true;
    } catch {
        // Selecting it on focus still lets it be copied by hand.
    }
};

const invite = useForm({
    name: '',
    email: '',
    role: 'editor' as UserRole,
});

const submitInvite = () =>
    invite.submit(store(), {
        preserveScroll: true,
        onSuccess: () => invite.reset(),
    });

const changeRole = (user: Row, role: string) =>
    router.put(update(user.id).url, { role }, { preserveScroll: true });

const newLink = (user: Row) =>
    router.post(link(user.id).url, {}, { preserveScroll: true });

const posts = (count: number) => (count === 1 ? '1 post' : `${count} posts`);

const remove = (user: Row) => {
    const message = user.invited
        ? `Cancel the invitation for ${user.name}? Their link stops working.`
        : `Remove ${user.name}? They can no longer log in.` +
          (user.posts_count
              ? ` Their ${posts(user.posts_count)} stay on the site, without an author.`
              : '');

    if (confirm(message)) {
        router.delete(destroy(user.id).url, { preserveScroll: true });
    }
};

const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <Head title="Users" />
    <PageHeader title="Users" />

    <p class="mb-6 max-w-3xl text-sm text-neutral-500">
        Editors write and publish posts, and look after tags and media. Admins
        can also change templates, links and settings, and manage users.
    </p>

    <section
        v-if="shownLink"
        class="mb-6 max-w-3xl rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-950 dark:border-green-900 dark:bg-green-950 dark:text-green-100"
        role="status"
    >
        <p class="font-medium">
            {{
                shownLink.invited
                    ? `Send this link to ${shownLink.name} to invite them.`
                    : `Send this link to ${shownLink.name} to choose a new password.`
            }}
        </p>
        <p class="mt-0.5 text-xs">
            It works once, until {{ formatDate(shownLink.expires_at) }}. Whoever
            opens it can choose the password, so send it only to them. It isn’t
            shown again, but you can make a new one.
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
            <input
                :value="shownLink.url"
                readonly
                aria-label="Link"
                class="cp-input min-w-0 flex-1 font-mono text-xs"
                @focus="($event.target as HTMLInputElement).select()"
            />
            <button type="button" class="cp-btn" @click="copyLink">
                {{ copied ? 'Copied' : 'Copy' }}
            </button>
            <button type="button" class="cp-btn" @click="shownLink = null">
                Done
            </button>
        </div>
    </section>

    <ul
        class="cp-card max-w-3xl divide-y divide-neutral-200 dark:divide-neutral-800"
    >
        <li
            v-for="user in users"
            :key="user.id"
            class="flex flex-wrap items-center gap-3 p-4"
        >
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">
                    {{ user.name }}
                    <span
                        v-if="user.is_me"
                        class="ml-1 rounded bg-neutral-100 px-1.5 py-0.5 text-xs font-normal text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400"
                        >You</span
                    >
                </p>
                <p class="truncate text-xs text-neutral-500">
                    {{ user.email }}
                </p>
                <p class="mt-0.5 text-xs text-neutral-500">
                    <template v-if="user.invited">
                        <span class="text-amber-700 dark:text-amber-400"
                            >Invited</span
                        >
                        &middot;
                        {{
                            user.link_expires_at
                                ? `link works until ${formatDate(user.link_expires_at)}`
                                : 'the link has expired'
                        }}
                    </template>
                    <template v-else>
                        {{ posts(user.posts_count) }}
                        <template v-if="user.link_expires_at">
                            &middot; password link works until
                            {{ formatDate(user.link_expires_at) }}
                        </template>
                    </template>
                </p>
            </div>

            <select
                :value="user.role"
                :disabled="user.is_me"
                :title="
                    user.is_me ? 'You can’t change your own role' : undefined
                "
                :aria-label="`Role of ${user.name}`"
                class="cp-input w-auto"
                @change="
                    changeRole(user, ($event.target as HTMLSelectElement).value)
                "
            >
                <option
                    v-for="role in roles"
                    :key="role.value"
                    :value="role.value"
                >
                    {{ role.label }}
                </option>
            </select>

            <template v-if="!user.is_me">
                <button
                    type="button"
                    class="cp-btn"
                    :title="
                        user.invited
                            ? 'A new link to send. The last one stops working.'
                            : 'A link to choose a new password, for when they forgot theirs. Their current one keeps working until it is used.'
                    "
                    @click="newLink(user)"
                >
                    {{ user.invited ? 'New invite link' : 'Password link' }}
                </button>
                <button
                    type="button"
                    class="cp-btn-danger"
                    @click="remove(user)"
                >
                    {{ user.invited ? 'Cancel invite' : 'Remove' }}
                </button>
            </template>
            <Link v-else :href="accountEdit()" class="cp-btn">Account</Link>
        </li>
    </ul>

    <form
        class="cp-card mt-6 max-w-3xl space-y-4 p-4"
        @submit.prevent="submitInvite"
    >
        <div>
            <h2 class="font-semibold">Invite someone</h2>
            <p class="text-sm text-neutral-500">
                You get a link to send them, to choose their password with.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-[1fr_1fr_9rem]">
            <div class="space-y-1.5">
                <label for="invite-name" class="cp-label">Name</label>
                <input
                    id="invite-name"
                    v-model="invite.name"
                    type="text"
                    autocomplete="off"
                    class="cp-input"
                />
                <p v-if="invite.errors.name" class="cp-error">
                    {{ invite.errors.name }}
                </p>
            </div>
            <div class="space-y-1.5">
                <label for="invite-email" class="cp-label">Email</label>
                <input
                    id="invite-email"
                    v-model="invite.email"
                    type="email"
                    autocomplete="off"
                    class="cp-input"
                />
                <p v-if="invite.errors.email" class="cp-error">
                    {{ invite.errors.email }}
                </p>
            </div>
            <div class="space-y-1.5">
                <label for="invite-role" class="cp-label">Role</label>
                <select id="invite-role" v-model="invite.role" class="cp-input">
                    <option
                        v-for="role in roles"
                        :key="role.value"
                        :value="role.value"
                    >
                        {{ role.label }}
                    </option>
                </select>
            </div>
        </div>

        <button
            type="submit"
            class="cp-btn-primary"
            :disabled="invite.processing"
        >
            Create invite link
        </button>
    </form>
</template>
