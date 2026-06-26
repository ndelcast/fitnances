<script setup>
import { ref, computed, reactive, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import SelectButton from 'primevue/selectbutton';
import ToggleSwitch from 'primevue/toggleswitch';
import Message from 'primevue/message';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategorySelect from '@/Components/CategorySelect.vue';
import { formatEuros, formatShortDate } from '@/lib/format';

const props = defineProps({
    movements: { type: Array, required: true },
    categories: { type: Array, required: true }, // [{ id, name, type }]
});

const page = usePage();
const confirm = useConfirm();

const categoriesState = reactive([...props.categories]);
const addCategoryFromCreate = (created) => {
    if (!categoriesState.find((c) => c.id === created.id)) {
        categoriesState.push(created);
    }
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

const paidFilterOptions = [
    { label: 'Todos', value: null },
    { label: 'Cobrado / Pagado', value: true },
    { label: 'Pendiente', value: false },
];

const formTypeOptions = [
    { label: 'Ingreso', value: 'income' },
    { label: 'Gasto', value: 'expense' },
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
const filterPaid = ref(null);
const filterCategoryId = ref(null);
const filterSearch = ref('');

const categoryOptionsForFilter = computed(() => [
    { label: 'Todas las categorías', value: null },
    ...categoriesState.map((c) => ({ label: c.name, value: c.id })),
]);

const categoriesForForm = computed(() =>
    categoriesState.filter((c) => c.type === form.value.kind),
);

const filteredMovements = computed(() =>
    props.movements.filter((t) => {
        if (filterType.value && t.kind !== filterType.value) return false;
        if (filterStatus.value && t.status !== filterStatus.value) return false;
        if (filterPaid.value !== null && t.is_paid !== filterPaid.value) return false;
        if (filterCategoryId.value && t.category_id !== filterCategoryId.value) return false;
        if (filterSearch.value && !t.label.toLowerCase().includes(filterSearch.value.toLowerCase())) {
            return false;
        }
        return true;
    }),
);

const kpis = computed(() => {
    const r = filteredMovements.value;
    return {
        cobrado: r.filter((t) => t.kind === 'income' && t.status === 'realizado').reduce((s, t) => s + t.amount, 0),
        porCobrar: r.filter((t) => t.kind === 'income' && t.status === 'previsto').reduce((s, t) => s + t.amount, 0),
        gastos: r.filter((t) => t.kind === 'expense' && t.status === 'realizado').reduce((s, t) => s + t.amount, 0),
        porPagar: r.filter((t) => t.kind === 'expense' && t.status === 'previsto').reduce((s, t) => s + t.amount, 0),
    };
});

const drawerOpen = ref(false);
const editingId = ref(null);
const saving = ref(false);
const form = ref(emptyForm());

function emptyForm() {
    return {
        kind: 'expense',
        label: '',
        category_id: null,
        estimated_on: new Date(),
        amount: null,
        has_iva: true,
        has_irpf: false,
        iva_rate: 21,
        irpf_rate: 15,
        paid: false,
    };
}

watch(
    () => form.value.kind,
    (newType, oldType) => {
        if (!oldType || newType === oldType) return;
        form.value.category_id = null;
        if (newType === 'income') {
            form.value.irpf_rate = 15;
        } else {
            form.value.irpf_rate = 0;
        }
    },
);

const openCreate = (kind = 'expense') => {
    editingId.value = null;
    form.value = emptyForm();
    form.value.kind = kind;
    if (kind === 'income') {
        form.value.has_irpf = true;
        form.value.estimated_on = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
    } else {
        form.value.has_irpf = false;
    }
    drawerOpen.value = true;
};

const openEdit = (row) => {
    editingId.value = row.id;
    form.value = {
        kind: row.kind,
        label: row.label,
        category_id: row.category_id,
        estimated_on: new Date(row.estimated_on),
        amount: row.amount,
        has_iva: row.has_iva ?? true,
        has_irpf: row.has_irpf ?? false,
        iva_rate: row.iva_rate ?? 21,
        irpf_rate: row.irpf_rate ?? 15,
        paid: !!row.is_paid,
    };
    drawerOpen.value = true;
};

const buildPayload = () => ({
    kind: form.value.kind,
    label: form.value.label,
    category_id: form.value.category_id,
    amount: form.value.amount,
    has_iva: form.value.has_iva,
    has_irpf: form.value.kind === 'income' ? form.value.has_irpf : false,
    iva_rate: form.value.has_iva ? form.value.iva_rate : null,
    irpf_rate: form.value.kind === 'income' && form.value.has_irpf ? form.value.irpf_rate : null,
    paid: form.value.paid,
    estimated_on:
        form.value.estimated_on instanceof Date
            ? form.value.estimated_on.toISOString().split('T')[0]
            : form.value.estimated_on,
});

const save = () => {
    saving.value = true;
    const payload = buildPayload();
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            drawerOpen.value = false;
        },
        onFinish: () => {
            saving.value = false;
        },
    };

    if (editingId.value) {
        router.put(`/movements/${editingId.value}`, payload, options);
    } else {
        router.post('/movements', payload, options);
    }
};

const togglePaid = (row, event) => {
    event.stopPropagation();
    router.patch(`/movements/${row.id}/toggle-paid`, {}, { preserveScroll: true });
};

const askDelete = (event) => {
    if (!editingId.value) return;
    confirm.require({
        target: event.currentTarget,
        message: '¿Eliminar esta transacción?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar',
        rejectLabel: 'Cancelar',
        acceptClass: 'p-button-danger',
        accept: () => {
            const id = editingId.value;
            router.delete(`/movements/${id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    drawerOpen.value = false;
                },
            });
        },
    });
};

const statusMeta = (status) =>
    status === 'realizado'
        ? { label: 'Realizado', severity: 'success', icon: 'pi-check-circle' }
        : { label: 'Previsto', severity: 'warn', icon: 'pi-clock' };

const paidMeta = (row) => {
    if (row.is_paid) {
        return {
            label: row.kind === 'income' ? 'Cobrado' : 'Pagado',
            severity: 'success',
            icon: 'pi-check-circle',
        };
    }
    if (row.status === 'realizado') {
        return { label: 'Vencido', severity: 'danger', icon: 'pi-exclamation-circle' };
    }
    return { label: 'Pendiente', severity: 'warn', icon: 'pi-clock' };
};

const flash = computed(() => page.props.flash);
</script>

<template>
    <Head title="Transacciones" />
    <ConfirmPopup />

    <AppLayout title="Transacciones">
        <div class="mx-auto max-w-7xl space-y-6">
            <Message v-if="flash?.success" severity="success" :closable="true">{{ flash.success }}</Message>

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
                    <Select v-model="filterPaid" :options="paidFilterOptions" optionLabel="label" optionValue="value" placeholder="Cobro / Pago" />
                    <Select v-model="filterCategoryId" :options="categoryOptionsForFilter" optionLabel="label" optionValue="value" placeholder="Categoría" />
                </div>
                <div class="flex gap-2">
                    <Button label="Nuevo ingreso" icon="pi pi-plus" severity="success" outlined @click="openCreate('income')" />
                    <Button label="Nuevo gasto" icon="pi pi-plus" severity="danger" outlined @click="openCreate('expense')" />
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-lg border border-surface-200 bg-white">
                <DataTable
                    :value="filteredMovements"
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

                    <Column field="is_paid" header="Estado" :style="{ width: '130px' }">
                        <template #body="{ data }">
                            <Tag :value="paidMeta(data).label" :severity="paidMeta(data).severity" :icon="'pi ' + paidMeta(data).icon" />
                        </template>
                    </Column>

                    <Column field="estimated_on" header="Fecha" sortable :style="{ width: '110px' }">
                        <template #body="{ data }">
                            <span class="text-sm">{{ formatShortDate(data.estimated_on) }}</span>
                        </template>
                    </Column>

                    <Column field="label" header="Descripción">
                        <template #body="{ data }">
                            <span class="font-medium text-surface-900">{{ data.label }}</span>
                            <span
                                v-if="data.is_recurring"
                                v-tooltip="'Forma parte de una serie recurrente del Flujo de caja.'"
                                class="ml-2 inline-flex items-center gap-1 rounded bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-violet-700 ring-1 ring-violet-200"
                            >
                                <i class="pi pi-replay text-[9px]" />
                                Recurrente
                            </span>
                        </template>
                    </Column>

                    <Column field="category_name" header="Categoría">
                        <template #body="{ data }">
                            <Tag v-if="data.category_name" :value="data.category_name" severity="secondary" />
                            <span v-else class="text-sm text-surface-400">—</span>
                        </template>
                    </Column>

                    <Column field="iva_rate" header="IVA" :style="{ width: '70px' }">
                        <template #body="{ data }">
                            <span class="text-sm text-surface-600">{{ data.iva_rate ?? '—' }}{{ data.iva_rate != null ? '%' : '' }}</span>
                        </template>
                    </Column>

                    <Column field="irpf_rate" header="IRPF" :style="{ width: '80px' }">
                        <template #body="{ data }">
                            <span v-if="data.irpf_rate" class="text-sm text-red-600">−{{ data.irpf_rate }}%</span>
                            <span v-else class="text-sm text-surface-400">—</span>
                        </template>
                    </Column>

                    <Column field="amount" header="Importe" sortable>
                        <template #body="{ data }">
                            <span class="font-semibold tabular-nums" :class="data.kind === 'income' ? 'text-emerald-600' : 'text-red-600'">
                                {{ data.kind === 'income' ? '+' : '−' }} {{ formatEuros(data.amount) }}
                            </span>
                        </template>
                    </Column>

                    <Column :style="{ width: '70px' }">
                        <template #body="{ data }">
                            <Button
                                :icon="data.is_paid ? 'pi pi-undo' : 'pi pi-check'"
                                size="small"
                                :severity="data.is_paid ? 'secondary' : 'success'"
                                text
                                rounded
                                v-tooltip.left="data.is_paid
                                    ? 'Marcar como pendiente'
                                    : data.kind === 'income' ? 'Marcar como cobrado' : 'Marcar como pagado'"
                                @click="togglePaid(data, $event)"
                            />
                        </template>
                    </Column>

                    <Column :style="{ width: '60px' }">
                        <template #body="{ data }">
                            <Button
                                icon="pi pi-pencil"
                                size="small"
                                severity="secondary"
                                text
                                rounded
                                v-tooltip.left="'Editar'"
                                @click.stop="openEdit(data)"
                            />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- Modal create/edit -->
        <Dialog
            v-model:visible="drawerOpen"
            modal
            :style="{ width: '500px' }"
            :pt="{ root: { class: '!rounded-2xl !overflow-hidden' } }"
            :dismissableMask="true"
        >
            <template #header>
                <span class="text-lg font-semibold">
                    {{ editingId ? 'Editar transacción' : form.kind === 'income' ? 'Nuevo ingreso' : 'Nuevo gasto' }}
                </span>
            </template>

            <form class="flex flex-col gap-5" @submit.prevent="save">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Tipo</label>
                    <SelectButton v-model="form.kind" :options="formTypeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
                </div>

                <div class="flex flex-col gap-2">
                    <label for="label" class="text-sm font-medium text-surface-700">
                        {{ form.kind === 'income' ? 'Cliente / descripción' : 'Descripción' }}
                    </label>
                    <InputText
                        id="label"
                        v-model="form.label"
                        :placeholder="form.kind === 'income' ? 'Ej: Factura Acme S.L. — Hito 2' : 'Ej: Alquiler oficina'"
                        fluid
                    />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label for="amount" class="text-sm font-medium text-surface-700">Importe (€)</label>
                        <InputNumber id="amount" v-model="form.amount" :minFractionDigits="2" :maxFractionDigits="2" locale="es-ES" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Fecha</label>
                        <DatePicker v-model="form.estimated_on" dateFormat="dd/mm/yy" fluid />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Categoría</label>
                    <CategorySelect
                        v-model="form.category_id"
                        :options="categoriesForForm"
                        :type="form.kind"
                        @created="addCategoryFromCreate"
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex items-center justify-between rounded-lg border border-surface-200 px-3 py-2">
                        <div>
                            <p class="text-sm font-medium text-surface-700">Con IVA</p>
                            <p class="text-xs text-surface-500">
                                {{ form.kind === 'income'
                                    ? 'Desactivar si facturas sin IVA (UE intracomunitario, exento).'
                                    : 'Desactivar si el proveedor no factura con IVA.' }}
                            </p>
                        </div>
                        <ToggleSwitch v-model="form.has_iva" />
                    </div>
                    <Select
                        v-if="form.has_iva"
                        v-model="form.iva_rate"
                        :options="ivaOptions.filter((o) => o.value > 0)"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Tipo de IVA"
                        fluid
                    />
                </div>

                <div v-if="form.kind === 'income'" class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">IRPF retenido</label>
                    <Select v-model="form.irpf_rate" :options="irpfOptions" optionLabel="label" optionValue="value" fluid />
                    <Message severity="info" size="small" variant="simple">
                        Aplica solo en facturas B2B en España.
                    </Message>
                </div>

                <div class="flex items-center justify-between rounded-lg border border-surface-200 px-3 py-2">
                    <div>
                        <p class="text-sm font-medium text-surface-700">
                            {{ form.kind === 'income' ? 'Cobrado' : 'Pagado' }}
                        </p>
                        <p class="text-xs text-surface-500">
                            {{ form.kind === 'income'
                                ? 'Indica si el cliente ya ha pagado esta factura.'
                                : 'Indica si has pagado este gasto.' }}
                        </p>
                    </div>
                    <ToggleSwitch v-model="form.paid" />
                </div>

                <div class="mt-2 flex gap-2">
                    <Button v-if="editingId" type="button" icon="pi pi-trash" severity="danger" outlined @click="askDelete" />
                    <Button type="button" label="Cancelar" severity="secondary" outlined fluid @click="drawerOpen = false" />
                    <Button type="submit" :label="editingId ? 'Guardar' : 'Crear'" :loading="saving" fluid />
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
:deep(.p-datatable-tbody > tr) {
    cursor: pointer;
}
</style>
