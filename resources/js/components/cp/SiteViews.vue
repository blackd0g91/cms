<script setup lang="ts">
import { computed } from 'vue';
import ViewsChart from '@/components/cp/ViewsChart.vue';
import type { DayViews } from '@/types';

const props = defineProps<{
    // Views of every post over the last 30 days, and the 30 before them.
    daily: DayViews[];
    total: number;
    previous: number;
}>();

const views = (count: number) =>
    `${count.toLocaleString()} ${count === 1 ? 'view' : 'views'}`;

// Against the 30 days before, when there is anything to compare with.
const change = computed(() => {
    if (props.previous === 0) {
        return null;
    }

    const percent = Math.round(
        ((props.total - props.previous) / props.previous) * 100,
    );

    return percent === 0
        ? 'about the same as the 30 days before'
        : `${Math.abs(percent)}% ${percent > 0 ? 'more' : 'fewer'} than the 30 days before`;
});
</script>

<template>
    <section class="cp-card flex flex-col">
        <div
            class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800"
        >
            <h2 class="font-semibold">Views</h2>
            <p class="text-xs text-neutral-500">
                {{ views(total) }} in the last 30 days
                <template v-if="change">&middot; {{ change }}</template>
            </p>
        </div>

        <p v-if="total === 0" class="p-4 text-sm text-neutral-500">
            No views yet. Visits to published posts are counted here, one per
            visitor per day. Yours don't count while you're logged in.
        </p>

        <!-- As tall as the card beside it, on the dashboard. -->
        <div v-else class="flex flex-1 flex-col px-4 pt-8 pb-3">
            <ViewsChart
                :days="daily"
                caption="Views of every post per day, over the last 30 days"
                height="fill"
                class="flex-1"
            />
        </div>
    </section>
</template>
