<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, provide, ref } from 'vue';
import CpIcon from '@/components/cp/CpIcon.vue';
import type { IconName } from '@/components/cp/CpIcon.vue';
import SiteMark from '@/components/cp/SiteMark.vue';
import ThemeButton from '@/components/cp/ThemeButton.vue';
import { openSidebarKey } from '@/lib/sidebar';
import { cn, initials } from '@/lib/utils';
import { home } from '@/routes';
import { dashboard, health, logout } from '@/routes/cp';
import { edit as accountEdit } from '@/routes/cp/account';
import { edit as linksEdit } from '@/routes/cp/links';
import { index as mediaIndex } from '@/routes/cp/media';
import { index as postsIndex } from '@/routes/cp/posts';
import { edit as settingsEdit } from '@/routes/cp/settings';
import { index as tagsIndex } from '@/routes/cp/tags';
import { index as templatesIndex } from '@/routes/cp/templates';
import { index as trashIndex } from '@/routes/cp/trash';
import { index as usersIndex } from '@/routes/cp/users';

type NavItem = {
    title: string;
    href: string;
    icon: IconName;
    active: (path: string) => boolean;
    // Editors can not open these (see routes/cp.php).
    adminOnly?: boolean;
};

type NavGroup = {
    title?: string;
    items: NavItem[];
};

const page = usePage();
const sidebarOpen = ref(false);

// Every page starts with a PageHeader, which has the menu button on small screens.
provide(openSidebarKey, () => (sidebarOpen.value = true));

const path = computed(() => page.url.split('?')[0]);
const isPostsPath = (path: string) => /^\/cp\/templates\/\d+\/posts/.test(path);

const groups: NavGroup[] = [
    {
        items: [
            {
                title: 'Dashboard',
                href: dashboard().url,
                icon: 'dashboard',
                active: (path) => path === dashboard().url,
            },
            {
                title: 'Health',
                href: health().url,
                icon: 'health',
                active: (path) => path === health().url,
            },
        ],
    },
    {
        title: 'Content',
        items: [
            {
                title: 'Posts',
                href: postsIndex().url,
                icon: 'posts',
                active: (path) =>
                    path.startsWith(postsIndex().url) || isPostsPath(path),
            },
            {
                title: 'Tags',
                href: tagsIndex().url,
                icon: 'tags',
                active: (path) => path.startsWith(tagsIndex().url),
            },
            {
                title: 'Media',
                href: mediaIndex().url,
                icon: 'media',
                active: (path) => path.startsWith(mediaIndex().url),
            },
            {
                title: 'Trash',
                href: trashIndex().url,
                icon: 'trash',
                active: (path) => path === trashIndex().url,
            },
        ],
    },
    {
        title: 'Site',
        items: [
            {
                title: 'Templates',
                href: templatesIndex().url,
                icon: 'templates',
                active: (path) =>
                    path.startsWith(templatesIndex().url) && !isPostsPath(path),
                adminOnly: true,
            },
            {
                title: 'Links',
                href: linksEdit().url,
                icon: 'links',
                active: (path) => path === linksEdit().url,
                adminOnly: true,
            },
            {
                title: 'Users',
                href: usersIndex().url,
                icon: 'users',
                active: (path) => path.startsWith(usersIndex().url),
                adminOnly: true,
            },
            {
                title: 'Settings',
                href: settingsEdit().url,
                icon: 'settings',
                active: (path) => path === settingsEdit().url,
                adminOnly: true,
            },
        ],
    },
];

const nav = computed(() => {
    const isAdmin = page.props.auth.user.role === 'admin';

    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => isAdmin || !item.adminOnly),
        }))
        .filter((group) => group.items.length > 0);
});

const rowClass = (active: boolean) =>
    cn(
        'group flex items-center gap-2.5 rounded-lg px-3 py-2 transition-colors',
        active
            ? 'bg-white font-medium text-neutral-900 shadow-xs ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-100 dark:ring-neutral-700'
            : 'text-neutral-600 hover:bg-neutral-200/60 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800/60 dark:hover:text-neutral-100',
    );

const iconClass = (active: boolean) =>
    cn(
        'size-4 shrink-0 transition-colors',
        active
            ? 'text-brand'
            : 'text-neutral-400 group-hover:text-neutral-600 dark:text-neutral-500 dark:group-hover:text-neutral-300',
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
                    'fixed inset-y-0 left-0 z-40 flex w-60 shrink-0 flex-col border-r border-neutral-200 bg-neutral-100 transition-transform md:sticky md:top-0 md:h-screen md:translate-x-0 dark:border-neutral-800 dark:bg-neutral-900',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                )
            "
        >
            <div
                class="flex h-14 shrink-0 items-center gap-2 border-b border-neutral-200 pr-3 pl-4 dark:border-neutral-800"
            >
                <Link
                    :href="dashboard()"
                    class="flex min-w-0 items-center gap-2.5 font-semibold"
                >
                    <SiteMark />
                    <span class="truncate">{{ page.props.name }}</span>
                </Link>
                <a
                    :href="home().url"
                    target="_blank"
                    title="View site"
                    aria-label="View site"
                    class="cp-icon-btn ml-auto shrink-0"
                >
                    <CpIcon name="external" class="size-4" />
                </a>
            </div>

            <nav class="flex-1 space-y-5 overflow-y-auto p-3 text-sm">
                <div v-for="(group, i) in nav" :key="group.title ?? i">
                    <p
                        v-if="group.title"
                        class="mb-1 px-3 text-[11px] font-semibold tracking-wider text-neutral-500 uppercase"
                    >
                        {{ group.title }}
                    </p>
                    <ul class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.title">
                            <Link
                                :href="item.href"
                                :class="rowClass(item.active(path))"
                                :aria-current="
                                    item.active(path) ? 'page' : undefined
                                "
                                @click="sidebarOpen = false"
                            >
                                <CpIcon
                                    :name="item.icon"
                                    :class="iconClass(item.active(path))"
                                />
                                <span class="truncate">{{ item.title }}</span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div
                class="space-y-0.5 border-t border-neutral-200 p-3 text-sm dark:border-neutral-800"
            >
                <!-- Small buttons for the whole panel. -->
                <div class="flex items-center justify-end gap-1">
                    <ThemeButton />
                    <Link
                        :href="logout()"
                        as="button"
                        title="Log out"
                        aria-label="Log out"
                        class="cp-icon-btn-danger"
                    >
                        <CpIcon name="logout" class="size-4" />
                    </Link>
                </div>
                <Link
                    :href="accountEdit()"
                    title="Account"
                    :class="rowClass(path === accountEdit().url)"
                    @click="sidebarOpen = false"
                >
                    <span
                        class="grid size-7 shrink-0 place-items-center rounded-full bg-neutral-200 text-[11px] font-semibold text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200"
                        aria-hidden="true"
                    >
                        {{ initials(page.props.auth.user.name) }}
                    </span>
                    <span class="min-w-0">
                        <span
                            class="block truncate font-medium text-neutral-900 dark:text-neutral-100"
                        >
                            {{ page.props.auth.user.name }}
                        </span>
                        <span
                            class="block truncate text-xs font-normal text-neutral-500"
                        >
                            {{ page.props.auth.user.email }}
                        </span>
                    </span>
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
