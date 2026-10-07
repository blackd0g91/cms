<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ContentCheckup from '@/components/cp/ContentCheckup.vue';
import type { Check } from '@/components/cp/ContentCheckup.vue';
import PageHeader from '@/components/cp/PageHeader.vue';
import SystemStatus from '@/components/cp/SystemStatus.vue';
import type { SystemReport } from '@/components/cp/SystemStatus.vue';
import CpLayout from '@/layouts/CpLayout.vue';

defineOptions({ layout: CpLayout });

defineProps<{
    checkup: Check[];
    // Only for admins.
    system: SystemReport | null;
}>();
</script>

<template>
    <Head title="Health" />
    <PageHeader title="Health" />

    <SystemStatus v-if="system" :report="system" class="mb-6" />

    <ContentCheckup :checks="checkup" />
</template>
