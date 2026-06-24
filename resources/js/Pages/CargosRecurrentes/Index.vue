<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Drawer from 'primevue/drawer';
import ToggleSwitch from 'primevue/toggleswitch';
import Message from 'primevue/message';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatEuros } from '@/lib/format';

const props = defineProps({
    charges: { type: Array, required: true },
    categories: { type: Array, required: true },
});

const frequencyOptions = [
    { label: 'Mensual', value: 'monthly' },
    { label: 'Trimestral', value: 'quarterly' },
    { label: 'Anual', value: 'yearly' },
];

const frequencyLabel = (value) => frequencyOptions.find((f) => f.value === value)?.label ?? value;

const monthlyEquivalent = (charge) => {
    if (charge.frequency === 'monthly') return charge.amount;
    if (charge.frequency === 'quarterly') return charge.amount / 3;
    if (charge.frequency === 'yearly') return charge.amount / 12;
    return charge.amount;
};

const totalMonthly = computed(() =>
    props.charges.filter((c) => c.active).reduce((s, c) => s + monthlyEquivalent(c), 0),
);

const drawerOpen = ref(false);
const editingId = ref(null);
const form = ref(emptyForm());

function emptyForm() {
    return {
        name: '',
        amount: null,
        frequency: 'monthly',
        category: null,
        dayOfMonth: 1,
        active: true,
    };
}

const openCreate = () => {
    editingId.value = null;
    form.value = emptyForm();
    drawerOpen.value = true;
};

const openEdit = (charge) => {
    editingId.value = charge.id;
    form.value = {
        name: charge.name,
        amount: charge.amount,
        frequency: charge.frequency,
        category: charge.category,
        dayOfMonth: charge.dayOfMonth,
        active: charge.active,
    };
    drawerOpen.value = true;
};

const save = () => {
    drawerOpen.value = false;
};
</script>

<template>
    <Head title="Cargos recurrentes" />

    <AppLayout title="Cargos recurrentes">
        <div class="mx-auto max-w-6xl space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm text-surface-500">Gastos fijos que se repiten cada mes, trimestre o año.</p>
                </div>
                <Button label="Nuevo cargo" icon="pi pi-plus" @click="openCreate" />
            </div>

            <!-- KPI strip -->
            <div class="rounded-lg border border-emerald-100 bg-emerald-50/50 p-4">
                <div class="flex items-baseline justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Total equivalente mensual</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-700">{{ formatEuros(totalMonthly) }}</p>
                    </div>
                    <span class="text-xs text-emerald-600">{{ charges.filter((c) => c.active).length }} activos</span>
                </div>
            </div>

            <!-- Grid de cargos -->
            <div v-if="charges.length" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="charge in charges"
                    :key="charge.id"
                    type="button"
                    class="group flex flex-col gap-3 rounded-lg border border-surface-200 bg-white p-4 text-left transition-all hover:border-emerald-400 hover:shadow-sm"
                    :class="{ 'opacity-60': !charge.active }"
                    @click="openEdit(charge)"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-semibold text-surface-900">{{ charge.name }}</p>
                            <Tag :value="charge.category" severity="secondary" class="mt-1" />
                        </div>
                        <Tag v-if="!charge.active" value="Inactivo" severity="warn" />
                    </div>

                    <div>
                        <p class="text-2xl font-bold text-surface-900">{{ formatEuros(charge.amount) }}</p>
                        <p class="text-xs text-surface-500">{{ frequencyLabel(charge.frequency) }} · día {{ charge.dayOfMonth }}</p>
                    </div>

                    <p v-if="charge.frequency !== 'monthly'" class="text-xs text-surface-400">
                        ≈ {{ formatEuros(monthlyEquivalent(charge)) }} / mes
                    </p>
                </button>
            </div>

            <div v-else class="rounded-lg border border-dashed border-surface-300 bg-white p-12 text-center">
                <i class="pi pi-replay mb-3 block text-4xl text-surface-300" />
                <p class="font-medium text-surface-700">Aún no tienes cargos recurrentes</p>
                <p class="mt-1 text-sm text-surface-500">Añade tu primer cargo fijo: alquiler, cuota de autónomos, software...</p>
                <Button label="Crear primer cargo" icon="pi pi-plus" class="mt-4" @click="openCreate" />
            </div>
        </div>

        <Drawer v-model:visible="drawerOpen" position="right" class="!w-full md:!w-[420px]">
            <template #header>
                <span class="text-lg font-semibold">
                    {{ editingId ? 'Editar cargo' : 'Nuevo cargo recurrente' }}
                </span>
            </template>

            <form class="flex flex-col gap-5" @submit.prevent="save">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Nombre</label>
                    <InputText v-model="form.name" placeholder="Ej: Alquiler oficina" fluid />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Importe (€)</label>
                        <InputNumber v-model="form.amount" :minFractionDigits="2" :maxFractionDigits="2" locale="es-ES" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Día del mes</label>
                        <InputNumber v-model="form.dayOfMonth" :min="1" :max="31" fluid />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Frecuencia</label>
                    <Select v-model="form.frequency" :options="frequencyOptions" optionLabel="label" optionValue="value" fluid />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Categoría</label>
                    <Select v-model="form.category" :options="categories" placeholder="Selecciona categoría" fluid />
                </div>

                <div class="flex items-center justify-between rounded-lg border border-surface-200 p-3">
                    <div>
                        <p class="text-sm font-medium text-surface-700">Activo</p>
                        <p class="text-xs text-surface-500">Se incluye en la previsión a 90 días</p>
                    </div>
                    <ToggleSwitch v-model="form.active" />
                </div>

                <Message v-if="form.frequency !== 'monthly'" severity="info" size="small" variant="simple">
                    Equivalente mensual: <strong>{{ formatEuros((form.amount || 0) / (form.frequency === 'quarterly' ? 3 : 12)) }}</strong>
                </Message>

                <div class="mt-2 flex gap-2">
                    <Button type="button" label="Cancelar" severity="secondary" outlined fluid @click="drawerOpen = false" />
                    <Button type="submit" :label="editingId ? 'Guardar' : 'Crear'" fluid />
                </div>
            </form>
        </Drawer>
    </AppLayout>
</template>
