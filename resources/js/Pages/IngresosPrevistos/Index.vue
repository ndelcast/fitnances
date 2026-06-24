<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import Drawer from 'primevue/drawer';
import Message from 'primevue/message';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatEuros, formatShortDate } from '@/lib/format';

const props = defineProps({
    incomes: { type: Array, required: true },
});

const statusOptions = [
    { label: 'Pendiente', value: 'pending', severity: 'warn' },
    { label: 'Facturado', value: 'invoiced', severity: 'info' },
    { label: 'Cobrado', value: 'collected', severity: 'success' },
];

const statusMeta = (value) => statusOptions.find((s) => s.value === value) ?? { label: value, severity: 'secondary' };

const ivaOptions = [
    { label: '21%', value: 21 },
    { label: '10%', value: 10 },
    { label: '4%', value: 4 },
    { label: '0% (exento)', value: 0 },
];

const irpfOptions = [
    { label: 'Sin retención', value: 0 },
    { label: '7% (nuevos autónomos)', value: 7 },
    { label: '15% (general)', value: 15 },
];

const filterStatus = ref(null);
const filterStatusOptions = [{ label: 'Todos', value: null }, ...statusOptions];

const filteredIncomes = computed(() =>
    props.incomes.filter((i) => !filterStatus.value || i.status === filterStatus.value),
);

const totalPending = computed(() =>
    props.incomes.filter((i) => i.status !== 'collected').reduce((s, i) => s + i.netAmount, 0),
);
const totalCollected = computed(() =>
    props.incomes.filter((i) => i.status === 'collected').reduce((s, i) => s + i.netAmount, 0),
);

const drawerOpen = ref(false);
const editingId = ref(null);
const form = ref(emptyForm());

function emptyForm() {
    return {
        client: '',
        baseAmount: null,
        iva: 21,
        irpf: 15,
        expectedDate: new Date(),
        status: 'pending',
        notes: '',
    };
}

const netPreview = computed(() => {
    const base = form.value.baseAmount || 0;
    const iva = (base * form.value.iva) / 100;
    const irpf = (base * form.value.irpf) / 100;
    return base + iva - irpf;
});

const openCreate = () => {
    editingId.value = null;
    form.value = emptyForm();
    drawerOpen.value = true;
};

const openEdit = (row) => {
    editingId.value = row.id;
    form.value = {
        client: row.client,
        baseAmount: row.baseAmount,
        iva: row.iva,
        irpf: row.irpf,
        expectedDate: new Date(row.expectedDate),
        status: row.status,
        notes: row.notes ?? '',
    };
    drawerOpen.value = true;
};

const save = () => {
    drawerOpen.value = false;
};
</script>

<template>
    <Head title="Ingresos previstos" />

    <AppLayout title="Ingresos previstos">
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <Select v-model="filterStatus" :options="filterStatusOptions" optionLabel="label" optionValue="value" placeholder="Estado" />
                </div>
                <Button label="Nueva factura prevista" icon="pi pi-plus" @click="openCreate" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-lg border border-surface-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wider text-surface-500">Pendiente de cobro</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-600">{{ formatEuros(totalPending) }}</p>
                </div>
                <div class="rounded-lg border border-surface-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wider text-surface-500">Ya cobrado</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ formatEuros(totalCollected) }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-surface-200 bg-white">
                <DataTable
                    :value="filteredIncomes"
                    :rows="10"
                    paginator
                    stripedRows
                    rowHover
                    @row-click="(e) => openEdit(e.data)"
                >
                    <template #empty>
                        <div class="py-8 text-center text-sm text-surface-500">
                            <i class="pi pi-inbox mb-2 block text-3xl text-surface-300" />
                            No hay ingresos previstos.
                        </div>
                    </template>
                    <Column field="client" header="Cliente">
                        <template #body="{ data }">
                            <span class="font-medium text-surface-900">{{ data.client }}</span>
                        </template>
                    </Column>
                    <Column field="expectedDate" header="Fecha prevista" sortable>
                        <template #body="{ data }">
                            <span class="text-sm">{{ formatShortDate(data.expectedDate) }}</span>
                        </template>
                    </Column>
                    <Column field="baseAmount" header="Base">
                        <template #body="{ data }">
                            <span class="text-sm text-surface-600">{{ formatEuros(data.baseAmount) }}</span>
                        </template>
                    </Column>
                    <Column field="iva" header="IVA">
                        <template #body="{ data }">
                            <span class="text-sm text-surface-600">{{ data.iva }}%</span>
                        </template>
                    </Column>
                    <Column field="irpf" header="IRPF">
                        <template #body="{ data }">
                            <span v-if="data.irpf" class="text-sm text-red-600">−{{ data.irpf }}%</span>
                            <span v-else class="text-sm text-surface-400">—</span>
                        </template>
                    </Column>
                    <Column field="netAmount" header="Neto" sortable>
                        <template #body="{ data }">
                            <span class="font-semibold text-surface-900">{{ formatEuros(data.netAmount) }}</span>
                        </template>
                    </Column>
                    <Column field="status" header="Estado">
                        <template #body="{ data }">
                            <Tag :value="statusMeta(data.status).label" :severity="statusMeta(data.status).severity" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Drawer v-model:visible="drawerOpen" position="right" class="!w-full md:!w-[460px]">
            <template #header>
                <span class="text-lg font-semibold">
                    {{ editingId ? 'Editar factura prevista' : 'Nueva factura prevista' }}
                </span>
            </template>

            <form class="flex flex-col gap-5" @submit.prevent="save">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Cliente</label>
                    <InputText v-model="form.client" placeholder="Ej: Cliente Acme S.L." fluid />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Base imponible (€)</label>
                        <InputNumber v-model="form.baseAmount" :minFractionDigits="2" :maxFractionDigits="2" locale="es-ES" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Fecha prevista</label>
                        <DatePicker v-model="form.expectedDate" dateFormat="dd/mm/yy" fluid />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">IVA</label>
                        <Select v-model="form.iva" :options="ivaOptions" optionLabel="label" optionValue="value" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">IRPF retenido</label>
                        <Select v-model="form.irpf" :options="irpfOptions" optionLabel="label" optionValue="value" fluid />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Estado</label>
                    <Select v-model="form.status" :options="statusOptions" optionLabel="label" optionValue="value" fluid />
                </div>

                <Message severity="info" size="small" variant="simple">
                    Importe neto a cobrar: <strong>{{ formatEuros(netPreview) }}</strong>
                </Message>

                <div class="mt-2 flex gap-2">
                    <Button type="button" label="Cancelar" severity="secondary" outlined fluid @click="drawerOpen = false" />
                    <Button type="submit" :label="editingId ? 'Guardar' : 'Crear'" fluid />
                </div>
            </form>
        </Drawer>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-tbody > tr) {
    cursor: pointer;
}
</style>
