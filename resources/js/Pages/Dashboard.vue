<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import { formatEuros, formatDate } from '@/lib/format';

const props = defineProps({
    user: { type: Object, required: true },
    headline: { type: Number, required: true },
    cash: { type: Number, required: true },
    available: { type: Number, required: true },
    provisions: { type: Object, required: true },
    forecast: { type: Object, required: true },
});

const provisionLines = computed(() => [
    { label: 'TVA à reverser', value: props.provisions.vat },
    { label: 'URSSAF', value: props.provisions.urssaf },
    { label: 'Impôt sur le revenu', value: props.provisions.incomeTax },
]);

const headlinePositive = computed(() => props.headline >= 0);
</script>

<template>
    <Head title="Tableau de bord" />

    <main class="min-h-full bg-surface-50 px-4 py-8 md:px-8">
        <div class="mx-auto max-w-5xl">
            <header class="mb-8">
                <p class="text-surface-500 text-sm">Bonjour {{ user.name }}</p>
                <h1 class="text-surface-900 text-2xl font-bold">Votre trésorerie</h1>
            </header>

            <!-- Le chiffre unique -->
            <Card class="mb-6 overflow-hidden">
                <template #content>
                    <div class="flex flex-col items-start gap-2 p-2">
                        <span class="text-surface-500 text-sm font-medium uppercase tracking-wide">
                            Vous pouvez vous verser ce mois-ci
                        </span>
                        <span
                            class="text-5xl font-extrabold tracking-tight md:text-6xl"
                            :class="headlinePositive ? 'text-emerald-600' : 'text-red-600'"
                        >
                            {{ formatEuros(headline) }}
                        </span>
                        <span class="text-surface-400 text-sm">
                            Une fois l'URSSAF, la TVA et l'impôt mis de côté, et les charges du mois couvertes.
                        </span>
                    </div>
                </template>
            </Card>

            <div class="grid gap-6 md:grid-cols-2">
                <!-- Réellement disponible + provisions -->
                <Card>
                    <template #title>Réellement disponible</template>
                    <template #content>
                        <div class="mb-4 flex items-baseline gap-3">
                            <span class="text-surface-900 text-3xl font-bold">{{ formatEuros(available) }}</span>
                            <Tag :value="`Solde : ${formatEuros(cash)}`" severity="secondary" />
                        </div>
                        <p class="text-surface-500 mb-3 text-sm">Mis de côté pour l'État</p>
                        <ul class="space-y-2">
                            <li
                                v-for="line in provisionLines"
                                :key="line.label"
                                class="flex items-center justify-between border-b border-surface-100 pb-2 last:border-0"
                            >
                                <span class="text-surface-600 text-sm">{{ line.label }}</span>
                                <span class="text-surface-900 font-medium">{{ formatEuros(line.value) }}</span>
                            </li>
                            <li class="flex items-center justify-between pt-1">
                                <span class="text-surface-700 text-sm font-semibold">Total provisions</span>
                                <span class="font-bold text-amber-600">{{ formatEuros(provisions.total) }}</span>
                            </li>
                        </ul>
                    </template>
                </Card>

                <!-- Prévisionnel 90 jours -->
                <Card>
                    <template #title>Prévisionnel 90 jours</template>
                    <template #subtitle>
                        {{ formatDate(forecast.from) }} → {{ formatDate(forecast.to) }}
                    </template>
                    <template #content>
                        <div class="mb-4 flex items-baseline gap-2">
                            <span
                                class="text-3xl font-bold"
                                :class="forecast.projectedBalance >= 0 ? 'text-surface-900' : 'text-red-600'"
                            >
                                {{ formatEuros(forecast.projectedBalance) }}
                            </span>
                            <span class="text-surface-400 text-sm">solde projeté</span>
                        </div>
                        <ul class="space-y-2">
                            <li class="flex items-center justify-between border-b border-surface-100 pb-2">
                                <span class="text-surface-600 text-sm">Solde actuel</span>
                                <span class="text-surface-900 font-medium">{{ formatEuros(forecast.startingCash) }}</span>
                            </li>
                            <li class="flex items-center justify-between border-b border-surface-100 pb-2">
                                <span class="text-surface-600 text-sm">Factures à venir</span>
                                <span class="font-medium text-emerald-600">+ {{ formatEuros(forecast.expectedIncome) }}</span>
                            </li>
                            <li class="flex items-center justify-between">
                                <span class="text-surface-600 text-sm">Charges à venir</span>
                                <span class="font-medium text-red-600">− {{ formatEuros(forecast.projectedCharges) }}</span>
                            </li>
                        </ul>
                    </template>
                </Card>
            </div>
        </div>
    </main>
</template>
