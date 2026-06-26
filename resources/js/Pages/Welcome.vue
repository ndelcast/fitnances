<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

defineProps({
    name: { type: String, default: 'Fitnances' },
});

const benefits = [
    {
        icon: 'pi pi-percentage',
        accent: 'amber',
        title: 'Provisiones automáticas',
        body: 'IVA (Modelo 303), IRPF (Modelo 130) y cuota de autónomos calculados sobre cada factura. Sin hojas de cálculo, sin sorpresas en abril.',
    },
    {
        icon: 'pi pi-table',
        accent: 'emerald',
        title: 'Flujo de caja anual editable',
        body: 'Los 12 meses delante de ti, celda por celda. Edita, arrastra para copiar y mira el saldo acumulado en tiempo real.',
    },
    {
        icon: 'pi pi-wallet',
        accent: 'violet',
        title: 'Salario sostenible',
        body: 'Te decimos cuánto puedes pagarte cada mes una vez cubiertos impuestos y cargos fijos, sin riesgo de vaciar la cuenta.',
    },
];

const accentClass = (accent) =>
    ({
        amber: 'bg-amber-50 text-amber-700 ring-amber-100',
        emerald: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
        violet: 'bg-violet-50 text-violet-700 ring-violet-100',
    })[accent];

const steps = [
    { n: '01', title: 'Cuéntanos tu año', body: 'Facturación prevista, cuota mensual y cuánto quieres pagarte. Tres preguntas, una vez al año.' },
    { n: '02', title: 'Anota lo que pasa', body: 'Ingresos cobrados, gastos pagados, cargos recurrentes. Categorizado y fechado en segundos.' },
    { n: '03', title: 'Mira tu dinero real', body: 'El Dashboard responde la única pregunta que importa: ¿cuánto puedo sacar este mes?' },
];
</script>

<template>
    <Head title="Pilotaje de tesorería para autónomos" />

    <div class="min-h-full bg-surface-50 text-surface-900">
        <!-- Top bar -->
        <header class="border-b border-surface-200/60 bg-white/80 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white">
                        <i class="pi pi-chart-line text-sm" />
                    </div>
                    <span class="text-base font-semibold tracking-tight">{{ name }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <Link href="/login">
                        <Button label="Entrar" severity="secondary" text size="small" />
                    </Link>
                    <Link href="/register">
                        <Button label="Probar gratis" size="small" />
                    </Link>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0 -z-10">
                <div class="absolute -top-32 left-1/2 h-96 w-[1100px] -translate-x-1/2 rounded-full bg-emerald-200/40 blur-3xl" />
                <div class="absolute -bottom-24 right-0 h-72 w-72 rounded-full bg-violet-200/30 blur-3xl" />
            </div>

            <div class="mx-auto grid max-w-6xl gap-12 px-6 py-20 lg:grid-cols-[1.05fr_1fr] lg:items-center lg:py-28">
                <div>
                    <Tag value="Para autónomos en España" severity="secondary" rounded class="!bg-white !text-surface-700 !ring-1 !ring-surface-200" />

                    <h1 class="mt-5 text-4xl font-extrabold leading-[1.05] tracking-tight text-surface-900 md:text-5xl lg:text-6xl">
                        Sabrás <span class="text-emerald-600">exactamente</span> cuánto puedes pagarte este mes.
                    </h1>

                    <p class="mt-5 max-w-xl text-lg text-surface-600">
                        Fitnances aparta IVA, IRPF y cuota antes de decirte lo que es realmente tuyo. Adiós a la calculadora del domingo por la noche.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link href="/register">
                            <Button label="Empieza 14 días gratis" icon="pi pi-arrow-right" iconPos="right" />
                        </Link>
                        <Link href="/login">
                            <Button label="Ya tengo cuenta" severity="secondary" text />
                        </Link>
                    </div>

                    <p class="mt-4 text-xs text-surface-500">
                        Sin tarjeta · Sin permanencia · Tus datos solo tuyos.
                    </p>
                </div>

                <!-- Mockup card -->
                <div class="relative">
                    <div class="absolute -inset-4 -z-10 rounded-3xl bg-gradient-to-br from-emerald-200/40 via-white to-violet-200/30 blur-xl" />

                    <div class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-xl ring-1 ring-surface-900/5">
                        <div class="flex items-center gap-1.5 border-b border-surface-100 bg-surface-50 px-4 py-2.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-red-300" />
                            <span class="h-2.5 w-2.5 rounded-full bg-amber-300" />
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-300" />
                            <span class="ml-3 text-[11px] font-medium text-surface-500">fitnances.app — Resumen</span>
                        </div>

                        <div class="space-y-5 p-6">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-surface-500">Puedes sacar este mes</p>
                                <p class="mt-1 text-5xl font-extrabold tracking-tight text-emerald-600">2.840,50&nbsp;€</p>
                                <p class="mt-1 text-xs text-surface-500">Una vez apartados IVA e IRPF, y cubiertos los cargos del mes.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3 border-t border-surface-100 pt-4">
                                <div class="rounded-lg border border-amber-100 bg-amber-50/60 p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700">IVA · M-303</p>
                                    <p class="mt-1 text-base font-bold text-amber-700">1.850,20&nbsp;€</p>
                                </div>
                                <div class="rounded-lg border border-amber-100 bg-amber-50/60 p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700">IRPF · M-130</p>
                                    <p class="mt-1 text-base font-bold text-amber-700">980,50&nbsp;€</p>
                                </div>
                            </div>

                            <div class="rounded-lg border border-surface-200 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-surface-700">Próxima cuota</span>
                                    <Tag value="5 días" severity="warn" />
                                </div>
                                <p class="mt-2 text-sm text-surface-600">Cuota autónomos · 469,00&nbsp;€</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Benefits -->
        <section class="border-y border-surface-200/60 bg-white">
            <div class="mx-auto max-w-6xl px-6 py-20">
                <div class="mb-12 max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Pensado para autónomos</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight md:text-4xl">Las tres cuentas que nadie te enseñó a hacer.</h2>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <article
                        v-for="benefit in benefits"
                        :key="benefit.title"
                        class="group rounded-2xl border border-surface-200 bg-surface-50/50 p-6 transition-all hover:-translate-y-0.5 hover:border-surface-300 hover:bg-white hover:shadow-md"
                    >
                        <div
                            class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg ring-1"
                            :class="accentClass(benefit.accent)"
                        >
                            <i :class="benefit.icon" />
                        </div>
                        <h3 class="text-lg font-semibold text-surface-900">{{ benefit.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-surface-600">{{ benefit.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section class="bg-surface-50">
            <div class="mx-auto max-w-6xl px-6 py-20">
                <div class="mb-12 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Cómo funciona</p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight md:text-4xl">De cero a control en cinco minutos.</h2>
                    </div>
                    <p class="max-w-sm text-sm text-surface-500">
                        Pensado para llevar tu dinero, no para pasar tiempo dentro de la app.
                    </p>
                </div>

                <ol class="grid gap-5 md:grid-cols-3">
                    <li
                        v-for="step in steps"
                        :key="step.n"
                        class="relative rounded-2xl border border-surface-200 bg-white p-6"
                    >
                        <span class="absolute -top-3 left-6 inline-flex h-7 items-center rounded-full bg-emerald-600 px-2.5 text-xs font-bold text-white tabular-nums">
                            {{ step.n }}
                        </span>
                        <h3 class="mt-2 text-base font-semibold text-surface-900">{{ step.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-surface-600">{{ step.body }}</p>
                    </li>
                </ol>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="bg-surface-900 text-white">
            <div class="mx-auto max-w-4xl px-6 py-20 text-center">
                <h2 class="text-3xl font-bold tracking-tight md:text-4xl">
                    Deja de adivinar lo que es tuyo.
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-base text-surface-300">
                    Empieza gratis durante 14 días. Sin tarjeta. Si no es para ti, te marchas sin más.
                </p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <Link href="/register">
                        <Button label="Crear mi cuenta" icon="pi pi-arrow-right" iconPos="right" severity="success" />
                    </Link>
                    <Link href="/login">
                        <Button label="Entrar" severity="contrast" text class="!text-white" />
                    </Link>
                </div>
            </div>
        </section>

        <footer class="border-t border-surface-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-6 text-xs text-surface-500 md:flex-row">
                <span>© {{ new Date().getFullYear() }} {{ name }}. Hecho desde Cataluña.</span>
                <div class="flex gap-4">
                    <a href="#" class="hover:text-surface-700">Privacidad</a>
                    <a href="#" class="hover:text-surface-700">Términos</a>
                    <a href="mailto:hola@fitnances.app" class="hover:text-surface-700">Contacto</a>
                </div>
            </div>
        </footer>
    </div>
</template>
