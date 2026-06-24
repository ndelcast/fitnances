<script setup>
defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <div class="min-h-screen bg-surface-50">
        <div class="grid min-h-screen lg:grid-cols-2">
            <!-- Form side -->
            <div class="flex flex-col px-6 py-10 md:px-12 lg:px-16">
                <div class="flex items-center gap-2">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white">
                        <i class="pi pi-wallet text-sm" />
                    </div>
                    <span class="text-lg font-bold text-surface-900">Fitnances</span>
                </div>

                <div class="mx-auto my-auto w-full max-w-sm py-12">
                    <div class="mb-8">
                        <h1 v-if="title" class="text-2xl font-bold text-surface-900">{{ title }}</h1>
                        <p v-if="subtitle" class="mt-2 text-sm text-surface-500">{{ subtitle }}</p>
                    </div>
                    <slot />
                </div>

                <p class="text-xs text-surface-400">
                    © {{ new Date().getFullYear() }} Fitnances · Tesorería para autónomos
                </p>
            </div>

            <!-- Visual side -->
            <div class="hidden bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800 lg:flex lg:flex-col lg:justify-between lg:p-16 lg:text-white">
                <div></div>
                <div class="max-w-md">
                    <p class="text-sm font-semibold uppercase tracking-wider text-emerald-200">Para autónomos en España</p>
                    <h2 class="mt-4 text-3xl font-bold leading-tight">
                        Sabes cuánto puedes sacar de tu cuenta cada mes
                    </h2>
                    <p class="mt-4 text-emerald-100">
                        Fitnances calcula automáticamente tus provisiones de IVA, IRPF y cuota de autónomos. Sin sorpresas en Hacienda.
                    </p>
                </div>
                <ul class="space-y-3 text-sm text-emerald-100">
                    <li class="flex items-center gap-2">
                        <i class="pi pi-check-circle text-emerald-300" />
                        Provisiones automáticas Modelo 303 y 130
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="pi pi-check-circle text-emerald-300" />
                        Previsión de tesorería a 90 días
                    </li>
                    <li class="flex items-center gap-2">
                        <i class="pi pi-check-circle text-emerald-300" />
                        14 días de prueba, sin tarjeta
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
