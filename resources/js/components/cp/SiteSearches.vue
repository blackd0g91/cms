<script setup lang="ts">
import { search } from '@/routes';

export type SearchOverview = {
    total: number;
    found: { query: string; times: number; results: number }[];
    nothing: { query: string; times: number }[];
};

defineProps<{
    searches: SearchOverview;
}>();

const plural = (count: number, one: string, many: string) =>
    `${count.toLocaleString()} ${count === 1 ? one : many}`;

// The site's own results page, to see what visitors saw.
const resultsUrl = (query: string) => search({ query: { q: query } }).url;
</script>

<template>
    <section class="cp-card">
        <div
            class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800"
        >
            <h2 class="font-semibold">Searches</h2>
            <p class="text-xs text-neutral-500">
                What visitors searched for in the last 30 days<template
                    v-if="searches.total"
                    >, {{ plural(searches.total, 'search', 'searches') }} in
                    all</template
                >
            </p>
        </div>

        <p
            v-if="searches.found.length + searches.nothing.length === 0"
            class="p-4 text-sm text-neutral-500"
        >
            No searches in the last 30 days.
        </p>

        <div
            v-else
            class="grid divide-y divide-neutral-200 md:grid-cols-2 md:divide-x md:divide-y-0 dark:divide-neutral-800"
        >
            <div class="p-4">
                <h3
                    class="mb-2 text-xs font-medium tracking-wide text-neutral-500 uppercase"
                >
                    Most searched
                </h3>
                <p
                    v-if="searches.found.length === 0"
                    class="text-sm text-neutral-500"
                >
                    None of the searches find anything yet.
                </p>
                <ol v-else class="space-y-2">
                    <li
                        v-for="item in searches.found"
                        :key="item.query"
                        class="flex items-baseline gap-3"
                    >
                        <div class="min-w-0 flex-1">
                            <a
                                :href="resultsUrl(item.query)"
                                target="_blank"
                                class="block truncate text-sm font-medium hover:underline"
                                :title="item.query"
                            >
                                {{ item.query }}
                            </a>
                            <p class="text-xs text-neutral-500">
                                Finds
                                {{ plural(item.results, 'post', 'posts') }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 text-sm text-neutral-700 tabular-nums dark:text-neutral-300"
                        >
                            {{ plural(item.times, 'time', 'times') }}
                        </span>
                    </li>
                </ol>
            </div>

            <div class="p-4">
                <h3
                    class="mb-2 text-xs font-medium tracking-wide text-neutral-500 uppercase"
                >
                    Found nothing
                </h3>
                <p
                    v-if="searches.nothing.length === 0"
                    class="text-sm text-neutral-500"
                >
                    Every search finds something.
                </p>
                <template v-else>
                    <ol class="space-y-2">
                        <li
                            v-for="item in searches.nothing"
                            :key="item.query"
                            class="flex items-baseline gap-3"
                        >
                            <a
                                :href="resultsUrl(item.query)"
                                target="_blank"
                                class="min-w-0 flex-1 truncate text-sm font-medium hover:underline"
                                :title="item.query"
                            >
                                {{ item.query }}
                            </a>
                            <span
                                class="shrink-0 text-sm text-neutral-700 tabular-nums dark:text-neutral-300"
                            >
                                {{ plural(item.times, 'time', 'times') }}
                            </span>
                        </li>
                    </ol>
                    <p class="mt-3 text-xs text-neutral-500">
                        Ideas for what to write next. Once a published post
                        matches, the search moves to Most searched.
                    </p>
                </template>
            </div>
        </div>
    </section>
</template>
