<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Button from 'primevue/button';

defineProps({
    name: { type: String, default: 'Fitnances' },
});
</script>

<template>
    <Head title="Bienvenue" />

    <main class="flex min-h-full items-center justify-center bg-surface-50 p-6">
        <Card class="w-full max-w-md">
            <template #title>{{ name }}</template>
            <template #subtitle>Combien je peux me verser ce mois-ci ?</template>
            <template #content>
                <p class="text-surface-600 leading-relaxed">
                    Le pilotage de trésorerie pour freelances. Sachez en un coup d'œil
                    ce que vous pouvez vraiment vous verser, une fois l'URSSAF, la TVA
                    et l'impôt mis de côté.
                </p>
            </template>
            <template #footer>
                <div class="flex flex-col gap-3">
                    <Link href="/register">
                        <Button label="Démarrer l'essai gratuit" fluid />
                    </Link>
                    <Link href="/login" class="text-center text-sm text-surface-500 hover:underline">
                        J'ai déjà un compte
                    </Link>
                </div>
            </template>
        </Card>
    </main>
</template>
