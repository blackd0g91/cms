<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { edit as linksEdit } from '@/routes/cp/links';
import { edit as settingsEdit } from '@/routes/cp/settings';

export type ClickOverview = {
    total: number;
    // Every link and profile the site shows, most clicked first.
    links: {
        target: string;
        kind: 'link' | 'profile';
        label: string;
        emoji: string | null;
        // A profile site's icon: the inside of a 24px outline SVG.
        icon: string | null;
        href: string;
        clicks: number;
    }[];
};

const props = defineProps<{
    clicks: ClickOverview;
}>();

const largest = computed(() =>
    Math.max(1, ...props.clicks.links.map((link) => link.clicks)),
);

const count = (clicks: number) =>
    `${clicks.toLocaleString()} ${clicks === 1 ? 'click' : 'clicks'}`;
</script>

<template>
    <section class="cp-card">
        <div
            class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800"
        >
            <h2 class="font-semibold">Link clicks</h2>
            <p class="text-xs text-neutral-500">
                Which of your links and profiles visitors followed in the last
                30 days<template v-if="clicks.total"
                    >, {{ count(clicks.total) }} in all</template
                >
            </p>
        </div>

        <p
            v-if="clicks.links.length === 0"
            class="p-4 text-sm text-neutral-500"
        >
            The site has no links or profiles yet. Add them on the
            <template v-if="$page.props.auth.user.role === 'admin'">
                <Link :href="linksEdit()" class="underline">Links</Link> and
                <Link :href="settingsEdit()" class="underline">Settings</Link>
                pages.
            </template>
            <template v-else>Links and Settings pages.</template>
        </p>

        <ol v-else class="space-y-3 p-4">
            <li
                v-for="link in clicks.links"
                :key="link.target"
                class="grid grid-cols-[1.25rem_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1"
            >
                <!-- The icon comes from the server's list of profile sites. -->
                <svg
                    v-if="link.icon"
                    class="size-4 justify-self-center text-neutral-500"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                    v-html="link.icon"
                />
                <span v-else class="text-center text-sm" aria-hidden="true">{{
                    link.emoji ?? '↗'
                }}</span>
                <div class="min-w-0">
                    <a
                        :href="link.href"
                        target="_blank"
                        class="block truncate text-sm font-medium hover:underline"
                    >
                        {{ link.label }}
                    </a>
                    <p class="text-xs text-neutral-500">
                        {{ link.kind === 'profile' ? 'Profile' : 'Link' }}
                    </p>
                </div>
                <span
                    :class="[
                        'text-sm tabular-nums',
                        link.clicks
                            ? 'text-neutral-700 dark:text-neutral-300'
                            : 'text-neutral-400 dark:text-neutral-500',
                    ]"
                >
                    {{ count(link.clicks) }}
                </span>
                <!-- Share of the most clicked link, on one scale. -->
                <span
                    v-if="link.clicks"
                    class="col-start-2 col-end-4 h-1.5 rounded-[4px] bg-[#2a78d6] dark:bg-[#3987e5]"
                    :style="{ width: `${(link.clicks / largest) * 100}%` }"
                    aria-hidden="true"
                />
            </li>
        </ol>
    </section>
</template>
