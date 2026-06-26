<script setup>
import { ref, computed, reactive } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch';
import Message from 'primevue/message';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategorySelect from '@/Components/CategorySelect.vue';
import { formatEuros } from '@/lib/format';

const props = defineProps({
    charges: { type: Array, required: true },
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

const filterSearch = ref('');
const filterCategoryId = ref(null);
const filterStatus = ref(null);

const statusOptions = [
    { label: 'Todos', value: null },
    { label: 'Activos', value: true },
    { label: 'Inactivos', value: false },
];

const categoryOptionsForFilter = computed(() => [
    { label: 'Todas las categorías', value: null },
    ...categoriesState.map((c) => ({ label: c.name, value: c.id })),
]);

const filteredCharges = computed(() =>
    props.charges.filter((c) => {
        if (filterStatus.value !== null && c.is_active !== filterStatus.value) return false;
        if (filterCategoryId.value && c.category_id !== filterCategoryId.value) return false;
        if (filterSearch.value && !c.label.toLowerCase().includes(filterSearch.value.toLowerCase())) return false;
        return true;
    }),
);

const totalMonthly = computed(() =>
    filteredCharges.value.filter((c) => c.is_active).reduce((s, c) => s + monthlyEquivalent(c), 0),
);

const totalAnnual = computed(() => totalMonthly.value * 12);

const drawerOpen = ref(false);
const editingId = ref(null);
const saving = ref(false);
const form = ref(emptyForm());

function emptyForm() {
    return {
        label: '',
        amount: null,
        frequency: 'monthly',
        category_id: null,
        day_of_month: 1,
        is_active: true,
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
        label: charge.label,
        amount: charge.amount,
        frequency: charge.frequency,
        category_id: charge.category_id,
        day_of_month: charge.day_of_month ?? 1,
        is_active: charge.is_active,
    };
    drawerOpen.value = true;
};

const save = () => {
    saving.value = true;
    const payload = { ...form.value };
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
        router.put(`/cargos-recurrentes/${editingId.value}`, payload, options);
    } else {
        router.post('/cargos-recurrentes', payload, options);
    }
};

const toggleCharge = (charge, event) => {
    event.stopPropagation();
    router.patch(`/cargos-recurrentes/${charge.id}/toggle`, {}, { preserveScroll: true });
};

const askDelete = (event) => {
    if (!editingId.value) return;
    confirm.require({
        target: event.currentTarget,
        message: '¿Eliminar este cargo recurrente?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar',
        rejectLabel: 'Cancelar',
        acceptClass: 'p-button-danger',
        accept: () => {
            const id = editingId.value;
            router.delete(`/cargos-recurrentes/${id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    drawerOpen.value = false;
                },
            });
        },
    });
};

const frequencySeverity = (frequency) => {
    if (frequency === 'monthly') return 'info';
    if (frequency === 'quarterly') return 'warn';
    return 'secondary';
};

const flash = computed(() => page.props.flash);
</script>

<template>
    <Head title="Cargos recurrentes" />
    <ConfirmPopup />

    <AppLayout title="Cargos recurrentes">
        <div class="mx-auto max-w-7xl space-y-6">
            <Message v-if="flash?.success" severity="success" :closable="true">{{ flash.success }}</Message>

            <p class="text-sm text-surface-500">Gastos fijos que se repiten cada mes, trimestre o año. Se proyectan automáticamente en el flujo de caja.</p>

            <!-- KPI strip -->
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Equivalente mensual</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ formatEuros(totalMonthly) }}</p>
                </div>
                <div class="rounded-lg border border-surface-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-surface-500">Equivalente anual</p>
                    <p class="mt-1 text-2xl font-bold text-surface-900">{{ formatEuros(totalAnnual) }}</p>
                </div>
                <div class="rounded-lg border border-surface-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-surface-500">Cargos activos</p>
                    <p class="mt-1 text-2xl font-bold text-surface-900">
                        {{ filteredCharges.filter((c) => c.is_active).length }}
                        <span class="text-base font-normal text-surface-400">/ {{ filteredCharges.length }}</span>
                    </p>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <IconField iconPosition="left">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="filterSearch" placeholder="Buscar..." />
                    </IconField>
                    <Select v-model="filterCategoryId" :options="categoryOptionsForFilter" optionLabel="label" optionValue="value" placeholder="Categoría" />
                    <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Estado" />
                </div>
                <Button label="Nuevo cargo" icon="pi pi-plus" @click="openCreate" />
            </div>

            <!-- Table -->
            <div class="rounded-lg border border-surface-200 bg-white">
                <DataTable
                    :value="filteredCharges"
                    :rows="15"
                    paginator
                    stripedRows
                    rowHover
                    sortMode="single"
                    @row-click="(e) => openEdit(e.data)"
                >
                    <template #empty>
                        <div class="py-10 text-center">
                            <i class="pi pi-replay mb-3 block text-4xl text-surface-300" />
                            <p class="font-medium text-surface-700">No hay cargos recurrentes</p>
                            <p class="mt-1 text-sm text-surface-500">Añade tu primer cargo fijo: alquiler, cuota de autónomos, software...</p>
                            <Button label="Crear primer cargo" icon="pi pi-plus" class="mt-4" @click="openCreate" />
                        </div>
                    </template>

                    <Column field="is_active" header="" :style="{ width: '50px' }">
                        <template #body="{ data }">
                            <Button
                                :icon="data.is_active ? 'pi pi-check-circle' : 'pi pi-circle'"
                                :severity="data.is_active ? 'success' : 'secondary'"
                                text
                                rounded
                                size="small"
                                v-tooltip="data.is_active ? 'Activo (clic para desactivar)' : 'Inactivo (clic para activar)'"
                                @click="toggleCharge(data, $event)"
                            />
                        </template>
                    </Column>

                    <Column field="label" header="Nombre" sortable>
                        <template #body="{ data }">
                            <span class="font-medium text-surface-900" :class="{ 'opacity-50': !data.is_active }">{{ data.label }}</span>
                        </template>
                    </Column>

                    <Column field="category_name" header="Categoría">
                        <template #body="{ data }">
                            <Tag v-if="data.category_name" :value="data.category_name" severity="secondary" />
                            <span v-else class="text-sm text-surface-400">—</span>
                        </template>
                    </Column>

                    <Column field="frequency" header="Frecuencia" sortable>
                        <template #body="{ data }">
                            <Tag :value="frequencyLabel(data.frequency)" :severity="frequencySeverity(data.frequency)" />
                        </template>
                    </Column>

                    <Column field="day_of_month" header="Día" sortable :style="{ width: '70px' }">
                        <template #body="{ data }">
                            <span class="text-sm text-surface-600">{{ data.day_of_month }}</span>
                        </template>
                    </Column>

                    <Column field="amount" header="Importe" sortable>
                        <template #body="{ data }">
                            <span class="font-semibold tabular-nums text-surface-900" :class="{ 'opacity-50': !data.is_active }">
                                {{ formatEuros(data.amount) }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Equiv. mensual">
                        <template #body="{ data }">
                            <span class="tabular-nums" :class="data.frequency === 'monthly' ? 'text-surface-400' : 'text-emerald-700'">
                                {{ formatEuros(monthlyEquivalent(data)) }}
                            </span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Dialog
            v-model:visible="drawerOpen"
            modal
            :style="{ width: '460px' }"
            :pt="{ root: { class: '!rounded-2xl !overflow-hidden' } }"
            :dismissableMask="true"
        >
            <template #header>
                <span class="text-lg font-semibold">
                    {{ editingId ? 'Editar cargo' : 'Nuevo cargo recurrente' }}
                </span>
            </template>

            <form class="flex flex-col gap-5" @submit.prevent="save">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Nombre</label>
                    <InputText v-model="form.label" placeholder="Ej: Alquiler oficina" fluid />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Importe (€)</label>
                        <InputNumber v-model="form.amount" :minFractionDigits="2" :maxFractionDigits="2" locale="es-ES" fluid />
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Día del mes</label>
                        <InputNumber v-model="form.day_of_month" :min="1" :max="31" fluid />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Frecuencia</label>
                    <Select v-model="form.frequency" :options="frequencyOptions" optionLabel="label" optionValue="value" fluid />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Categoría</label>
                    <CategorySelect
                        v-model="form.category_id"
                        :options="categoriesState"
                        type="expense"
                        @created="addCategoryFromCreate"
                    />
                </div>

                <div class="flex items-center justify-between rounded-lg border border-surface-200 p-3">
                    <div>
                        <p class="text-sm font-medium text-surface-700">Activo</p>
                        <p class="text-xs text-surface-500">Se incluye en la previsión y en el flujo de caja</p>
                    </div>
                    <ToggleSwitch v-model="form.is_active" />
                </div>

                <Message v-if="form.frequency !== 'monthly'" severity="info" size="small" variant="simple">
                    Equivalente mensual: <strong>{{ formatEuros((form.amount || 0) / (form.frequency === 'quarterly' ? 3 : 12)) }}</strong>
                </Message>

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
