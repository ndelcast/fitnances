<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import OnboardingWizard from '@/Components/OnboardingWizard.vue';

defineProps({
    initial: {
        type: Object,
        default: () => ({ startingBalance: null, annualRevenue: null, cuotaMonthly: null, monthlySalary: null }),
    },
});
</script>

<template>
    <Head title="Asistente anual" />

    <AppLayout title="Asistente anual">
        <div class="mx-auto max-w-2xl">
            <OnboardingWizard :initial="initial" />
        </div>
    </AppLayout>
</template>
