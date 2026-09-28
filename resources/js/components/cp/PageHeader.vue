<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    title: string;
    back?: { label: string; href: string };
}>();
</script>

<template>
    <div class="mb-6">
        <Link
            v-if="back"
            :href="back.href"
            class="mb-2 inline-block text-sm text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200"
        >
            &larr; {{ back.label }}
        </Link>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">{{ title }}</h1>
            <div class="flex items-center gap-2">
                <slot />
            </div>
        </div>
    </div>
</template>
