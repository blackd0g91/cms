<script setup lang="ts">
import SiteMark from '@/components/cp/SiteMark.vue';

// The frame of the pages for logging in, with the site's mark and name.
defineProps<{
    // Below the site's name.
    subtitle?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-neutral-50 px-4 py-10 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <div class="w-full max-w-sm">
            <div class="mb-6 flex flex-col items-center gap-3 text-center">
                <SiteMark size="lg" />
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">
                        {{ $page.props.name }}
                    </h1>
                    <p v-if="subtitle" class="mt-1 text-sm text-neutral-500">
                        {{ subtitle }}
                    </p>
                </div>
            </div>

            <slot />
        </div>
    </div>
</template>
