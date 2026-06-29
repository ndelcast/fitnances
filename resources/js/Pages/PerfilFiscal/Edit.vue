<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import Message from 'primevue/message';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    profile: { type: Object, required: true },
});

const page = usePage();

const regimeOptions = [
    { label: 'Estimación directa simplificada', value: 'direct_simplified' },
    { label: 'Estimación directa normal', value: 'direct_normal' },
    { label: 'Estimación objetiva (módulos)', value: 'modules' },
];

const ivaDefaultOptions = [
    { label: '21% (general)', value: 21 },
    { label: '10% (reducido)', value: 10 },
    { label: '4% (superreducido)', value: 4 },
    { label: '0% (exento)', value: 0 },
];

const irpfDefaultOptions = [
    { label: '15% (general)', value: 15 },
    { label: '7% (nuevos autónomos, primeros 3 años)', value: 7 },
    { label: 'Sin retención', value: 0 },
];

const provinceOptions = [
    'A Coruña', 'Álava', 'Albacete', 'Alicante', 'Almería', 'Asturias', 'Ávila',
    'Badajoz', 'Barcelona', 'Bizkaia', 'Burgos', 'Cáceres', 'Cádiz', 'Cantabria',
    'Castellón', 'Ciudad Real', 'Córdoba', 'Cuenca', 'Gipuzkoa', 'Girona',
    'Granada', 'Guadalajara', 'Huelva', 'Huesca', 'Illes Balears', 'Jaén',
    'La Rioja', 'Las Palmas', 'León', 'Lleida', 'Lugo', 'Madrid', 'Málaga',
    'Murcia', 'Navarra', 'Ourense', 'Palencia', 'Pontevedra', 'Salamanca',
    'Santa Cruz de Tenerife', 'Segovia', 'Sevilla', 'Soria', 'Tarragona',
    'Teruel', 'Toledo', 'Valencia', 'Valladolid', 'Zamora', 'Zaragoza',
];

const form = ref({
    full_name: props.profile.full_name ?? '',
    nif: props.profile.nif ?? '',
    activity: props.profile.activity ?? '',
    province: props.profile.province ?? null,
    regime: props.profile.regime ?? 'direct_simplified',
    iva_default: props.profile.iva_default ?? 21,
    irpf_default: props.profile.irpf_default ?? 15,
    cuota_monthly: props.profile.cuota_monthly ?? 0,
    surcharge_equivalence: props.profile.surcharge_equivalence ?? false,
    intra_community: props.profile.intra_community ?? false,
});

const saving = ref(false);
const errors = computed(() => page.props.errors ?? {});
const flash = computed(() => page.props.flash);

const save = () => {
    saving.value = true;
    router.put('/fiscal-profile', form.value, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
    });
};
</script>

<template>
    <Head title="Perfil fiscal" />

    <AppLayout title="Perfil fiscal">
        <div class="mx-auto max-w-4xl space-y-6">
            <p class="text-sm text-surface-500">
                Configura tu régimen fiscal para que Fitnances calcule correctamente tus provisiones de IVA, IRPF y cuota.
            </p>

            <form class="space-y-6" @submit.prevent="save">
                <section class="rounded-lg border border-surface-200 bg-white p-6">
                    <h2 class="mb-4 text-base font-semibold text-surface-900">Datos del autónomo</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">Nombre y apellidos</label>
                            <InputText v-model="form.full_name" :invalid="!!errors.full_name" fluid />
                            <small v-if="errors.full_name" class="text-red-600">{{ errors.full_name }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">NIF / NIE</label>
                            <InputText v-model="form.nif" placeholder="12345678X" :invalid="!!errors.nif" fluid />
                            <small v-if="errors.nif" class="text-red-600">{{ errors.nif }}</small>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">Actividad económica</label>
                            <InputText v-model="form.activity" placeholder="Ej: Desarrollo de software" fluid />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">Provincia</label>
                            <Select v-model="form.province" :options="provinceOptions" filter placeholder="Selecciona provincia" fluid />
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-surface-200 bg-white p-6">
                    <h2 class="mb-4 text-base font-semibold text-surface-900">Régimen fiscal</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="text-sm font-medium text-surface-700">Régimen de IRPF</label>
                            <Select v-model="form.regime" :options="regimeOptions" optionLabel="label" optionValue="value" fluid />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">IVA por defecto</label>
                            <Select v-model="form.iva_default" :options="ivaDefaultOptions" optionLabel="label" optionValue="value" fluid />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">IRPF por defecto en facturas</label>
                            <Select v-model="form.irpf_default" :options="irpfDefaultOptions" optionLabel="label" optionValue="value" fluid />
                        </div>
                        <div class="flex flex-col gap-2 md:col-span-2">
                            <label class="text-sm font-medium text-surface-700">Cuota de autónomos mensual (€)</label>
                            <InputNumber v-model="form.cuota_monthly" :minFractionDigits="2" :maxFractionDigits="2" locale="es-ES" fluid />
                            <small class="text-surface-500">
                                Esta cuota se proyecta automáticamente en tu previsión de tesorería.
                            </small>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-surface-200 bg-white p-6">
                    <h2 class="mb-4 text-base font-semibold text-surface-900">Opciones de IVA</h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between rounded-lg border border-surface-200 p-3">
                            <div>
                                <p class="text-sm font-medium text-surface-700">Recargo de equivalencia</p>
                                <p class="text-xs text-surface-500">Solo para comercio minorista a particulares.</p>
                            </div>
                            <ToggleSwitch v-model="form.surcharge_equivalence" />
                        </div>
                        <div class="flex items-center justify-between rounded-lg border border-surface-200 p-3">
                            <div>
                                <p class="text-sm font-medium text-surface-700">Operaciones intracomunitarias</p>
                                <p class="text-xs text-surface-500">Activa si facturas a clientes con NIF-IVA en otros países de la UE (Modelo 349).</p>
                            </div>
                            <ToggleSwitch v-model="form.intra_community" />
                        </div>
                    </div>
                </section>

                <div class="flex items-center justify-end gap-3">
                    <Message v-if="flash?.success" severity="success" size="small" :closable="false" class="!my-0">{{ flash.success }}</Message>
                    <Button type="submit" label="Guardar perfil" icon="pi pi-save" :loading="saving" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
