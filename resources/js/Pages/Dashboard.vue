<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import AppLayout from '@/Layouts/AppLayout.vue';
import HealthRing from '@/Components/HealthRing.vue';
import { formatEuros, formatDate, formatShortDate } from '@/lib/format';

const props = defineProps({
    money: { type: Object, required: true },
    provisions: { type: Object, required: true },
    health: { type: Object, required: true },
    sales: { type: Object, required: true },
    renta: { type: Object, default: () => null },
    forecast: { type: Object, required: true },
    upcomingDeadlines: { type: Array, default: () => [] },
});

const scoreSeverityClass = computed(() => {
    const s = props.health.statusSeverity;
    if (s === 'success') return 'text-emerald-600 bg-emerald-50 ring-emerald-200';
    if (s === 'warn') return 'text-amber-700 bg-amber-50 ring-amber-200';
    return 'text-red-700 bg-red-50 ring-red-200';
});

const deadlineSeverity = (daysLeft) => {
    if (daysLeft <= 7) return 'danger';
    if (daysLeft <= 21) return 'warn';
    return 'info';
};

const provisionLines = computed(() => [props.provisions.iva, props.provisions.irpfModelo130, props.provisions.rentaAccrued]);
</script>

<template>
    <Head title="Resumen" />

    <AppLayout title="Resumen">
        <div class="mx-auto max-w-6xl space-y-6">
            <!-- HEALTH RINGS -->
            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>Salud de tu negocio</span>
                        <div
                            class="flex items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold ring-1"
                            :class="scoreSeverityClass"
                        >
                            <span class="text-base font-bold tabular-nums">{{ health.globalScore }}</span>
                            <span>/ 100 · {{ health.statusLabel }}</span>
                        </div>
                    </div>
                </template>
                <template #content>
                    <div class="grid gap-6 md:grid-cols-3">
                        <div
                            v-for="metric in health.metrics"
                            :key="metric.key"
                            class="flex flex-col items-center text-center"
                        >
                            <HealthRing :value="metric.score" :centerText="metric.centerText" :color="metric.color" />
                            <p class="mt-3 text-sm font-semibold text-surface-900">{{ metric.label }}</p>
                            <p class="mt-0.5 text-xs text-surface-500">{{ metric.subline }}</p>
                            <p
                                class="mt-2 max-w-[260px] cursor-help text-xs leading-relaxed text-surface-400"
                                v-tooltip.bottom="metric.hint"
                            >
                                <i class="pi pi-info-circle mr-1 text-[10px]" />
                                ¿Qué significa?
                            </p>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Salud comercial : recurrencia + DSO + pendientes -->
            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>Salud comercial</span>
                        <Tag
                            v-if="sales.pendientesCount > 0"
                            :value="`${sales.pendientesCount} pendiente${sales.pendientesCount > 1 ? 's' : ''}`"
                            severity="warn"
                        />
                    </div>
                </template>
                <template #content>
                    <div class="grid gap-4 md:grid-cols-3">
                        <!-- Recurrencia -->
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-emerald-700">Recurrencia</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-700">{{ sales.recurrenciaPct }} %</p>
                            <p class="mt-1 text-xs text-emerald-700">
                                <span class="font-semibold">{{ formatEuros(sales.recurrenciaAmount) }}</span>
                                de {{ formatEuros(sales.totalAnnualIncome) }} previstos
                            </p>
                        </div>

                        <!-- DSO -->
                        <div class="rounded-lg border border-sky-200 bg-sky-50 p-4">
                            <p class="text-xs uppercase tracking-wider text-sky-700">DSO · días de cobro</p>
                            <p class="mt-1 text-2xl font-bold text-sky-700">
                                {{ sales.avgDsoDays !== null ? sales.avgDsoDays + ' días' : '—' }}
                            </p>
                            <p class="mt-1 text-xs text-sky-700">
                                <span v-if="sales.dsoSampleSize > 0">media sobre {{ sales.dsoSampleSize }} factura{{ sales.dsoSampleSize > 1 ? 's' : '' }} cobrada{{ sales.dsoSampleSize > 1 ? 's' : '' }}</span>
                                <span v-else>aún sin facturas cobradas con fecha de emisión</span>
                            </p>
                        </div>

                        <!-- Pendientes (cliquable si > 0) -->
                        <component
                            :is="sales.pendientesCount > 0 ? Link : 'div'"
                            :href="sales.pendientesCount > 0 ? '/movements?kind=income&paid=false' : undefined"
                            class="block rounded-lg border p-4 transition-all"
                            :class="[
                                sales.pendientesCount > 0
                                    ? 'border-amber-200 bg-amber-50 hover:border-amber-400 hover:bg-amber-100 hover:shadow-sm cursor-pointer'
                                    : 'border-surface-200 bg-surface-50',
                            ]"
                        >
                            <p
                                class="flex items-center justify-between text-xs uppercase tracking-wider"
                                :class="sales.pendientesCount > 0 ? 'text-amber-700' : 'text-surface-500'"
                            >
                                <span>Pendientes de cobro</span>
                                <i v-if="sales.pendientesCount > 0" class="pi pi-arrow-up-right text-[10px]" />
                            </p>
                            <p
                                class="mt-1 text-2xl font-bold"
                                :class="sales.pendientesCount > 0 ? 'text-amber-700' : 'text-surface-900'"
                            >
                                {{ formatEuros(sales.pendientesAmount) }}
                            </p>
                            <p
                                class="mt-1 text-xs"
                                :class="sales.pendientesCount > 0 ? 'text-amber-700' : 'text-surface-500'"
                            >
                                {{ sales.pendientesCount }} factura{{ sales.pendientesCount !== 1 ? 's' : '' }} emitida{{ sales.pendientesCount !== 1 ? 's' : '' }} sin cobrar
                            </p>
                        </component>
                    </div>
                </template>
            </Card>

            <!-- Lo que debes apartar -->
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <template #title>
                        <div class="flex items-center justify-between">
                            <span>Lo que debes apartar</span>
                            <Tag :value="formatEuros(money.provisionsTotal)" severity="warn" />
                        </div>
                    </template>
                    <template #content>
                        <p class="mb-3 text-xs text-surface-500">
                            Para tu próxima declaración <strong class="text-surface-700">{{ provisions.quarterLabel }}</strong>
                            · vence el <strong class="text-surface-700">{{ formatShortDate(provisions.quarterDue) }}</strong>.
                        </p>
                        <ul class="space-y-3">
                            <li
                                v-for="line in provisionLines"
                                :key="line.label"
                                class="flex items-center justify-between border-b border-surface-100 pb-2 last:border-0 last:pb-0"
                            >
                                <span class="text-sm text-surface-600">{{ line.label }}</span>
                                <span class="font-semibold tabular-nums text-surface-900">{{ formatEuros(line.amount) }}</span>
                            </li>
                        </ul>
                        <p class="mt-3 text-xs italic text-surface-400">
                            Renta se aprovisiona mensualmente <strong>{{ formatEuros(provisions.rentaAccrued.amount / Math.max(1, new Date().getMonth() + 1)) }}/mes</strong>
                            hasta la declaración de mayo (tramo {{ provisions.rentaAccrued.marginalRate }} %).
                        </p>
                    </template>
                </Card>
            </div>

            <!-- Renta annuelle estimée -->
            <Card v-if="renta">
                <template #title>
                    <div class="flex items-center justify-between">
                        <span>Renta {{ renta.year }} — estimación</span>
                        <Tag :value="`Tramo marginal ${renta.marginalRate} %`" severity="warn" />
                    </div>
                </template>
                <template #content>
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
                        <div
                            v-if="renta.expectedRefund > 0"
                            class="rounded-lg border border-emerald-200 bg-emerald-50 p-4"
                        >
                            <p class="text-xs uppercase tracking-wider text-emerald-700">Hacienda te devolverá</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-700">+ {{ formatEuros(renta.expectedRefund) }}</p>
                            <p class="mt-1 text-xs text-emerald-700">
                                Has avanzado más de lo necesario con Modelo 130 + retenciones.
                            </p>
                        </div>
                        <div
                            v-else
                            class="rounded-lg border border-amber-200 bg-amber-50 p-4"
                        >
                            <p class="text-xs uppercase tracking-wider text-amber-700">Falta provisionar</p>
                            <p class="mt-1 text-2xl font-bold text-amber-700">{{ formatEuros(renta.restanteRenta) }}</p>
                            <p class="mt-1 text-xs text-amber-700">
                                ≈ {{ formatEuros(renta.monthlyProvision) }}/mes hasta la declaración
                            </p>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Próximas declaraciones + Previsión 90 días -->
            <div class="grid gap-6 lg:grid-cols-3">
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

                <Card class="lg:col-span-2">
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
                            <div class="rounded-lg bg-surface-50 p-3">
                                <p class="text-xs uppercase tracking-wider text-surface-500">Saldo actual</p>
                                <p class="mt-1 text-lg font-semibold text-surface-900">{{ formatEuros(forecast.startingCash) }}</p>
                            </div>
                            <div class="rounded-lg bg-emerald-50 p-3">
                                <p class="text-xs uppercase tracking-wider text-emerald-700">Ingresos previstos</p>
                                <p class="mt-1 text-lg font-semibold text-emerald-700">+ {{ formatEuros(forecast.expectedIncome) }}</p>
                            </div>
                            <div class="rounded-lg bg-red-50 p-3">
                                <p class="text-xs uppercase tracking-wider text-red-700">Cargos previstos</p>
                                <p class="mt-1 text-lg font-semibold text-red-700">− {{ formatEuros(forecast.projectedCharges) }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Link href="/movements">
                                <Button label="Añadir movimiento" icon="pi pi-plus" size="small" />
                            </Link>
                            <Link href="/movements?status=previsto">
                                <Button label="Ver previstos" icon="pi pi-clock" severity="secondary" size="small" />
                            </Link>
                        </div>
                    </template>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
