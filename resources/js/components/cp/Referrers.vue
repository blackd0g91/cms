<script setup lang="ts">
import { computed } from 'vue';

export type ReferrerOverview = {
    total: number;
    // Most visits first. "" is visits without a source.
    sources: { source: string; visits: number }[];
};

const props = defineProps<{
    referrers: ReferrerOverview;
}>();

const largest = computed(() =>
    Math.max(1, ...props.referrers.sources.map((source) => source.visits)),
);

const visits = (count: number) =>
    `${count.toLocaleString()} ${count === 1 ? 'visit' : 'visits'}`;

const share = (count: number) =>
    `${Math.round((count / Math.max(1, props.referrers.total)) * 100)}%`;

// Sites have a dot in their name; ?ref= names usually do not.
const isSite = (source: string) => /^[a-z0-9-]+(\.[a-z0-9-]+)+$/.test(source);
</script>

<template>
    <section class="cp-card">
        <div
            class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800"
        >
            <h2 class="font-semibold">Where visitors come from</h2>
            <p class="text-xs text-neutral-500">
                Where visitors arrived from in the last 30 days<template
                    v-if="referrers.total"
                    >, {{ visits(referrers.total) }} in all</template
                >
            </p>
        </div>

        <p
            v-if="referrers.sources.length === 0"
            class="p-4 text-sm text-neutral-500"
        >
            No visits yet. Each visitor counts once a day per site they came
            from. Links from your own apps can add
            <code class="text-xs">?ref=app-name</code> to show up by name.
        </p>

        <ol v-else class="space-y-3 p-4">
            <li
                v-for="{ source, visits: count } in referrers.sources"
                :key="source"
                class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1"
            >
                <div class="min-w-0">
                    <a
                        v-if="isSite(source)"
                        :href="`https://${source}`"
                        target="_blank"
                        rel="noreferrer"
                        class="block truncate text-sm font-medium hover:underline"
                    >
                        {{ source }}
                    </a>
                    <p v-else class="truncate text-sm font-medium">
                        {{ source || 'Direct or unknown' }}
                    </p>
                    <p class="text-xs text-neutral-500">
                        {{
                            source === ''
                                ? 'Typed in, bookmarked, or the link was private'
                                : isSite(source)
                                  ? 'A link on that site'
                                  : 'Named in the link'
                        }}
                    </p>
                </div>
                <span
                    class="text-sm text-neutral-700 tabular-nums dark:text-neutral-300"
                >
                    {{ visits(count) }}
                    <span class="text-neutral-400"
                        >&middot; {{ share(count) }}</span
                    >
                </span>
                <!-- Share of the biggest source, on one scale. -->
                <span
                    class="col-span-2 h-1.5 rounded-[4px] bg-[#2a78d6] dark:bg-[#3987e5]"
                    :style="{ width: `${(count / largest) * 100}%` }"
                    aria-hidden="true"
                />
            </li>
        </ol>
    </section>
</template>
