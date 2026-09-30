<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { inject } from 'vue';
import { openSidebarKey } from '@/lib/sidebar';

defineProps<{
    title: string;
    back?: { label: string; href: string };
}>();

const openSidebar = inject(openSidebarKey, null);
</script>

<template>
    <!-- Stays at the top while the page scrolls, lined up with the
         sidebar's top row. Its actions wrap below when the title needs the
         room. -->
    <header
        class="sticky top-0 z-20 -mx-6 -mt-6 mb-6 flex min-h-14 flex-wrap items-center gap-x-3 gap-y-2 border-b border-neutral-200 bg-white px-6 py-2 dark:border-neutral-800 dark:bg-neutral-900"
    >
        <button
            v-if="openSidebar"
            type="button"
            class="-ml-2 rounded-md px-2 py-1 text-sm hover:bg-neutral-100 md:hidden dark:hover:bg-neutral-800"
            @click="openSidebar"
        >
            Menu
        </button>

        <div class="flex min-w-40 flex-1 items-center gap-2">
            <template v-if="back">
                <Link
                    :href="back.href"
                    class="shrink-0 text-sm text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200"
                >
                    {{ back.label }}
                </Link>
                <span
                    class="text-neutral-300 dark:text-neutral-700"
                    aria-hidden="true"
                    >/</span
                >
            </template>
            <h1 class="truncate text-lg font-semibold">{{ title }}</h1>
        </div>

        <div v-if="$slots.default" class="flex shrink-0 items-center gap-2">
            <slot />
        </div>
    </header>
</template>
