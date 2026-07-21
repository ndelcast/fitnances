<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import AppLayout from '@/Layouts/AppLayout.vue';
import HealthRing from '@/Components/HealthRing.vue';
import { formatEuros, formatDate, formatShortDate } from '@/lib/format';

const props = defineProps({
    user: { type: Object, default: () => ({ name: '' }) },
    money: { type: Object, required: true },
    provisions: { type: Object, required: true },
    health: { type: Object, required: true },
    sales: { type: Object, required: true },
    renta: { type: Object, default: () => null },
    forecast: { type: Object, required: true },
    upcomingDeadlines: { type: Array, default: () => [] },
});

/* ---------------------------------------------------------------
 * Hero : le chiffre que l'utilisateur vient chercher.
 * saldo actual → part réservée Hacienda → « realmente tuyo ».
 * --------------------------------------------------------------- */
const heroFmt = new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
});

const saldo = computed(() => props.forecast.startingCash);
const reallyYours = computed(() => saldo.value - props.money.provisionsTotal);

const todayLabel = new Date().toLocaleDateString('es-ES', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});

const firstName = computed(() => (props.user.name || '').split(' ')[0]);

const colchonMetric = computed(() => props.health.metrics.find((m) => m.key === 'colchon'));

const scoreDotClass = computed(() => {
    const s = props.health.statusSeverity;
    if (s === 'success') return 'bg-emerald-500';
    if (s === 'warn') return 'bg-amber-500';
    return 'bg-red-500';
});

const deadlineTone = (daysLeft) => {
    if (daysLeft <= 7) return 'text-red-700 bg-red-50 border-red-200';
    if (daysLeft <= 21) return 'text-amber-700 bg-amber-50 border-amber-200';
    return 'text-surface-600 bg-surface-50 border-surface-200';
};

const provisionLines = computed(() => [props.provisions.iva, props.provisions.irpfModelo130, props.provisions.rentaAccrued]);
</script>

<template>
    <Head title="Resumen" />

    <AppLayout title="Resumen">
        <div class="mx-auto max-w-6xl space-y-6">
            <!-- ============ HERO : saldo actual ============ -->
            <section class="pb-2 pt-4">
                <p class="text-sm capitalize text-surface-500">{{ todayLabel }}</p>

                <div class="mt-4 flex flex-wrap items-end justify-between gap-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-surface-400">Saldo actual</p>
                        <p class="font-display mt-1 text-6xl leading-none tracking-tight text-surface-900 tabular-nums md:text-7xl">
                            {{ heroFmt.format(saldo) }}
                        </p>
                        <p class="mt-3 text-sm text-surface-500">
                            <span class="font-semibold text-violet-700">{{ formatEuros(money.provisionsTotal) }}</span>
                            reservados para Hacienda ·
                            <span class="font-semibold" :class="reallyYours >= 0 ? 'text-surface-900' : 'text-red-600'">
                                {{ formatEuros(reallyYours) }}
                            </span>
                            realmente {{ firstName ? 'tuyos' : 'disponibles' }}
                        </p>
                    </div>

                    <!-- Chips de contexte -->
                    <div class="flex flex-wrap gap-2.5">
                        <div class="rounded-xl border border-surface-200 bg-white px-4 py-2.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-surface-400">Colchón</p>
                            <p class="mt-0.5 text-sm font-semibold tabular-nums text-surface-900">
                                {{ colchonMetric?.centerText ?? '—' }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-surface-200 bg-white px-4 py-2.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-surface-400">En 90 días</p>
                            <p
                                class="mt-0.5 text-sm font-semibold tabular-nums"
                                :class="forecast.projectedBalance >= 0 ? 'text-surface-900' : 'text-red-600'"
                            >
                                {{ formatEuros(forecast.projectedBalance) }}
                            </p>
                        </div>
                        <component
                            :is="sales.pendientesCount > 0 ? Link : 'div'"
                            :href="sales.pendientesCount > 0 ? '/movements?kind=income&paid=false' : undefined"
                            class="rounded-xl border px-4 py-2.5 transition-colors"
                            :class="
                                sales.pendientesCount > 0
                                    ? 'cursor-pointer border-amber-200 bg-amber-50 hover:border-amber-300'
                                    : 'border-surface-200 bg-white'
                            "
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wider"
                                :class="sales.pendientesCount > 0 ? 'text-amber-600' : 'text-surface-400'"
                            >
                                Pendiente de cobro
                                <i v-if="sales.pendientesCount > 0" class="pi pi-arrow-up-right ml-0.5 text-[8px]" />
                            </p>
                            <p
                                class="mt-0.5 text-sm font-semibold tabular-nums"
                                :class="sales.pendientesCount > 0 ? 'text-amber-700' : 'text-surface-900'"
                            >
                                {{ formatEuros(sales.pendientesAmount) }}
                                <span v-if="sales.pendientesCount > 0" class="font-normal text-amber-600">
                                    · {{ sales.pendientesCount }} factura{{ sales.pendientesCount > 1 ? 's' : '' }}
                                </span>
                            </p>
                        </component>
                    </div>
                </div>
            </section>

            <!-- ============ SALUD DEL NEGOCIO ============ -->
            <section class="rounded-2xl border border-surface-200 bg-white p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-surface-900">Salud de tu negocio</h2>
                    <div class="flex items-center gap-2 text-sm text-surface-600">
                        <span class="h-2 w-2 rounded-full" :class="scoreDotClass" />
                        <span class="font-semibold tabular-nums text-surface-900">{{ health.globalScore }}</span>
                        <span class="text-surface-400">/ 100</span>
                        <span>· {{ health.statusLabel }}</span>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 md:grid-cols-3">
                    <div v-for="metric in health.metrics" :key="metric.key" class="flex flex-col items-center text-center">
                        <HealthRing :value="metric.score" :centerText="metric.centerText" :color="metric.color" />
                        <p class="mt-3 text-sm font-semibold text-surface-900">{{ metric.label }}</p>
                        <p class="mt-0.5 text-xs text-surface-500">{{ metric.subline }}</p>
                        <p class="mt-2 max-w-[260px] cursor-help text-xs text-surface-400" v-tooltip.bottom="metric.hint">
                            <i class="pi pi-info-circle mr-1 text-[10px]" />
                            ¿Qué significa?
                        </p>
                    </div>
                </div>

                <p v-if="sales.dsoSampleSize > 0" class="mt-6 border-t border-surface-100 pt-4 text-xs text-surface-500">
                    Cobras de media en
                    <span class="font-semibold text-surface-900">{{ sales.avgDsoDays }} días</span>
                    <span class="text-surface-400">· sobre {{ sales.dsoSampleSize }} factura{{ sales.dsoSampleSize > 1 ? 's' : '' }} cobrada{{ sales.dsoSampleSize > 1 ? 's' : '' }}</span>
                    · el {{ sales.recurrenciaPct }} % de tus ingresos ({{ formatEuros(sales.recurrenciaAmount) }}) es recurrente.
                </p>
            </section>

            <!-- ============ HACIENDA ============ -->
            <div class="grid gap-6 lg:grid-cols-5">
                <!-- Lo que debes apartar -->
                <section class="rounded-2xl border border-surface-200 bg-white p-6 lg:col-span-3">
                    <div class="flex items-baseline justify-between">
                        <h2 class="text-base font-semibold text-surface-900">Lo que debes apartar</h2>
                        <p class="font-display text-2xl tracking-tight text-violet-700 tabular-nums">
                            {{ formatEuros(money.provisionsTotal) }}
                        </p>
                    </div>
                    <p class="mt-1 text-xs text-surface-500">
                        Para tu próxima declaración <strong class="font-semibold text-surface-700">{{ provisions.quarterLabel }}</strong>
                        · vence el <strong class="font-semibold text-surface-700">{{ formatShortDate(provisions.quarterDue) }}</strong>
                    </p>

                    <ul class="mt-5 space-y-3">
                        <li
                            v-for="line in provisionLines"
                            :key="line.label"
                            class="flex items-center justify-between border-b border-surface-100 pb-3 text-sm last:border-0 last:pb-0"
                        >
                            <span class="text-surface-600">{{ line.label }}</span>
                            <span class="font-semibold tabular-nums text-surface-900">{{ formatEuros(line.amount) }}</span>
                        </li>
                    </ul>

                    <p class="mt-4 text-xs text-surface-400">
                        Renta se aprovisiona
                        <strong class="font-semibold text-surface-600">{{ formatEuros(provisions.rentaAccrued.amount / Math.max(1, new Date().getMonth() + 1)) }}/mes</strong>
                        hasta la declaración de mayo (tramo {{ provisions.rentaAccrued.marginalRate }} %).
                    </p>
                </section>

                <!-- Próximas declaraciones -->
                <section class="rounded-2xl border border-surface-200 bg-white p-6 lg:col-span-2">
                    <h2 class="text-base font-semibold text-surface-900">Próximas declaraciones</h2>
                    <ul v-if="upcomingDeadlines.length" class="mt-5 space-y-3">
                        <li
                            v-for="d in upcomingDeadlines"
                            :key="d.modelo + d.date"
                            class="flex items-center justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-surface-900">{{ d.modelo }}</p>
                                <p class="truncate text-xs text-surface-500">{{ formatShortDate(d.date) }} · {{ d.label }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="text-sm font-semibold tabular-nums text-surface-900">{{ formatEuros(d.amount) }}</span>
                                <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold tabular-nums" :class="deadlineTone(d.daysLeft)">
                                    {{ d.daysLeft }} d
                                </span>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="mt-5 text-sm text-surface-500">Sin declaraciones próximas.</p>
                </section>
            </div>

            <!-- ============ RENTA ============ -->
            <section v-if="renta" class="rounded-2xl border border-surface-200 bg-white p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-surface-900">Renta {{ renta.year }} · estimación</h2>
                    <span class="rounded-full border border-surface-200 bg-surface-50 px-2.5 py-0.5 text-xs font-semibold text-surface-600">
                        Tramo marginal {{ renta.marginalRate }} %
                    </span>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-surface-200 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-surface-400">IRPF Renta a pagar</p>
                        <p class="font-display mt-1 text-3xl tracking-tight text-violet-700 tabular-nums">{{ formatEuros(renta.rentaIrpf) }}</p>
                        <p class="mt-1 text-xs text-surface-500">sobre {{ formatEuros(renta.baseImponible) }} de base imponible</p>
                    </div>
                    <div class="rounded-xl border border-surface-200 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-surface-400">Ya cubierto</p>
                        <p class="font-display mt-1 text-3xl tracking-tight text-surface-900 tabular-nums">
                            {{ formatEuros(renta.modelo130Annual + renta.retentionsAnnual) }}
                        </p>
                        <p class="mt-1 text-xs text-surface-500">
                            {{ formatEuros(renta.modelo130Annual) }} Modelo 130 + {{ formatEuros(renta.retentionsAnnual) }} retenciones
                        </p>
                    </div>
                    <div v-if="renta.expectedRefund > 0" class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Hacienda te devolverá</p>
                        <p class="font-display mt-1 text-3xl tracking-tight text-emerald-700 tabular-nums">+ {{ formatEuros(renta.expectedRefund) }}</p>
                        <p class="mt-1 text-xs text-emerald-700">Has avanzado más de lo necesario con Modelo 130 + retenciones.</p>
                    </div>
                    <div v-else class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-amber-600">Falta provisionar</p>
                        <p class="font-display mt-1 text-3xl tracking-tight text-amber-700 tabular-nums">{{ formatEuros(renta.restanteRenta) }}</p>
                        <p class="mt-1 text-xs text-amber-700">≈ {{ formatEuros(renta.monthlyProvision) }}/mes hasta la declaración</p>
                    </div>
                </div>
            </section>

            <!-- ============ PREVISIÓN 90 DÍAS ============ -->
            <section class="rounded-2xl border border-surface-200 bg-white p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-surface-900">Previsión 90 días</h2>
                    <span class="text-xs text-surface-400">{{ formatDate(forecast.from) }} → {{ formatDate(forecast.to) }}</span>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-surface-200 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-surface-400">Saldo actual</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-surface-900">{{ formatEuros(forecast.startingCash) }}</p>
                    </div>
                    <div class="rounded-xl border border-surface-200 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-surface-400">Ingresos previstos</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-emerald-600">+ {{ formatEuros(forecast.expectedIncome) }}</p>
                    </div>
                    <div class="rounded-xl border border-surface-200 p-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-surface-400">Cargos previstos</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-red-600">− {{ formatEuros(forecast.projectedCharges) }}</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-surface-100 pt-4">
                    <p class="text-sm text-surface-500">
                        Saldo proyectado:
                        <span class="font-semibold tabular-nums" :class="forecast.projectedBalance >= 0 ? 'text-surface-900' : 'text-red-600'">
                            {{ formatEuros(forecast.projectedBalance) }}
                        </span>
                    </p>
                    <div class="flex gap-2">
                        <Link href="/movements">
                            <Button label="Añadir movimiento" icon="pi pi-plus" size="small" />
                        </Link>
                        <Link href="/movements?status=previsto">
                            <Button label="Ver previstos" icon="pi pi-clock" severity="secondary" outlined size="small" />
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
