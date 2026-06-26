<script setup>
import { ref, computed, reactive, watch } from 'vue';
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
import SelectButton from 'primevue/selectbutton';
import Message from 'primevue/message';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategorySelect from '@/Components/CategorySelect.vue';
import { formatEuros, formatShortDate } from '@/lib/format';

const props = defineProps({
    transactions: { type: Array, required: true },
    categories: { type: Array, required: true },
});

const transactionsState = reactive(props.transactions.map((t) => ({ ...t })));

const categoriesState = reactive([...props.categories]);
const addCategory = (name) => {
    if (!categoriesState.includes(name)) categoriesState.push(name);
};

const typeFilterOptions = [
    { label: 'Todos', value: null },
    { label: 'Ingresos', value: 'income' },
    { label: 'Gastos', value: 'expense' },
];

const statusFilterOptions = [
    { label: 'Todos', value: null },
    { label: 'Previsto', value: 'previsto' },
    { label: 'Realizado', value: 'realizado' },
];

const formTypeOptions = [
    { label: 'Ingreso', value: 'income' },
    { label: 'Gasto', value: 'expense' },
];

const formStatusOptions = [
    { label: 'Previsto', value: 'previsto' },
    { label: 'Realizado', value: 'realizado' },
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
const filterStatus = ref(null);
const filterCategory = ref(null);
const filterSearch = ref('');

const categoryOptions = computed(() => [
    { label: 'Todas las categorías', value: null },
    ...categoriesState.map((c) => ({ label: c, value: c })),
]);

const filteredTransactions = computed(() =>
    transactionsState.filter((t) => {
        if (filterType.value && t.type !== filterType.value) return false;
        if (filterStatus.value && t.status !== filterStatus.value) return false;
        if (filterCategory.value && t.category !== filterCategory.value) return false;
        if (
            filterSearch.value &&
            !t.description.toLowerCase().includes(filterSearch.value.toLowerCase())
        )
            return false;
        return true;
    }),
);

const kpis = computed(() => {
    const r = filteredTransactions.value;
    return {
        cobrado: r.filter((t) => t.type === 'income' && t.status === 'realizado').reduce((s, t) => s + t.amount, 0),
        porCobrar: r.filter((t) => t.type === 'income' && t.status === 'previsto').reduce((s, t) => s + t.amount, 0),
        gastos: r.filter((t) => t.type === 'expense' && t.status === 'realizado').reduce((s, t) => s + t.amount, 0),
        porPagar: r.filter((t) => t.type === 'expense' && t.status === 'previsto').reduce((s, t) => s + t.amount, 0),
    };
});

const drawerOpen = ref(false);
const editingId = ref(null);
const form = ref(emptyForm());

function emptyForm() {
    return {
        type: 'expense',
        status: 'realizado',
        description: '',
        category: null,
        date: new Date(),
        amount: null,
        iva: 21,
        irpf: 0,
    };
}

// Quand on switche le type, on bascule sur les valeurs par défaut adaptées
watch(
    () => form.value.type,
    (newType, oldType) => {
        if (!oldType || newType === oldType) return;
        if (newType === 'income') {
            form.value.status = 'previsto';
            if (form.value.irpf === 0) form.value.irpf = 15;
        } else {
            form.value.status = 'realizado';
            form.value.irpf = 0;
        }
    },
);

const openCreate = (type = 'expense') => {
    editingId.value = null;
    form.value = emptyForm();
    if (type === 'income') {
        form.value.type = 'income';
        form.value.status = 'previsto';
        form.value.irpf = 15;
    }
    drawerOpen.value = true;
};

const openEdit = (row) => {
    editingId.value = row.id;
    form.value = {
        type: row.type,
        status: row.status,
        description: row.description,
        category: row.category,
        date: new Date(row.date),
        amount: row.amount,
        iva: row.iva,
        irpf: row.irpf ?? 0,
    };
    drawerOpen.value = true;
};

const save = () => {
    if (editingId.value !== null) {
        const idx = transactionsState.findIndex((t) => t.id === editingId.value);
        if (idx >= 0) {
            transactionsState[idx] = {
                ...transactionsState[idx],
                ...form.value,
                date: form.value.date instanceof Date ? form.value.date.toISOString().split('T')[0] : form.value.date,
            };
        }
    }
    drawerOpen.value = false;
};

const markAsRealizado = (row, event) => {
    event.stopPropagation();
    const idx = transactionsState.findIndex((t) => t.id === row.id);
    if (idx >= 0) transactionsState[idx].status = 'realizado';
};

const statusMeta = (status) =>
    status === 'realizado'
        ? { label: 'Realizado', severity: 'success', icon: 'pi-check-circle' }
        : { label: 'Previsto', severity: 'warn', icon: 'pi-clock' };
</script>

<template>
    <Head title="Transacciones" />

    <AppLayout title="Transacciones">
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- KPI strip -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Cobrado</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-emerald-700">{{ formatEuros(kpis.cobrado) }}</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Por cobrar</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-amber-700">{{ formatEuros(kpis.porCobrar) }}</p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-red-700">Gastos</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-red-700">{{ formatEuros(kpis.gastos) }}</p>
                </div>
                <div class="rounded-lg border border-surface-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-surface-500">Por pagar</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-surface-700">{{ formatEuros(kpis.porPagar) }}</p>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <IconField iconPosition="left">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="filterSearch" placeholder="Buscar..." />
                    </IconField>
                    <Select v-model="filterType" :options="typeFilterOptions" optionLabel="label" optionValue="value" placeholder="Tipo" />
                    <Select v-model="filterStatus" :options="statusFilterOptions" optionLabel="label" optionValue="value" placeholder="Estado" />
                    <Select v-model="filterCategory" :options="categoryOptions" optionLabel="label" optionValue="value" placeholder="Categoría" />
                </div>
                <div class="flex gap-2">
                    <Button label="Nuevo ingreso" icon="pi pi-plus" severity="success" outlined @click="openCreate('income')" />
                    <Button label="Nuevo gasto" icon="pi pi-plus" severity="danger" outlined @click="openCreate('expense')" />
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-lg border border-surface-200 bg-white">
                <DataTable
                    :value="filteredTransactions"
                    :rows="15"
                    paginator
                    stripedRows
                    rowHover
                    @row-click="(e) => openEdit(e.data)"
                >
                    <template #empty>
                        <div class="py-8 text-center text-sm text-surface-500">
                            <i class="pi pi-inbox mb-2 block text-3xl text-surface-300" />
                            No hay transacciones que coincidan con los filtros.
                        </div>
                    </template>

                    <Column field="status" header="Estado" :style="{ width: '120px' }">
                        <template #body="{ data }">
                            <Tag
                                :value="statusMeta(data.status).label"
                                :severity="statusMeta(data.status).severity"
                                :icon="'pi ' + statusMeta(data.status).icon"
                            />
                        </template>
                    </Column>

                    <Column field="date" header="Fecha" sortable :style="{ width: '110px' }">
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

                    <Column field="iva" header="IVA" :style="{ width: '70px' }">
                        <template #body="{ data }">
                            <span class="text-sm text-surface-600">{{ data.iva }}%</span>
                        </template>
                    </Column>

                    <Column field="irpf" header="IRPF" :style="{ width: '80px' }">
                        <template #body="{ data }">
                            <span v-if="data.irpf" class="text-sm text-red-600">−{{ data.irpf }}%</span>
                            <span v-else class="text-sm text-surface-400">—</span>
                        </template>
                    </Column>

                    <Column field="amount" header="Importe" sortable>
                        <template #body="{ data }">
                            <span
                                class="font-semibold tabular-nums"
                                :class="data.type === 'income' ? 'text-emerald-600' : 'text-red-600'"
                            >
                                {{ data.type === 'income' ? '+' : '−' }} {{ formatEuros(data.amount) }}
                            </span>
                        </template>
                    </Column>

                    <Column :style="{ width: '60px' }">
                        <template #body="{ data }">
                            <Button
                                v-if="data.status === 'previsto'"
                                icon="pi pi-check"
                                size="small"
                                severity="success"
                                text
                                rounded
                                v-tooltip.left="data.type === 'income' ? 'Marcar como cobrado' : 'Marcar como pagado'"
                                @click="markAsRealizado(data, $event)"
                            />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- Drawer create/edit -->
        <Drawer v-model:visible="drawerOpen" position="right" class="!w-full md:!w-[460px]">
            <template #header>
                <span class="text-lg font-semibold">
                    {{ editingId ? 'Editar transacción' : form.type === 'income' ? 'Nuevo ingreso' : 'Nuevo gasto' }}
                </span>
            </template>

            <form class="flex flex-col gap-5" @submit.prevent="save">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Tipo</label>
                    <SelectButton v-model="form.type" :options="formTypeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Estado</label>
                    <SelectButton v-model="form.status" :options="formStatusOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
                </div>

                <div class="flex flex-col gap-2">
                    <label for="description" class="text-sm font-medium text-surface-700">
                        {{ form.type === 'income' ? 'Cliente / descripción' : 'Descripción' }}
                    </label>
                    <InputText
                        id="description"
                        v-model="form.description"
                        :placeholder="form.type === 'income' ? 'Ej: Factura Acme S.L. — Hito 2' : 'Ej: Alquiler oficina'"
                        fluid
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label for="amount" class="text-sm font-medium text-surface-700">Importe (€)</label>
                        <InputNumber id="amount" v-model="form.amount" :minFractionDigits="2" :maxFractionDigits="2" locale="es-ES" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">
                            {{ form.status === 'previsto' ? 'Fecha prevista' : 'Fecha' }}
                        </label>
                        <DatePicker v-model="form.date" dateFormat="dd/mm/yy" fluid />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Categoría</label>
                    <CategorySelect v-model="form.category" :options="categoriesState" @add="addCategory" />
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
