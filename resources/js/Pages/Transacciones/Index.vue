<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Drawer from 'primevue/drawer';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import Textarea from 'primevue/textarea';
import SelectButton from 'primevue/selectbutton';
import Message from 'primevue/message';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatEuros, formatShortDate } from '@/lib/format';

const props = defineProps({
    transactions: { type: Array, required: true },
    categories: { type: Array, required: true },
});

const typeOptions = [
    { label: 'Todos', value: null },
    { label: 'Ingresos', value: 'income' },
    { label: 'Gastos', value: 'expense' },
];

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

const filterType = ref(null);
const filterCategory = ref(null);
const filterSearch = ref('');

const filteredTransactions = computed(() =>
    props.transactions.filter((t) => {
        if (filterType.value && t.type !== filterType.value) return false;
        if (filterCategory.value && t.category !== filterCategory.value) return false;
        if (filterSearch.value && !t.description.toLowerCase().includes(filterSearch.value.toLowerCase())) return false;
        return true;
    }),
);

// Drawer state
const drawerOpen = ref(false);
const editingId = ref(null);
const formTypeOptions = [
    { label: 'Ingreso', value: 'income' },
    { label: 'Gasto', value: 'expense' },
];

const form = ref(emptyForm());

function emptyForm() {
    return {
        type: 'expense',
        amount: null,
        iva: 21,
        irpf: 0,
        date: new Date(),
        category: null,
        description: '',
    };
}

const openCreate = () => {
    editingId.value = null;
    form.value = emptyForm();
    drawerOpen.value = true;
};

const openEdit = (row) => {
    editingId.value = row.id;
    form.value = {
        type: row.type,
        amount: row.amount,
        iva: row.iva,
        irpf: row.irpf ?? 0,
        date: new Date(row.date),
        category: row.category,
        description: row.description,
    };
    drawerOpen.value = true;
};

const save = () => {
    // Prototype: fake save, just close.
    drawerOpen.value = false;
};

const totalIncome = computed(() =>
    filteredTransactions.value.filter((t) => t.type === 'income').reduce((s, t) => s + t.amount, 0),
);
const totalExpense = computed(() =>
    filteredTransactions.value.filter((t) => t.type === 'expense').reduce((s, t) => s + t.amount, 0),
);

const categoryOptions = computed(() => [
    { label: 'Todas las categorías', value: null },
    ...props.categories.map((c) => ({ label: c, value: c })),
]);
</script>

<template>
    <Head title="Transacciones" />

    <AppLayout title="Transacciones">
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Toolbar -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <IconField iconPosition="left">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="filterSearch" placeholder="Buscar..." />
                    </IconField>
                    <Select v-model="filterType" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Tipo" />
                    <Select v-model="filterCategory" :options="categoryOptions" optionLabel="label" optionValue="value" placeholder="Categoría" />
                </div>
                <Button label="Nueva transacción" icon="pi pi-plus" @click="openCreate" />
            </div>

            <!-- KPI strip -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-surface-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wider text-surface-500">Ingresos</p>
                    <p class="mt-1 text-xl font-semibold text-emerald-600">{{ formatEuros(totalIncome) }}</p>
                </div>
                <div class="rounded-lg border border-surface-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wider text-surface-500">Gastos</p>
                    <p class="mt-1 text-xl font-semibold text-red-600">{{ formatEuros(totalExpense) }}</p>
                </div>
                <div class="col-span-2 rounded-lg border border-surface-200 bg-white p-4 md:col-span-1">
                    <p class="text-xs uppercase tracking-wider text-surface-500">Neto</p>
                    <p class="mt-1 text-xl font-semibold text-surface-900">{{ formatEuros(totalIncome - totalExpense) }}</p>
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-lg border border-surface-200 bg-white">
                <DataTable
                    :value="filteredTransactions"
                    :rows="10"
                    paginator
                    stripedRows
                    @row-click="(e) => openEdit(e.data)"
                    rowHover
                >
                    <template #empty>
                        <div class="py-8 text-center text-sm text-surface-500">
                            <i class="pi pi-inbox mb-2 block text-3xl text-surface-300" />
                            No hay transacciones. Crea la primera con el botón "Nueva transacción".
                        </div>
                    </template>
                    <Column field="date" header="Fecha" sortable>
                        <template #body="{ data }">
                            <span class="text-sm">{{ formatShortDate(data.date) }}</span>
                        </template>
                    </Column>
                    <Column field="description" header="Descripción">
                        <template #body="{ data }">
                            <span class="font-medium text-surface-900">{{ data.description }}</span>
                        </template>
                    </Column>
                    <Column field="category" header="Categoría">
                        <template #body="{ data }">
                            <Tag :value="data.category" severity="secondary" />
                        </template>
                    </Column>
                    <Column field="iva" header="IVA">
                        <template #body="{ data }">
                            <span class="text-sm text-surface-600">{{ data.iva }}%</span>
                        </template>
                    </Column>
                    <Column field="type" header="Tipo">
                        <template #body="{ data }">
                            <Tag
                                :value="data.type === 'income' ? 'Ingreso' : 'Gasto'"
                                :severity="data.type === 'income' ? 'success' : 'danger'"
                            />
                        </template>
                    </Column>
                    <Column field="amount" header="Importe" sortable>
                        <template #body="{ data }">
                            <span
                                class="font-semibold"
                                :class="data.type === 'income' ? 'text-emerald-600' : 'text-red-600'"
                            >
                                {{ data.type === 'income' ? '+' : '−' }} {{ formatEuros(data.amount) }}
                            </span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- Drawer create/edit -->
        <Drawer v-model:visible="drawerOpen" position="right" class="!w-full md:!w-[420px]">
            <template #header>
                <span class="text-lg font-semibold">
                    {{ editingId ? 'Editar transacción' : 'Nueva transacción' }}
                </span>
            </template>

            <form class="flex flex-col gap-5" @submit.prevent="save">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Tipo</label>
                    <SelectButton v-model="form.type" :options="formTypeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
                </div>

                <div class="flex flex-col gap-2">
                    <label for="description" class="text-sm font-medium text-surface-700">Descripción</label>
                    <InputText id="description" v-model="form.description" placeholder="Ej: Factura cliente Acme" fluid />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label for="amount" class="text-sm font-medium text-surface-700">Importe (€)</label>
                        <InputNumber id="amount" v-model="form.amount" :minFractionDigits="2" :maxFractionDigits="2" locale="es-ES" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Fecha</label>
                        <DatePicker v-model="form.date" dateFormat="dd/mm/yy" fluid />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Categoría</label>
                    <Select v-model="form.category" :options="categories" placeholder="Selecciona categoría" fluid />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">IVA</label>
                    <Select v-model="form.iva" :options="ivaOptions" optionLabel="label" optionValue="value" fluid />
                </div>

                <div v-if="form.type === 'income'" class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">IRPF retenido</label>
                    <Select v-model="form.irpf" :options="irpfOptions" optionLabel="label" optionValue="value" fluid />
                    <Message severity="info" size="small" variant="simple">
                        Aplica solo en facturas B2B en España.
                    </Message>
                </div>

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
