<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

export type Check = {
    key: string;
    label: string;
    hint: string;
    count: number;
    items: { title: string; detail: string | null; href: string }[];
};

const props = defineProps<{
    checks: Check[];
}>();

const open = computed(() => props.checks.filter((check) => check.count > 0));
</script>

<template>
    <section class="cp-card">
        <h2
            class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 font-semibold dark:border-neutral-800"
        >
            Content checkup
            <span
                v-if="open.length"
                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                {{ open.length }} to look at
            </span>
        </h2>

        <p v-if="open.length === 0" class="p-4 text-sm text-neutral-500">
            ✨ Everything looks good. No missing images, alt text, tags or
            thumbnails, and no forgotten drafts.
        </p>

        <ul v-else class="divide-y divide-neutral-200 dark:divide-neutral-800">
            <li v-for="check in open" :key="check.key">
                <details class="group">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800/50"
                    >
                        <span class="flex items-center gap-2">
                            <span
                                class="text-neutral-400 transition group-open:rotate-90"
                                aria-hidden="true"
                                >›</span
                            >
                            {{ check.label }}
                        </span>
                        <span
                            class="min-w-6 rounded-full bg-neutral-100 px-2 py-0.5 text-center text-xs font-medium dark:bg-neutral-800"
                        >
                            {{ check.count }}
                        </span>
                    </summary>
                    <div class="px-4 pb-3 pl-9">
                        <p class="mb-2 text-xs text-neutral-500">
                            {{ check.hint }}
                        </p>
                        <ul class="space-y-1 text-sm">
                            <li
                                v-for="(item, i) in check.items"
                                :key="i"
                                class="flex items-baseline justify-between gap-3"
                            >
                                <Link
                                    :href="item.href"
                                    class="truncate hover:underline"
                                >
                                    {{ item.title }}
                                </Link>
                                <span
                                    v-if="item.detail"
                                    class="shrink-0 text-xs text-neutral-500"
                                >
                                    {{ item.detail }}
                                </span>
                            </li>
                        </ul>
                        <p
                            v-if="check.count > check.items.length"
                            class="mt-1 text-xs text-neutral-500"
                        >
                            and {{ check.count - check.items.length }} more
                        </p>
                    </div>
                </details>
            </li>
        </ul>
    </section>
</template>
