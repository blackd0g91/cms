<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, provide, ref } from 'vue';
import { openSidebarKey } from '@/lib/sidebar';
import { cn } from '@/lib/utils';
import { dashboard, logout } from '@/routes/cp';
import { edit as accountEdit } from '@/routes/cp/account';
import { edit as linksEdit } from '@/routes/cp/links';
import { index as mediaIndex } from '@/routes/cp/media';
import { index as postsIndex } from '@/routes/cp/posts';
import { edit as settingsEdit } from '@/routes/cp/settings';
import { index as tagsIndex } from '@/routes/cp/tags';
import { index as templatesIndex } from '@/routes/cp/templates';

type NavItem = {
    title: string;
    href: string;
    active: (path: string) => boolean;
};

const page = usePage();
const sidebarOpen = ref(false);

// Every page starts with a PageHeader, which has the menu button on small screens.
provide(openSidebarKey, () => (sidebarOpen.value = true));

const path = computed(() => page.url.split('?')[0]);
const isPostsPath = (path: string) => /^\/cp\/templates\/\d+\/posts/.test(path);

const mainNav: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
        active: (path) => path === dashboard().url,
    },
    {
        title: 'Posts',
        href: postsIndex().url,
        active: (path) =>
            path.startsWith(postsIndex().url) || isPostsPath(path),
    },
    {
        title: 'Templates',
        href: templatesIndex().url,
        active: (path) =>
            path.startsWith(templatesIndex().url) && !isPostsPath(path),
    },
    {
        title: 'Tags',
        href: tagsIndex().url,
        active: (path) => path.startsWith(tagsIndex().url),
    },
    {
        title: 'Links',
        href: linksEdit().url,
        active: (path) => path === linksEdit().url,
    },
    {
        title: 'Media',
        href: mediaIndex().url,
        active: (path) => path.startsWith(mediaIndex().url),
    },
    {
        title: 'Settings',
        href: settingsEdit().url,
        active: (path) => path === settingsEdit().url,
    },
];

const linkClass = (item: NavItem) =>
    cn(
        'block truncate rounded-md px-3 py-2 hover:bg-neutral-100 dark:hover:bg-neutral-800',
        item.active(path.value) &&
            'bg-neutral-100 font-medium dark:bg-neutral-800',
    );
</script>

<template>
    <div
        class="flex min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-black/40 md:hidden"
            @click="sidebarOpen = false"
        />

        <aside
            :class="
                cn(
                    'fixed inset-y-0 left-0 z-40 flex w-60 shrink-0 flex-col border-r border-neutral-200 bg-white transition-transform md:sticky md:top-0 md:h-screen md:translate-x-0 dark:border-neutral-800 dark:bg-neutral-900',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                )
            "
        >
            <div
                class="flex h-14 items-center border-b border-neutral-200 px-5 font-semibold dark:border-neutral-800"
            >
                <Link :href="dashboard()">{{ page.props.name }}</Link>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto p-3 text-sm">
                <ul class="space-y-1">
                    <li v-for="item in mainNav" :key="item.title">
                        <Link
                            :href="item.href"
                            :class="linkClass(item)"
                            @click="sidebarOpen = false"
                        >
                            {{ item.title }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <div
                class="border-t border-neutral-200 p-3 text-sm dark:border-neutral-800"
            >
                <Link
                    :href="accountEdit()"
                    title="Account"
                    :class="
                        cn(
                            'block rounded-md px-3 py-2 hover:bg-neutral-100 dark:hover:bg-neutral-800',
                            path === accountEdit().url &&
                                'bg-neutral-100 dark:bg-neutral-800',
                        )
                    "
                    @click="sidebarOpen = false"
                >
                    <span class="block truncate font-medium">
                        {{ page.props.auth.user.name }}
                    </span>
                    <span class="block truncate text-xs text-neutral-500">
                        {{ page.props.auth.user.email }}
                    </span>
                </Link>
                <Link
                    :href="logout()"
                    as="button"
                    class="mt-1 w-full rounded-md px-3 py-2 text-left hover:bg-neutral-100 dark:hover:bg-neutral-800"
                >
                    Log out
                </Link>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
