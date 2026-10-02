<script setup lang="ts">
import { computed, ref } from 'vue';
import CpIcon from '@/components/cp/CpIcon.vue';
import type { IconName } from '@/components/cp/CpIcon.vue';
import { applyTheme, currentTheme, nextTheme, themeTitle } from '@/lib/theme';
import type { Theme } from '@/lib/theme';

// Steps through following the system, light and dark, like the public
// site's button, which shares the choice.
const icons: Record<Theme, IconName> = {
    system: 'monitor',
    light: 'sun',
    dark: 'moon',
};

const theme = ref<Theme>(
    typeof document === 'undefined' ? 'system' : currentTheme(),
);
const title = computed(() => themeTitle(theme.value));

const next = () => {
    theme.value = nextTheme(theme.value);
    applyTheme(theme.value);
};
</script>

<template>
    <button
        type="button"
        :title="title"
        :aria-label="title"
        class="grid size-7 place-items-center rounded-md text-neutral-500 transition-colors hover:bg-neutral-200/60 hover:text-neutral-900 focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
        @click="next"
    >
        <CpIcon :name="icons[theme]" class="size-4" />
    </button>
</template>
