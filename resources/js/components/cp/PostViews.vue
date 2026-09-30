<script setup lang="ts">
import { computed } from 'vue';
import ViewsChart from '@/components/cp/ViewsChart.vue';
import type { DayViews } from '@/types';

export type ViewHistory = {
    // Up to the last 90 days, oldest first: from when the post was
    // published when that is more recent, but a week at least.
    daily: DayViews[];
    last_30_days: number;
    total: number;
    best: DayViews | null;
};

const props = defineProps<{
    history: ViewHistory;
}>();

// With the year only when it is not this one.
const day = (value: string) => {
    const [year, month, date] = value.split('-').map(Number);

    return new Date(year, month - 1, date).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        ...(year === new Date().getFullYear() ? {} : { year: 'numeric' }),
    });
};

// What the chart covers: the last 90 days, a week, or since publishing.
const range = computed(() => {
    const days = props.history.daily.length;

    if (days >= 90 || days <= 7) {
        return `Last ${days} days`;
    }

    return `Since ${day(props.history.daily[0].date)}`;
});
</script>

<template>
    <section class="cp-card space-y-3 p-4">
        <div class="flex items-baseline justify-between gap-2">
            <h2 class="cp-label">Views</h2>
            <span v-if="history.total" class="text-xs text-neutral-500">{{
                range
            }}</span>
        </div>

        <p v-if="history.total === 0" class="text-xs text-neutral-500">
            No views yet. Each visitor counts once a day, and yours don't count
            while you're logged in.
        </p>

        <template v-else>
            <div class="pt-5">
                <ViewsChart
                    :days="history.daily"
                    :height="64"
                    :caption="`Views of this post per day. ${range}.`"
                />
            </div>

            <dl class="grid grid-cols-3 gap-2">
                <div>
                    <dt class="text-xs text-neutral-500">30 days</dt>
                    <dd class="text-sm font-semibold">
                        {{ history.last_30_days.toLocaleString() }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-500">All time</dt>
                    <dd class="text-sm font-semibold">
                        {{ history.total.toLocaleString() }}
                    </dd>
                </div>
                <div v-if="history.best">
                    <dt class="text-xs text-neutral-500">Best day</dt>
                    <dd class="text-sm font-semibold">
                        {{ history.best.views.toLocaleString() }}
                        <span
                            class="block text-xs font-normal text-neutral-500"
                        >
                            {{ day(history.best.date) }}
                        </span>
                    </dd>
                </div>
            </dl>
        </template>
    </section>
</template>
