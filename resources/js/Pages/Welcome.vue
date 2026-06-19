<script setup>
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';

defineProps({
    name: { type: String, default: 'Fitnances' },
});
</script>

<template>
    <Head title="Bienvenue" />

    <main class="flex min-h-full items-center justify-center bg-surface-50 p-6">
        <Card class="w-full max-w-md">
            <template #title>{{ name }}</template>
            <template #subtitle>
                Combien je peux me verser ce mois-ci ?
            </template>
            <template #content>
                <p class="text-surface-600 leading-relaxed">
                    Stack opérationnelle : Laravel + Inertia + Vue 3 + PrimeVue.
                    On peut commencer le MVP.
                </p>
            </template>
            <template #footer>
                <Button label="C'est parti" />
            </template>
        </Card>
    </main>
</template>
