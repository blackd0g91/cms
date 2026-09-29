<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    // Published posts per month, oldest first, as "YYYY-MM".
    months: { month: string; count: number }[];
}>();

const total = computed(() =>
    props.months.reduce((sum, month) => sum + month.count, 0),
);

const largest = computed(() =>
    Math.max(1, ...props.months.map((month) => month.count)),
);

const date = (month: string) => {
    const [year, number] = month.split('-').map(Number);

    return new Date(year, number - 1, 1);
};

const short = (month: string, index: number) => {
    const d = date(month);
    const name = d.toLocaleDateString(undefined, { month: 'short' });

    // The year on the first column and at every January.
    return index === 0 || d.getMonth() === 0
        ? `${name} ’${String(d.getFullYear()).slice(2)}`
        : name;
};

const long = (month: string) =>
    date(month).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });

const posts = (count: number) => `${count} ${count === 1 ? 'post' : 'posts'}`;

// Only the busiest month and the current one carry a value label; hovering
// shows the rest.
const labelled = computed(() => {
    const busiest = props.months.findIndex(
        (month) => month.count === largest.value && month.count > 0,
    );

    return new Set([busiest, props.months.length - 1]);
});
</script>

<template>
    <section class="cp-card">
        <div
            class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800"
        >
            <h2 class="font-semibold">Publishing activity</h2>
            <p class="text-xs text-neutral-500">
                {{ posts(total) }} published in the last 12 months
            </p>
        </div>

        <div class="px-4 pt-6 pb-3">
            <div
                class="flex h-36 items-end gap-[2px] border-b border-neutral-200 dark:border-neutral-800"
                aria-hidden="true"
            >
                <div
                    v-for="(month, i) in months"
                    :key="month.month"
                    class="group relative flex h-full flex-1 flex-col items-center justify-end"
                    tabindex="0"
                >
                    <!-- Value label on the cap, for the labelled columns only. -->
                    <span
                        v-if="labelled.has(i) && month.count > 0"
                        class="mb-1 text-xs text-neutral-600 tabular-nums dark:text-neutral-400 [.group:focus_&]:invisible [.group:hover_&]:invisible"
                    >
                        {{ month.count }}
                    </span>
                    <span
                        class="w-full max-w-6 rounded-t-[4px] bg-[#2a78d6] transition-opacity dark:bg-[#3987e5] [.group:hover_&]:opacity-80"
                        :style="{
                            height: month.count
                                ? `max(3px, ${(month.count / largest) * 100}%)`
                                : '0',
                        }"
                    />

                    <!-- Hover / focus tooltip. Plain :hover rules (not Tailwind's
                         group-hover, which needs a mouse-capable device) so a
                         tap shows it on phones too. -->
                    <span
                        class="pointer-events-none absolute bottom-full z-10 mb-1 hidden rounded-md bg-neutral-900 px-2 py-1 text-xs whitespace-nowrap text-white shadow dark:bg-neutral-100 dark:text-neutral-900 [.group:focus_&]:block [.group:hover_&]:block"
                    >
                        {{ long(month.month) }} &middot;
                        {{ posts(month.count) }}
                    </span>
                </div>
            </div>

            <div class="mt-1.5 flex gap-[2px]" aria-hidden="true">
                <span
                    v-for="(month, i) in months"
                    :key="month.month"
                    class="flex-1 truncate text-center text-[11px] text-neutral-500"
                >
                    {{ short(month.month, i) }}
                </span>
            </div>

            <!-- The same numbers as a table, for screen readers. -->
            <table class="sr-only">
                <caption>
                    Posts published per month
                </caption>
                <tr v-for="month in months" :key="month.month">
                    <th scope="row">{{ long(month.month) }}</th>
                    <td>{{ month.count }}</td>
                </tr>
            </table>
        </div>
    </section>
</template>
