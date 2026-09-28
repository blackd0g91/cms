<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CpLayout from '@/layouts/CpLayout.vue';

defineOptions({ layout: CpLayout });
</script>

<template>
    <Head title="Dashboard" />
    <h1 class="text-2xl font-semibold">Dashboard</h1>
    <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">
        Welcome back, {{ $page.props.auth.user.name }}.
    </p>
</template>
