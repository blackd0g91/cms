<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { daysAgo } from '@/lib/utils';
import { edit } from '@/routes/cp/templates/posts';
import type { PostListItem } from '@/types';

export type Week = {
    // Over the last 7 days and the 7 before them.
    views: { total: number; previous: number };
    searches: { total: number; previous: number };
    top: (PostListItem & { views: number }) | null;
    lastPublished: PostListItem | null;
};

const props = defineProps<{ week: Week }>();

// Against the week before, when there is anything to compare with.
const change = ({ total, previous }: { total: number; previous: number }) => {
    if (previous === 0) {
        return null;
    }

    const percent = Math.round(((total - previous) / previous) * 100);

    if (percent === 0) {
        return {
            arrow: '→',
            label: 'same as last week',
            class: 'text-neutral-500',
        };
    }

    return percent > 0
        ? {
              arrow: '↑',
              label: `${percent}% up on last week`,
              class: 'text-green-700 dark:text-green-400',
          }
        : {
              arrow: '↓',
              label: `${-percent}% down on last week`,
              class: 'text-red-600 dark:text-red-400',
          };
};

const counters = computed(() =>
    [
        { label: 'Views this week', totals: props.week.views },
        { label: 'Searches this week', totals: props.week.searches },
    ].map(({ label, totals }) => ({
        label,
        total: totals.total,
        change: change(totals),
    })),
);

const views = (count: number) =>
    `${count.toLocaleString()} ${count === 1 ? 'view' : 'views'}`;
</script>

<template>
    <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div
            v-for="counter in counters"
            :key="counter.label"
            class="cp-card p-4"
        >
            <dt class="text-xs font-medium text-neutral-500">
                {{ counter.label }}
            </dt>
            <dd
                class="mt-1.5 text-3xl font-semibold tracking-tight tabular-nums"
            >
                {{ counter.total.toLocaleString() }}
            </dd>
            <dd
                v-if="counter.change"
                :class="['mt-1 text-xs', counter.change.class]"
            >
                <span aria-hidden="true">{{ counter.change.arrow }}</span>
                {{ counter.change.label }}
            </dd>
            <dd v-else class="mt-1 text-xs text-neutral-500">
                Nothing to compare with last week
            </dd>
        </div>

        <div class="cp-card min-w-0 p-4">
            <dt class="text-xs font-medium text-neutral-500">
                Most read this week
            </dt>
            <template v-if="week.top">
                <dd class="mt-1.5 truncate text-lg font-semibold">
                    <Link
                        :href="edit([week.top.template.id, week.top.id])"
                        class="hover:underline"
                    >
                        {{ week.top.title }}
                    </Link>
                </dd>
                <dd class="mt-1 text-xs text-neutral-500">
                    {{ views(week.top.views) }} &middot;
                    {{ week.top.template.name }}
                </dd>
            </template>
            <dd v-else class="mt-1.5 text-sm text-neutral-500">
                No views yet this week.
            </dd>
        </div>

        <div class="cp-card min-w-0 p-4">
            <dt class="text-xs font-medium text-neutral-500">Last published</dt>
            <template v-if="week.lastPublished?.published_at">
                <dd class="mt-1.5 text-3xl font-semibold tracking-tight">
                    {{ daysAgo(week.lastPublished.published_at) }}
                </dd>
                <dd class="mt-1 truncate text-xs text-neutral-500">
                    <Link
                        :href="
                            edit([
                                week.lastPublished.template.id,
                                week.lastPublished.id,
                            ])
                        "
                        class="hover:underline"
                    >
                        {{ week.lastPublished.title }}
                    </Link>
                </dd>
            </template>
            <dd v-else class="mt-1.5 text-sm text-neutral-500">
                Nothing published yet.
            </dd>
        </div>
    </dl>
</template>
