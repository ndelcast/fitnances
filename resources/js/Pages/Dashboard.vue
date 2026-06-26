<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatEuros, formatDate, formatShortDate } from '@/lib/format';

const props = defineProps({
    headline: { type: Number, required: true },
    cash: { type: Number, required: true },
    available: { type: Number, required: true },
    provisions: { type: Object, required: true },
    forecast: { type: Object, required: true },
    upcomingDeadlines: { type: Array, default: () => [] },
    renta: { type: Object, default: () => null },
});

const provisionLines = computed(() => [
    { label: 'IVA (Modelo 303)', value: props.provisions.iva, icon: 'pi pi-percentage' },
    { label: 'IRPF (Modelo 130)', value: props.provisions.irpf, icon: 'pi pi-file' },
]);

const headlinePositive = computed(() => props.headline >= 0);

const deadlineSeverity = (daysLeft) => {
    if (daysLeft <= 7) return 'danger';
    if (daysLeft <= 21) return 'warn';
    return 'info';
};
</script>

<template>
    <Head title="Resumen" />

    <AppLayout title="Resumen">
        <div class="mx-auto max-w-6xl space-y-6">
            <!-- Chiffre unique -->
            <Card class="overflow-hidden">
                <template #content>
                    <div class="flex flex-col items-start gap-2 p-2 md:p-4">
                        <span class="text-xs font-semibold uppercase tracking-wider text-surface-500">
                            Puedes sacar este mes
                        </span>
                        <span
                            class="text-5xl font-extrabold tracking-tight md:text-6xl"
                            :class="headlinePositive ? 'text-emerald-600' : 'text-red-600'"
                        >
                            {{ formatEuros(headline) }}
                        </span>
                        <span class="text-sm text-surface-500">
                            Una vez apartados IVA e IRPF, y cubiertos los cargos del mes.
                        </span>
                    </div>
                </template>
            </Card>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Disponible estimado -->
                <Card class="lg:col-span-2">
                    <template #title>
                        <div class="flex items-center justify-between">
                            <span>Disponible estimado</span>
                            <Tag :value="`Saldo: ${formatEuros(cash)}`" severity="secondary" />
                        </div>
                    </template>
                    <template #content>
                        <div class="mb-4 flex items-baseline gap-3">
                            <span class="text-3xl font-bold text-surface-900">{{ formatEuros(available) }}</span>
                        </div>
                        <p class="mb-3 text-sm text-surface-500">Apartado para Hacienda y Seguridad Social</p>
                        <ul class="space-y-2">
                            <li
                                v-for="line in provisionLines"
                                :key="line.label"
                                class="flex items-center justify-between border-b border-surface-100 pb-2 last:border-0"
                            >
                                <span class="flex items-center gap-2 text-sm text-surface-600">
                                    <i :class="line.icon" class="text-xs text-surface-400" />
                                    {{ line.label }}
                                </span>
                                <span class="font-medium text-surface-900">{{ formatEuros(line.value) }}</span>
                            </li>
                            <li class="flex items-center justify-between pt-1">
                                <span class="text-sm font-semibold text-surface-700">Total provisiones</span>
                                <span class="font-bold text-amber-600">{{ formatEuros(provisions.total) }}</span>
                            </li>
                        </ul>
                    </template>
                </Card>

                <!-- Próximas declaraciones -->
                <Card>
                    <template #title>Próximas declaraciones</template>
                    <template #content>
                        <ul v-if="upcomingDeadlines.length" class="space-y-3">
                            <li
                                v-for="d in upcomingDeadlines"
                                :key="d.modelo + d.date"
                                class="rounded-lg border border-surface-200 p-3"
                            >
                                <div class="mb-1 flex items-center justify-between">
                                    <span class="text-sm font-semibold text-surface-900">{{ d.modelo }}</span>
                                    <Tag :value="`${d.daysLeft} días`" :severity="deadlineSeverity(d.daysLeft)" />
                                </div>
                                <p class="text-xs text-surface-500">{{ formatShortDate(d.date) }} · {{ d.label }}</p>
                                <p class="mt-1 text-sm font-semibold text-surface-700">{{ formatEuros(d.amount) }}</p>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-surface-500">Sin declaraciones próximas.</p>
                    </template>
                </Card>
            </div>

            <!-- Renta anual estimada -->
            <Card v-if="renta">
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>Renta {{ renta.year }} — estimación</span>
                        <Tag :value="`Tramo marginal ${renta.marginalRate} %`" severity="warn" />
                    </div>
                </template>
                <template #content>
                    <p class="mb-4 text-sm text-surface-500">
                        Estimación del IRPF real que tendrás que pagar en la declaración de la renta, según el barème
                        progresivo (estado + autonómico general). El Modelo 130 ya cubre una parte ; queda lo siguiente
                        a aprovisionar.
                    </p>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-lg border border-violet-200 bg-violet-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-violet-700">IRPF Renta a pagar</p>
                            <p class="mt-1 text-2xl font-bold text-violet-700">{{ formatEuros(renta.rentaIrpf) }}</p>
                            <p class="mt-1 text-xs text-violet-600">
                                sobre {{ formatEuros(renta.baseImponible) }} de base imponible
                            </p>
                        </div>
                        <div class="rounded-lg bg-surface-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-surface-500">Ya cubierto</p>
                            <p class="mt-1 text-xl font-semibold text-surface-900">
                                {{ formatEuros(renta.modelo130Annual + renta.retentionsAnnual) }}
                            </p>
                            <p class="mt-1 text-xs text-surface-500">
                                {{ formatEuros(renta.modelo130Annual) }} Modelo 130 + {{ formatEuros(renta.retentionsAnnual) }} retenciones
                            </p>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-amber-700">Falta provisionar</p>
                            <p class="mt-1 text-2xl font-bold text-amber-700">{{ formatEuros(renta.restanteRenta) }}</p>
                            <p class="mt-1 text-xs text-amber-700">
                                ≈ {{ formatEuros(renta.monthlyProvision) }}/mes para llegar a la declaración
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 text-xs italic text-surface-400">
                        Cálculo aproximado : rendimiento neto de {{ formatEuros(renta.rendimientoNeto) }} − mínimo personal 5 550 €.
                        Ignora reducciones específicas (familia, discapacidad, etc.) y particularidades autonómicas.
                    </p>
                </template>
            </Card>

            <!-- Previsión 90 días -->
            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>Previsión 90 días</span>
                        <span class="text-sm font-normal text-surface-500">
                            {{ formatDate(forecast.from) }} → {{ formatDate(forecast.to) }}
                        </span>
                    </div>
                </template>
                <template #content>
                    <div class="mb-6 flex items-baseline gap-3">
                        <span
                            class="text-3xl font-bold"
                            :class="forecast.projectedBalance >= 0 ? 'text-surface-900' : 'text-red-600'"
                        >
                            {{ formatEuros(forecast.projectedBalance) }}
                        </span>
                        <span class="text-sm text-surface-400">saldo proyectado</span>
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-lg bg-surface-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-surface-500">Saldo actual</p>
                            <p class="mt-1 text-xl font-semibold text-surface-900">{{ formatEuros(forecast.startingCash) }}</p>
                        </div>
                        <div class="rounded-lg bg-emerald-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-emerald-700">Ingresos previstos</p>
                            <p class="mt-1 text-xl font-semibold text-emerald-700">+ {{ formatEuros(forecast.expectedIncome) }}</p>
                        </div>
                        <div class="rounded-lg bg-red-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-red-700">Cargos previstos</p>
                            <p class="mt-1 text-xl font-semibold text-red-700">− {{ formatEuros(forecast.projectedCharges) }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-2">
                        <Link href="/transactions">
                            <Button label="Añadir transacción" icon="pi pi-plus" size="small" />
                        </Link>
                        <Link href="/transactions?status=previsto">
                            <Button label="Ver previstos" icon="pi pi-clock" severity="secondary" size="small" />
                        </Link>
                    </div>
                </template>
            </Card>
        </div>
    </AppLayout>
</template>
