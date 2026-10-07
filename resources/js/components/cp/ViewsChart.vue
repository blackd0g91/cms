<script setup lang="ts">
import { computed, ref } from 'vue';
import type { DayViews } from '@/types';

const props = withDefaults(
    defineProps<{
        // One entry per day, oldest first and ending today.
        days: DayViews[];
        // What is counted, for screen readers.
        caption: string;
        // Of the plot, in pixels, or "fill" to take the room the chart is
        // given (144px at least).
        height?: number | 'fill';
    }>(),
    { height: 144 },
);

const last = computed(() => props.days.length - 1);

const largest = computed(() =>
    Math.max(1, ...props.days.map((day) => day.views)),
);

// Positions in percent of the plot, from the left and from the top.
const left = (i: number) => (last.value > 0 ? (i / last.value) * 100 : 50);
const top = (views: number) => 100 - (views / largest.value) * 100;

// A smooth curve through every day (monotone cubic, Fritsch–Carlson): it
// never overshoots, so it stays above zero and peaks on the busiest day.
const line = computed(() => {
    const xs = props.days.map((_, i) => left(i));
    const ys = props.days.map((day) => top(day.views));
    const n = xs.length;

    if (n < 3) {
        return xs.map((x, i) => `${i ? 'L' : 'M'}${x} ${ys[i]}`).join(' ');
    }

    const slopes = xs.slice(1).map((x, i) => (ys[i + 1] - ys[i]) / (x - xs[i]));
    const tangents = xs.map((_, i) => {
        if (i === 0) {
            return slopes[0];
        }

        if (i === n - 1) {
            return slopes[n - 2];
        }

        const [before, after] = [slopes[i - 1], slopes[i]];

        // Flat at peaks, valleys and plateaus.
        return before * after <= 0
            ? 0
            : (2 * before * after) / (before + after);
    });

    let path = `M${xs[0]} ${ys[0]}`;

    for (let i = 0; i < n - 1; i++) {
        const third = (xs[i + 1] - xs[i]) / 3;
        path += ` C${xs[i] + third} ${ys[i] + tangents[i] * third} ${xs[i + 1] - third} ${ys[i + 1] - tangents[i + 1] * third} ${xs[i + 1]} ${ys[i + 1]}`;
    }

    return path;
});

const area = computed(() => `${line.value} L100 100 L0 100 Z`);

const date = (value: string) => {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
};

const short = (value: string) =>
    date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });

const long = (value: string) =>
    date(value).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

const views = (count: number) =>
    `${count.toLocaleString()} ${count === 1 ? 'view' : 'views'}`;

// Only the busiest day and today carry their value; the rest show on hover.
const labelled = computed(() => {
    const busiest = props.days.findIndex((day) => day.views === largest.value);

    return [...new Set([busiest, last.value])].filter(
        (i) => i >= 0 && props.days[i].views > 0,
    );
});

// The day under the pointer, or picked with the arrow keys.
const active = ref<number | null>(null);
const plot = ref<HTMLElement>();

const clamp = (i: number) => Math.min(last.value, Math.max(0, i));

const pointAt = (event: PointerEvent) => {
    const box = plot.value?.getBoundingClientRect();

    if (box && box.width > 0) {
        active.value = clamp(
            Math.round(((event.clientX - box.left) / box.width) * last.value),
        );
    }
};

// A tap keeps its day showing; only a mouse leaving clears it.
const leave = (event: PointerEvent) => {
    if (event.pointerType === 'mouse') {
        active.value = null;
    }
};

const moves: Record<string, number> = {
    ArrowLeft: -1,
    ArrowRight: 1,
    Home: -Infinity,
    End: Infinity,
};

const step = (event: KeyboardEvent) => {
    if (event.key in moves) {
        event.preventDefault();
        active.value = clamp((active.value ?? last.value) + moves[event.key]);
    }
};

// Slides from left-aligned at the first day to right-aligned at the last,
// so it never leaves the plot.
const at = (i: number, y?: number) => ({
    left: `${left(i)}%`,
    ...(y === undefined ? {} : { top: `${y}%` }),
    transform: `translateX(-${left(i)}%)`,
});
</script>

<template>
    <div class="flex flex-col">
        <div
            ref="plot"
            :class="height === 'fill' && 'min-h-36 flex-1'"
            class="relative cursor-crosshair touch-pan-y border-b border-neutral-200 outline-none focus-visible:ring-2 focus-visible:ring-neutral-300 focus-visible:ring-offset-4 dark:border-neutral-800 dark:ring-offset-neutral-900 dark:focus-visible:ring-neutral-700"
            :style="height === 'fill' ? {} : { height: `${height}px` }"
            tabindex="0"
            :aria-label="`${caption}. Use the arrow keys to read each day.`"
            @pointerdown="pointAt"
            @pointermove="pointAt"
            @pointerleave="leave"
            @focus="active = active ?? last"
            @blur="active = null"
            @keydown="step"
        >
            <svg
                class="absolute inset-0 size-full overflow-visible"
                viewBox="0 0 100 100"
                preserveAspectRatio="none"
                aria-hidden="true"
            >
                <path
                    :d="area"
                    class="fill-[#2a78d6]/10 dark:fill-[#3987e5]/10"
                />
                <path
                    :d="line"
                    fill="none"
                    class="stroke-[#2a78d6] dark:stroke-[#3987e5]"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    vector-effect="non-scaling-stroke"
                />
            </svg>

            <!-- The labelled days: a dot on the line and the value above it. -->
            <template v-if="active === null">
                <template v-for="i in labelled" :key="i">
                    <span
                        class="pointer-events-none absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#2a78d6] ring-2 ring-white dark:bg-[#3987e5] dark:ring-neutral-900"
                        :style="{
                            left: `${left(i)}%`,
                            top: `${top(days[i].views)}%`,
                        }"
                        aria-hidden="true"
                    />
                    <span
                        class="pointer-events-none absolute -mt-6 text-xs text-neutral-600 tabular-nums dark:text-neutral-400"
                        :style="at(i, top(days[i].views))"
                        aria-hidden="true"
                    >
                        {{ days[i].views.toLocaleString() }}
                    </span>
                </template>
            </template>

            <!-- Crosshair and readout for the active day. -->
            <template v-else>
                <span
                    class="pointer-events-none absolute inset-y-0 w-px -translate-x-1/2 bg-neutral-300 dark:bg-neutral-600"
                    :style="{ left: `${left(active)}%` }"
                    aria-hidden="true"
                />
                <span
                    class="pointer-events-none absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#2a78d6] ring-2 ring-white dark:bg-[#3987e5] dark:ring-neutral-900"
                    :style="{
                        left: `${left(active)}%`,
                        top: `${top(days[active].views)}%`,
                    }"
                    aria-hidden="true"
                />
                <span
                    class="pointer-events-none absolute bottom-full z-10 mb-2 rounded-md bg-neutral-900 px-2 py-1 text-xs whitespace-nowrap text-white shadow dark:bg-neutral-100 dark:text-neutral-900"
                    :style="at(active)"
                    aria-hidden="true"
                >
                    <strong class="font-semibold">{{
                        views(days[active].views)
                    }}</strong>
                    <span class="opacity-75">
                        &middot; {{ long(days[active].date) }}</span
                    >
                </span>
            </template>

            <!-- Read out while moving with the arrow keys. -->
            <span class="sr-only" aria-live="polite">
                <template v-if="active !== null">
                    {{ views(days[active].views) }},
                    {{ long(days[active].date) }}
                </template>
            </span>
        </div>

        <div
            class="mt-1.5 flex justify-between gap-2 text-[11px] text-neutral-500"
            aria-hidden="true"
        >
            <span>{{ short(days[0].date) }}</span>
            <span v-if="days.length > 14">{{
                short(days[Math.floor(last / 2)].date)
            }}</span>
            <span>Today</span>
        </div>

        <!-- The same numbers as a table, for screen readers. -->
        <table class="sr-only">
            <caption>
                {{
                    caption
                }}
            </caption>
            <tbody>
                <tr v-for="day in days" :key="day.date">
                    <th scope="row">{{ long(day.date) }}</th>
                    <td>{{ day.views }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
