<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { cn, initials } from '@/lib/utils';

// The site's logo as chosen in the settings, or a letter badge without one.
// Like the public site, the logo background goes behind either.
const props = withDefaults(
    defineProps<{
        size?: 'sm' | 'lg';
    }>(),
    { size: 'sm' },
);

const page = usePage();
const logo = computed(() => page.props.logo);
const small = computed(() => props.size === 'sm');
</script>

<template>
    <span
        v-if="logo.url && logo.background"
        :class="
            cn(
                'grid shrink-0 place-items-center shadow-xs',
                small
                    ? 'h-7 min-w-7 rounded-lg p-1'
                    : 'h-10 min-w-10 rounded-xl p-1.5',
            )
        "
        :style="logo.background"
        aria-hidden="true"
    >
        <img
            :src="logo.url"
            alt=""
            :class="
                cn(
                    'w-auto object-contain',
                    small ? 'h-5 max-w-24' : 'h-7 max-w-36',
                )
            "
        />
    </span>
    <img
        v-else-if="logo.url"
        :src="logo.url"
        alt=""
        :class="
            cn(
                'w-auto shrink-0 object-contain',
                small ? 'h-7 max-w-28' : 'h-10 max-w-40',
            )
        "
    />
    <span
        v-else
        :class="
            cn(
                'grid shrink-0 place-items-center font-semibold shadow-xs',
                small
                    ? 'size-7 rounded-lg text-xs'
                    : 'size-10 rounded-xl text-sm',
                logo.background ? 'text-white' : 'bg-brand text-brand-fg',
            )
        "
        :style="logo.background ?? undefined"
        aria-hidden="true"
    >
        {{ initials(page.props.name) }}
    </span>
</template>
