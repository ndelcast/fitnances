<script setup>
import { ref, computed, reactive, onUnmounted, nextTick } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import Popover from 'primevue/popover';
import Drawer from 'primevue/drawer';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import SelectButton from 'primevue/selectbutton';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatEuros } from '@/lib/format';

const props = defineProps({
    year: { type: Number, required: true },
    incomes: { type: Array, required: true },
    expenses: { type: Array, required: true },
    salary: { type: Object, required: true },
    quarterlyTaxes: { type: Object, required: true },
    startingBalance: { type: Number, required: true },
    categories: { type: Array, default: () => [] },
});

const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

// Local mutable state (proto : reset on reload)
const incomesState = reactive(
    props.incomes.map((line) => ({
        client: line.client,
        monthly: [...line.monthly],
        paid: new Array(12).fill(false),
    })),
);

const expensesState = reactive(
    props.expenses.map((line) => ({
        name: line.name,
        category: line.category,
        monthly: [...line.monthly],
    })),
);

// Quarterly taxes paid the following month
const quarterPaymentMonth = [3, 6, 9, null];
const taxesMonthly = (quarterly) => {
    const arr = new Array(12).fill(0);
    quarterly.forEach((amount, q) => {
        const m = quarterPaymentMonth[q];
        if (m !== null) arr[m] = amount;
    });
    return arr;
};
const ivaMonthly = computed(() => taxesMonthly(props.quarterlyTaxes.iva));
const irpfMonthly = computed(() => taxesMonthly(props.quarterlyTaxes.irpf));

const sumRow = (row) => row.reduce((s, v) => s + v, 0);

const incomesByMonth = computed(() =>
    months.map((_, i) => incomesState.reduce((s, l) => s + (l.monthly[i] ?? 0), 0)),
);
const expensesByMonth = computed(() =>
    months.map((_, i) => expensesState.reduce((s, l) => s + (l.monthly[i] ?? 0), 0)),
);
const taxesByMonth = computed(() =>
    months.map((_, i) => ivaMonthly.value[i] + irpfMonthly.value[i]),
);
const monthlyBalance = computed(() =>
    months.map(
        (_, i) =>
            incomesByMonth.value[i] -
            expensesByMonth.value[i] -
            taxesByMonth.value[i] -
            (props.salary.monthly[i] ?? 0),
    ),
);
const cumulativeBalance = computed(() => {
    let acc = props.startingBalance;
    return monthlyBalance.value.map((m) => {
        acc += m;
        return acc;
    });
});

const formatCompact = (value) => {
    if (!value) return '—';
    return formatEuros(value);
};

const currentYear = ref(props.year);
const quarterCols = [2, 5, 8, 11];

/* ---------------------------------------------------------------
 * Cell editor (Popover)
 * --------------------------------------------------------------- */
const popoverRef = ref();
const editingCell = ref(null);
const cellForm = ref({ amount: null, paid: false });

const openCellEditor = (event, section, rowIdx, monthIdx) => {
    if (dragState.value) return;
    const row = section === 'incomes' ? incomesState[rowIdx] : expensesState[rowIdx];
    cellForm.value = {
        amount: row.monthly[monthIdx] || null,
        paid: section === 'incomes' ? row.paid[monthIdx] : false,
    };
    editingCell.value = { section, rowIdx, monthIdx };
    popoverRef.value.show(event);
};

const saveCellEdit = () => {
    if (!editingCell.value) return;
    const { section, rowIdx, monthIdx } = editingCell.value;
    const row = section === 'incomes' ? incomesState[rowIdx] : expensesState[rowIdx];
    row.monthly[monthIdx] = cellForm.value.amount || 0;
    if (section === 'incomes') row.paid[monthIdx] = cellForm.value.paid;
    popoverRef.value.hide();
    editingCell.value = null;
};

const clearCell = () => {
    if (!editingCell.value) return;
    const { section, rowIdx, monthIdx } = editingCell.value;
    const row = section === 'incomes' ? incomesState[rowIdx] : expensesState[rowIdx];
    row.monthly[monthIdx] = 0;
    if (section === 'incomes') row.paid[monthIdx] = false;
    popoverRef.value.hide();
    editingCell.value = null;
};

const editingMonthLabel = computed(() => {
    if (!editingCell.value) return '';
    return `${months[editingCell.value.monthIdx]} ${currentYear.value}`;
});

const editingRowLabel = computed(() => {
    if (!editingCell.value) return '';
    const { section, rowIdx } = editingCell.value;
    return section === 'incomes' ? incomesState[rowIdx].client : expensesState[rowIdx].name;
});

/* ---------------------------------------------------------------
 * Drag-to-fill
 * --------------------------------------------------------------- */
const dragState = ref(null);

const startDrag = (event, section, rowIdx, monthIdx) => {
    event.preventDefault();
    event.stopPropagation();
    const row = section === 'incomes' ? incomesState[rowIdx] : expensesState[rowIdx];
    dragState.value = {
        section,
        rowIdx,
        sourceMonth: monthIdx,
        currentMonth: monthIdx,
        value: row.monthly[monthIdx] || 0,
    };
    window.addEventListener('mouseup', endDrag);
};

const onCellEnter = (section, rowIdx, monthIdx) => {
    if (!dragState.value) return;
    if (dragState.value.section !== section || dragState.value.rowIdx !== rowIdx) return;
    dragState.value.currentMonth = monthIdx;
};

const endDrag = () => {
    if (!dragState.value) {
        window.removeEventListener('mouseup', endDrag);
        return;
    }
    const { section, rowIdx, sourceMonth, currentMonth, value } = dragState.value;
    const start = Math.min(sourceMonth, currentMonth);
    const end = Math.max(sourceMonth, currentMonth);
    const row = section === 'incomes' ? incomesState[rowIdx] : expensesState[rowIdx];
    for (let i = start; i <= end; i++) {
        if (i !== sourceMonth) row.monthly[i] = value;
    }
    dragState.value = null;
    window.removeEventListener('mouseup', endDrag);
};

const isDragHighlighted = (section, rowIdx, monthIdx) => {
    if (!dragState.value) return false;
    if (dragState.value.section !== section || dragState.value.rowIdx !== rowIdx) return false;
    const start = Math.min(dragState.value.sourceMonth, dragState.value.currentMonth);
    const end = Math.max(dragState.value.sourceMonth, dragState.value.currentMonth);
    return monthIdx >= start && monthIdx <= end;
};

onUnmounted(() => window.removeEventListener('mouseup', endDrag));

/* ---------------------------------------------------------------
 * Add new row (Drawer)
 * --------------------------------------------------------------- */
const drawerOpen = ref(false);
const drawerSection = ref(null);
const newRowForm = ref(emptyNewRow());

function emptyNewRow() {
    return {
        name: '',
        category: null,
        amount: null,
        mode: 'monthly',
        singleMonth: 0,
    };
}

const openNewRow = (section) => {
    drawerSection.value = section;
    newRowForm.value = emptyNewRow();
    drawerOpen.value = true;
};

const modeOptions = [
    { label: 'Todos los meses', value: 'monthly' },
    { label: 'Un mes concreto', value: 'single' },
];

const monthOptions = months.map((m, i) => ({ label: m, value: i }));

const saveNewRow = () => {
    const f = newRowForm.value;
    if (!f.name || !f.amount) return;
    const monthly = new Array(12).fill(0);
    if (f.mode === 'monthly') {
        for (let i = 0; i < 12; i++) monthly[i] = f.amount;
    } else {
        monthly[f.singleMonth] = f.amount;
    }
    if (drawerSection.value === 'incomes') {
        incomesState.push({
            client: f.name,
            monthly,
            paid: new Array(12).fill(false),
        });
    } else {
        expensesState.push({
            name: f.name,
            category: f.category ?? 'Otros',
            monthly,
        });
    }
    drawerOpen.value = false;
};

/* ---------------------------------------------------------------
 * Row name inline edit
 * --------------------------------------------------------------- */
const editingRowName = ref(null);
const tempRowName = ref('');
const rowNameInput = ref();

const startRowNameEdit = async (section, rowIdx) => {
    const row = section === 'incomes' ? incomesState[rowIdx] : expensesState[rowIdx];
    tempRowName.value = section === 'incomes' ? row.client : row.name;
    editingRowName.value = { section, rowIdx };
    await nextTick();
    rowNameInput.value?.$el?.querySelector('input')?.focus();
    rowNameInput.value?.$el?.querySelector('input')?.select();
};

const saveRowName = () => {
    if (!editingRowName.value || !tempRowName.value.trim()) {
        editingRowName.value = null;
        return;
    }
    const { section, rowIdx } = editingRowName.value;
    const row = section === 'incomes' ? incomesState[rowIdx] : expensesState[rowIdx];
    if (section === 'incomes') row.client = tempRowName.value.trim();
    else row.name = tempRowName.value.trim();
    editingRowName.value = null;
};

const isEditingRow = (section, rowIdx) =>
    editingRowName.value?.section === section && editingRowName.value?.rowIdx === rowIdx;
</script>

<template>
    <Head title="Flujo de caja" />

    <AppLayout title="Flujo de caja" fluid>
        <div class="flex h-[calc(100vh-4rem)] flex-col">
            <!-- Top controls -->
            <div class="flex-shrink-0 space-y-4 px-4 pt-6 md:px-8">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-3">
                        <Button icon="pi pi-chevron-left" severity="secondary" outlined size="small" @click="currentYear--" />
                        <span class="min-w-[80px] text-center text-xl font-bold text-surface-900">{{ currentYear }}</span>
                        <Button icon="pi pi-chevron-right" severity="secondary" outlined size="small" @click="currentYear++" />
                    </div>
                    <div class="flex gap-2">
                        <Link href="/asistente">
                            <Button label="Reajustar año" icon="pi pi-sparkles" severity="secondary" outlined size="small" />
                        </Link>
                    </div>
                </div>

                <Message v-if="quarterlyTaxes.irpfExempt" severity="info" size="small" variant="simple">
                    Estás exento de Modelo 130 (más del 70% de tus ingresos llevan retención IRPF).
                </Message>

                <p class="text-xs text-surface-400">
                    Click en una celda para editar · Arrastra la esquina inferior derecha para copiar · Click en el nombre para renombrar.
                </p>
            </div>

            <!-- Table scroll area : fills viewport down to bottom -->
            <div class="mt-4 min-h-0 flex-1 overflow-auto border-t border-surface-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-200 bg-surface-50">
                            <th class="sticky left-0 z-10 min-w-[240px] bg-surface-50 px-4 py-3 text-left font-semibold text-surface-700">Concepto</th>
                            <th
                                v-for="(m, i) in months"
                                :key="m"
                                class="min-w-[110px] px-2 py-3 text-right font-semibold uppercase tracking-wider text-surface-500"
                                :class="{ 'bg-emerald-50/40': quarterCols.includes(i) }"
                            >
                                {{ m }}
                            </th>
                            <th class="min-w-[130px] bg-surface-100 px-3 py-3 text-right font-bold text-surface-700">Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        <!-- INGRESOS -->
                        <tr class="bg-emerald-50/30">
                            <td class="sticky left-0 z-10 bg-emerald-50 px-4 py-2" :colspan="14">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Ingresos</span>
                                    <Button
                                        label="Añadir cliente"
                                        icon="pi pi-plus"
                                        severity="success"
                                        text
                                        size="small"
                                        @click="openNewRow('incomes')"
                                    />
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="(line, rowIdx) in incomesState"
                            :key="'inc-' + rowIdx"
                            class="border-b border-surface-100 hover:bg-emerald-50/20"
                        >
                            <td class="sticky left-0 z-10 bg-white px-4 py-2 font-medium text-surface-800">
                                <InputText
                                    v-if="isEditingRow('incomes', rowIdx)"
                                    ref="rowNameInput"
                                    v-model="tempRowName"
                                    size="small"
                                    @blur="saveRowName"
                                    @keydown.enter="saveRowName"
                                    @keydown.esc="editingRowName = null"
                                />
                                <button
                                    v-else
                                    type="button"
                                    class="text-left hover:text-emerald-700"
                                    @click="startRowNameEdit('incomes', rowIdx)"
                                >
                                    {{ line.client }}
                                </button>
                            </td>
                            <td
                                v-for="(v, monthIdx) in line.monthly"
                                :key="monthIdx"
                                class="group relative cursor-pointer px-2 py-2 text-right tabular-nums transition-colors hover:bg-emerald-100/40"
                                :class="[
                                    !v && !line.paid[monthIdx] ? 'text-surface-300' : '',
                                    quarterCols.includes(monthIdx) ? 'bg-emerald-50/30' : '',
                                    isDragHighlighted('incomes', rowIdx, monthIdx) ? 'bg-emerald-200/50 ring-1 ring-inset ring-emerald-400' : '',
                                ]"
                                @click="openCellEditor($event, 'incomes', rowIdx, monthIdx)"
                                @mouseenter="onCellEnter('incomes', rowIdx, monthIdx)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <i
                                        v-if="line.paid[monthIdx] && v"
                                        class="pi pi-check-circle text-[10px] text-emerald-600"
                                    />
                                    <span :class="line.paid[monthIdx] && v ? 'font-semibold text-emerald-700' : ''">
                                        {{ formatCompact(v) }}
                                    </span>
                                </span>
                                <span
                                    v-if="v"
                                    class="fill-handle absolute bottom-0.5 right-0.5 h-2 w-2 cursor-crosshair rounded-sm bg-emerald-600 opacity-0 transition-opacity group-hover:opacity-100"
                                    @mousedown="startDrag($event, 'incomes', rowIdx, monthIdx)"
                                    @click.stop
                                />
                            </td>
                            <td class="bg-surface-50 px-3 py-2 text-right font-semibold tabular-nums text-emerald-700">{{ formatCompact(sumRow(line.monthly)) }}</td>
                        </tr>
                        <tr class="border-b-2 border-emerald-200 bg-emerald-100/40">
                            <td class="sticky left-0 z-10 bg-emerald-100 px-4 py-2 font-semibold text-emerald-800">Total ingresos</td>
                            <td
                                v-for="(v, i) in incomesByMonth"
                                :key="i"
                                class="px-2 py-2 text-right font-semibold tabular-nums text-emerald-800"
                            >
                                {{ formatCompact(v) }}
                            </td>
                            <td class="bg-emerald-200/60 px-3 py-2 text-right font-bold tabular-nums text-emerald-900">{{ formatCompact(sumRow(incomesByMonth)) }}</td>
                        </tr>

                        <!-- GASTOS -->
                        <tr class="bg-red-50/30">
                            <td class="sticky left-0 z-10 bg-red-50 px-4 py-2" :colspan="14">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-red-700">Gastos recurrentes</span>
                                    <Button
                                        label="Añadir gasto"
                                        icon="pi pi-plus"
                                        severity="danger"
                                        text
                                        size="small"
                                        @click="openNewRow('expenses')"
                                    />
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-for="(line, rowIdx) in expensesState"
                            :key="'exp-' + rowIdx"
                            class="border-b border-surface-100 hover:bg-red-50/20"
                        >
                            <td class="sticky left-0 z-10 bg-white px-4 py-2 text-surface-800">
                                <InputText
                                    v-if="isEditingRow('expenses', rowIdx)"
                                    ref="rowNameInput"
                                    v-model="tempRowName"
                                    size="small"
                                    @blur="saveRowName"
                                    @keydown.enter="saveRowName"
                                    @keydown.esc="editingRowName = null"
                                />
                                <button
                                    v-else
                                    type="button"
                                    class="text-left hover:text-red-700"
                                    @click="startRowNameEdit('expenses', rowIdx)"
                                >
                                    <span class="font-medium">{{ line.name }}</span>
                                    <span class="ml-1 text-xs text-surface-400">· {{ line.category }}</span>
                                </button>
                            </td>
                            <td
                                v-for="(v, monthIdx) in line.monthly"
                                :key="monthIdx"
                                class="group relative cursor-pointer px-2 py-2 text-right tabular-nums transition-colors hover:bg-red-100/40"
                                :class="[
                                    !v ? 'text-surface-300' : '',
                                    quarterCols.includes(monthIdx) ? 'bg-red-50/30' : '',
                                    isDragHighlighted('expenses', rowIdx, monthIdx) ? 'bg-red-200/50 ring-1 ring-inset ring-red-400' : '',
                                ]"
                                @click="openCellEditor($event, 'expenses', rowIdx, monthIdx)"
                                @mouseenter="onCellEnter('expenses', rowIdx, monthIdx)"
                            >
                                {{ formatCompact(v) }}
                                <span
                                    v-if="v"
                                    class="fill-handle absolute bottom-0.5 right-0.5 h-2 w-2 cursor-crosshair rounded-sm bg-red-600 opacity-0 transition-opacity group-hover:opacity-100"
                                    @mousedown="startDrag($event, 'expenses', rowIdx, monthIdx)"
                                    @click.stop
                                />
                            </td>
                            <td class="bg-surface-50 px-3 py-2 text-right font-semibold tabular-nums text-red-700">{{ formatCompact(sumRow(line.monthly)) }}</td>
                        </tr>
                        <tr class="border-b-2 border-red-200 bg-red-100/40">
                            <td class="sticky left-0 z-10 bg-red-100 px-4 py-2 font-semibold text-red-800">Total gastos</td>
                            <td
                                v-for="(v, i) in expensesByMonth"
                                :key="i"
                                class="px-2 py-2 text-right font-semibold tabular-nums text-red-800"
                            >
                                {{ formatCompact(v) }}
                            </td>
                            <td class="bg-red-200/60 px-3 py-2 text-right font-bold tabular-nums text-red-900">{{ formatCompact(sumRow(expensesByMonth)) }}</td>
                        </tr>

                        <!-- OBLIGACIONES FISCALES -->
                        <tr class="bg-amber-50/40">
                            <td class="sticky left-0 z-10 bg-amber-50 px-4 py-2 text-xs font-bold uppercase tracking-wider text-amber-700" :colspan="14">
                                Obligaciones fiscales (trimestrales)
                            </td>
                        </tr>
                        <tr class="border-b border-surface-100">
                            <td class="sticky left-0 z-10 bg-white px-4 py-2 text-surface-800">
                                <span class="font-medium">IVA</span>
                                <Tag value="Modelo 303" severity="secondary" class="ml-2 !text-[10px]" />
                            </td>
                            <td
                                v-for="(v, i) in ivaMonthly"
                                :key="i"
                                class="px-2 py-2 text-right tabular-nums"
                                :class="!v ? 'text-surface-300' : ''"
                            >
                                {{ formatCompact(v) }}
                            </td>
                            <td class="bg-surface-50 px-3 py-2 text-right font-semibold tabular-nums text-amber-700">{{ formatCompact(sumRow(ivaMonthly)) }}</td>
                        </tr>
                        <tr v-if="!quarterlyTaxes.irpfExempt" class="border-b border-surface-100">
                            <td class="sticky left-0 z-10 bg-white px-4 py-2 text-surface-800">
                                <span class="font-medium">IRPF (pago fraccionado)</span>
                                <Tag value="Modelo 130" severity="secondary" class="ml-2 !text-[10px]" />
                            </td>
                            <td
                                v-for="(v, i) in irpfMonthly"
                                :key="i"
                                class="px-2 py-2 text-right tabular-nums"
                                :class="!v ? 'text-surface-300' : ''"
                            >
                                {{ formatCompact(v) }}
                            </td>
                            <td class="bg-surface-50 px-3 py-2 text-right font-semibold tabular-nums text-amber-700">{{ formatCompact(sumRow(irpfMonthly)) }}</td>
                        </tr>
                        <tr class="border-b-2 border-amber-200 bg-amber-100/40">
                            <td class="sticky left-0 z-10 bg-amber-100 px-4 py-2 font-semibold text-amber-800">Total fiscal</td>
                            <td
                                v-for="(v, i) in taxesByMonth"
                                :key="i"
                                class="px-2 py-2 text-right font-semibold tabular-nums text-amber-800"
                            >
                                {{ formatCompact(v) }}
                            </td>
                            <td class="bg-amber-200/60 px-3 py-2 text-right font-bold tabular-nums text-amber-900">{{ formatCompact(sumRow(taxesByMonth)) }}</td>
                        </tr>

                        <!-- SALARIO -->
                        <tr class="bg-violet-50/40">
                            <td class="sticky left-0 z-10 bg-violet-50 px-4 py-2 text-xs font-bold uppercase tracking-wider text-violet-700" :colspan="14">
                                Pago a mí mismo
                            </td>
                        </tr>
                        <tr class="border-b-2 border-violet-200 bg-violet-100/30">
                            <td class="sticky left-0 z-10 bg-violet-100 px-4 py-2 font-medium text-violet-900">Salario mensual</td>
                            <td
                                v-for="(v, i) in salary.monthly"
                                :key="i"
                                class="px-2 py-2 text-right font-semibold tabular-nums text-violet-800"
                            >
                                {{ formatCompact(v) }}
                            </td>
                            <td class="bg-violet-200/60 px-3 py-2 text-right font-bold tabular-nums text-violet-900">{{ formatCompact(sumRow(salary.monthly)) }}</td>
                        </tr>

                        <!-- BALANCE -->
                        <tr class="border-b border-surface-200">
                            <td class="sticky left-0 z-10 bg-white px-4 py-3 font-semibold text-surface-700">Saldo del mes</td>
                            <td
                                v-for="(v, i) in monthlyBalance"
                                :key="i"
                                class="px-2 py-3 text-right font-semibold tabular-nums"
                                :class="v >= 0 ? 'text-surface-700' : 'text-red-600'"
                            >
                                {{ formatCompact(v) }}
                            </td>
                            <td class="bg-surface-100 px-3 py-3 text-right font-bold tabular-nums text-surface-900">{{ formatCompact(sumRow(monthlyBalance)) }}</td>
                        </tr>
                        <tr class="bg-surface-50">
                            <td class="sticky left-0 z-10 bg-surface-50 px-4 py-3 font-bold text-surface-900">Saldo acumulado</td>
                            <td
                                v-for="(v, i) in cumulativeBalance"
                                :key="i"
                                class="px-2 py-3 text-right font-bold tabular-nums"
                                :class="v >= 0 ? 'text-emerald-700' : 'text-red-700'"
                            >
                                {{ formatCompact(v) }}
                            </td>
                            <td class="bg-surface-200/80 px-3 py-3 text-right font-bold tabular-nums text-surface-900">
                                {{ formatCompact(cumulativeBalance[11]) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Popover: cell editor -->
        <Popover ref="popoverRef" class="!w-64">
            <div class="flex flex-col gap-3 p-1">
                <div>
                    <p class="text-xs uppercase tracking-wider text-surface-500">{{ editingRowLabel }}</p>
                    <p class="text-sm font-semibold text-surface-900">{{ editingMonthLabel }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-surface-600">Importe (€)</label>
                    <InputNumber
                        v-model="cellForm.amount"
                        :minFractionDigits="0"
                        :maxFractionDigits="2"
                        locale="es-ES"
                        suffix=" €"
                        autofocus
                        fluid
                        @keydown.enter="saveCellEdit"
                    />
                </div>

                <div
                    v-if="editingCell?.section === 'incomes'"
                    class="flex items-center justify-between rounded-md bg-emerald-50 px-3 py-2"
                >
                    <span class="text-sm font-medium text-emerald-800">Cobrado</span>
                    <ToggleSwitch v-model="cellForm.paid" />
                </div>

                <div class="flex gap-2">
                    <Button
                        v-if="editingCell?.rowIdx !== null && cellForm.amount"
                        type="button"
                        icon="pi pi-trash"
                        severity="secondary"
                        text
                        size="small"
                        v-tooltip="'Vaciar celda'"
                        @click="clearCell"
                    />
                    <Button label="Guardar" size="small" fluid @click="saveCellEdit" />
                </div>
            </div>
        </Popover>

        <!-- Drawer: nueva fila -->
        <Drawer v-model:visible="drawerOpen" position="right" class="!w-full md:!w-[420px]">
            <template #header>
                <span class="text-lg font-semibold">
                    {{ drawerSection === 'incomes' ? 'Añadir cliente' : 'Añadir gasto' }}
                </span>
            </template>

            <form class="flex flex-col gap-5" @submit.prevent="saveNewRow">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">
                        {{ drawerSection === 'incomes' ? 'Cliente' : 'Nombre del gasto' }}
                    </label>
                    <InputText
                        v-model="newRowForm.name"
                        :placeholder="drawerSection === 'incomes' ? 'Ej: Cliente Acme S.L.' : 'Ej: Coworking'"
                        fluid
                    />
                </div>

                <div v-if="drawerSection === 'expenses'" class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Categoría</label>
                    <Select v-model="newRowForm.category" :options="categories" placeholder="Selecciona categoría" fluid />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Importe (€)</label>
                    <InputNumber
                        v-model="newRowForm.amount"
                        :minFractionDigits="0"
                        :maxFractionDigits="2"
                        locale="es-ES"
                        suffix=" €"
                        fluid
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Frecuencia</label>
                    <SelectButton
                        v-model="newRowForm.mode"
                        :options="modeOptions"
                        optionLabel="label"
                        optionValue="value"
                        :allowEmpty="false"
                    />
                </div>

                <div v-if="newRowForm.mode === 'single'" class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Mes</label>
                    <Select v-model="newRowForm.singleMonth" :options="monthOptions" optionLabel="label" optionValue="value" fluid />
                </div>

                <div class="mt-2 flex gap-2">
                    <Button type="button" label="Cancelar" severity="secondary" outlined fluid @click="drawerOpen = false" />
                    <Button type="submit" label="Añadir" fluid />
                </div>
            </form>
        </Drawer>
    </AppLayout>
</template>

<style scoped>
.fill-handle {
    box-shadow: 0 0 0 1px white;
}
</style>
