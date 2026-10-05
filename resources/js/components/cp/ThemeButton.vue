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
        class="cp-icon-btn"
        @click="next"
    >
        <CpIcon :name="icons[theme]" class="size-4" />
    </button>
</template>
