<script setup>
import { ref, computed, reactive, onUnmounted, nextTick, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import Popover from 'primevue/popover';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import SelectButton from 'primevue/selectbutton';
import AppLayout from '@/Layouts/AppLayout.vue';
import CategorySelect from '@/Components/CategorySelect.vue';
import { formatEuros } from '@/lib/format';

const props = defineProps({
    year: { type: Number, required: true },
    incomes: { type: Array, required: true },
    expenses: { type: Array, required: true },
    salary: { type: Object, required: true },
    quarterlyTaxes: { type: Object, required: true },
    taxRates: {
        type: Object,
        default: () => ({ iva: 21, irpf: 15, modelo130: 20 }),
    },
    startingBalance: { type: Number, required: true },
    irpfExempt: { type: Boolean, default: false },
    categories: { type: Array, default: () => [] }, // [{id, name, type}]
    years: { type: Array, default: () => [] },
});

const page = usePage();

const categoriesState = reactive([...props.categories]);
const addCategoryFromCreate = (created) => {
    if (!categoriesState.find((c) => c.id === created.id)) {
        categoriesState.push(created);
    }
};
const categoryName = (id) => categoriesState.find((c) => c.id === id)?.name ?? '';

const expenseCategories = computed(() => categoriesState.filter((c) => c.type === 'expense'));

const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

const today = new Date();
const todayMonth = computed(() => (props.year === today.getFullYear() ? today.getMonth() : -1));

const incomesState = reactive(
    props.incomes.map((line) => ({
        id: line.id,
        label: line.clientName || line.label,
        categoryId: line.categoryId,
        hasIva: line.hasIva ?? true,
        monthly: [...line.monthly],
        paid: [...line.paid],
    })),
);

const expensesState = reactive(
    props.expenses.map((line) => ({
        id: line.id,
        label: line.label,
        categoryId: line.categoryId,
        hasIva: line.hasIva ?? true,
        monthly: [...line.monthly],
        paid: [...line.paid],
    })),
);

const salaryState = reactive({
    id: props.salary.id,
    label: props.salary.label || 'Salario',
    monthly: [...props.salary.monthly],
    paid: [...props.salary.paid],
});

const isCurrentMonth = (i) => i === todayMonth.value;
const isPastMonth = (i) => todayMonth.value >= 0 && i < todayMonth.value;

// IVA et IRPF se paient le mois suivant la fin de chaque trimestre :
// Q1 → Abr, Q2 → Jul, Q3 → Oct. Le Q4 est déclaré en janvier N+1 (hors année).
const quarterPaymentMonth = [3, 6, 9, null];

// Somme TTC sur une fenêtre de mois, filtrée par "con IVA" si demandé.
const sumQuarterTtc = (rows, monthsRange, withIvaOnly = true) =>
    rows.reduce((sum, row) => {
        if (withIvaOnly && row.hasIva === false) return sum;
        return sum + monthsRange.reduce((s, m) => s + (row.monthly[m] ?? 0), 0);
    }, 0);

const extractIva = (ttc, rate) => (rate > 0 ? (ttc * rate) / (1 + rate) : 0);

// IVA dû par trimestre = repercutido (sur ingresos) - soportado (sur gastos).
const ivaState = computed(() => {
    const rate = (props.taxRates.iva ?? 0) / 100;
    return [0, 1, 2, 3].map((q) => {
        const range = [q * 3, q * 3 + 1, q * 3 + 2];
        const incomeTtc = sumQuarterTtc(incomesState, range);
        const expenseTtc = sumQuarterTtc(expensesState, range);
        const repercutido = extractIva(incomeTtc, rate);
        const soportado = extractIva(expenseTtc, rate);
        return Math.max(0, Math.round((repercutido - soportado) * 100) / 100);
    });
});

// IRPF (Modelo 130) = 20 % du rendimiento neto HT - retenciones déjà appliquées.
// Toutes les lignes comptent dans le rendimiento neto (avec ou sans IVA),
// mais l'extraction HT n'a lieu que pour celles avec IVA.
const irpfState = computed(() => {
    const ivaRate = (props.taxRates.iva ?? 0) / 100;
    const irpfRetentionRate = (props.taxRates.irpf ?? 0) / 100;
    const modelo130Rate = (props.taxRates.modelo130 ?? 20) / 100;

    return [0, 1, 2, 3].map((q) => {
        const range = [q * 3, q * 3 + 1, q * 3 + 2];
        const incomeTtcWithIva = sumQuarterTtc(incomesState, range, true);
        const incomeTtcNoIva = sumQuarterTtc(incomesState, range, false) - incomeTtcWithIva;
        const expenseTtcWithIva = sumQuarterTtc(expensesState, range, true);
        const expenseTtcNoIva = sumQuarterTtc(expensesState, range, false) - expenseTtcWithIva;

        const incomeHt = incomeTtcWithIva - extractIva(incomeTtcWithIva, ivaRate) + incomeTtcNoIva;
        const expenseHt = expenseTtcWithIva - extractIva(expenseTtcWithIva, ivaRate) + expenseTtcNoIva;

        const netRendimiento = Math.max(0, incomeHt - expenseHt);
        const base = netRendimiento * modelo130Rate;
        const retenido = incomeHt * irpfRetentionRate;
        return Math.max(0, Math.round((base - retenido) * 100) / 100);
    });
});

const taxesMonthly = (quarterly) => {
    const arr = new Array(12).fill(0);
    quarterly.forEach((amount, q) => {
        const m = quarterPaymentMonth[q];
        if (m !== null) arr[m] = amount;
    });
    return arr;
};
const ivaMonthly = computed(() => taxesMonthly(ivaState.value));
const irpfMonthly = computed(() => taxesMonthly(irpfState.value));

const sumRow = (row) => row.reduce((s, v) => s + (v || 0), 0);

const incomesByMonth = computed(() =>
    months.map((_, i) => incomesState.reduce((s, l) => s + (l.monthly[i] ?? 0), 0)),
);
const incomesRealizedByMonth = computed(() =>
    months.map((_, i) => incomesState.reduce((s, l) => s + (l.paid[i] ? (l.monthly[i] ?? 0) : 0), 0)),
);
const expensesByMonth = computed(() =>
    months.map((_, i) => expensesState.reduce((s, l) => s + (l.monthly[i] ?? 0), 0)),
);
const expensesPaidByMonth = computed(() =>
    months.map((_, i) => expensesState.reduce((s, l) => s + (l.paid[i] ? (l.monthly[i] ?? 0) : 0), 0)),
);

const isVencido = (section, rowIdx, monthIdx) => {
    if (!isPastMonth(monthIdx)) return false;
    const row = section === 'incomes' ? incomesState[rowIdx] : expensesState[rowIdx];
    const amount = row.monthly[monthIdx];
    if (!amount) return false;
    return !row.paid[monthIdx];
};

const taxesByMonth = computed(() => months.map((_, i) => ivaMonthly.value[i] + irpfMonthly.value[i]));

const monthlyBalance = computed(() =>
    months.map(
        (_, i) =>
            incomesByMonth.value[i] -
            expensesByMonth.value[i] -
            taxesByMonth.value[i] -
            (salaryState.monthly[i] ?? 0),
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

const quarterCols = [2, 5, 8, 11];

/* ---------------------------------------------------------------
 * Save debouncé
 * --------------------------------------------------------------- */
const saving = ref(false);
const lastSavedAt = ref(null);
let saveTimer = null;

const buildPayload = () => ({
    startingBalance: props.startingBalance,
    irpfExempt: props.irpfExempt,
    incomes: incomesState.map((r) => ({
        id: r.id,
        label: r.label,
        clientName: r.label,
        categoryId: r.categoryId,
        hasIva: r.hasIva,
        monthly: r.monthly.map((v) => Number(v) || 0),
        paid: r.paid.map((p) => !!p),
    })),
    expenses: expensesState.map((r) => ({
        id: r.id,
        label: r.label,
        categoryId: r.categoryId,
        hasIva: r.hasIva,
        monthly: r.monthly.map((v) => Number(v) || 0),
        paid: r.paid.map((p) => !!p),
    })),
    salary: {
        id: salaryState.id,
        label: salaryState.label,
        monthly: salaryState.monthly.map((v) => Number(v) || 0),
        paid: salaryState.paid.map((p) => !!p),
    },
    // Les trimestres IVA/IRPF sont désormais dérivés du tableau, pas persistés.
});

const persistNow = () => {
    saving.value = true;
    router.put(`/flujo-caja/${props.year}`, buildPayload(), {
        preserveScroll: true,
        preserveState: true,
        only: [],
        onSuccess: () => {
            lastSavedAt.value = new Date();
        },
        onFinish: () => {
            saving.value = false;
        },
    });
};

const scheduleSave = () => {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(persistNow, 800);
};

// Auto-save sur tout changement profond.
watch([incomesState, expensesState, salaryState], scheduleSave, { deep: true });

const saveStateLabel = computed(() => {
    if (saving.value) return 'Guardando...';
    if (lastSavedAt.value) return 'Guardado';
    return '';
});

/* ---------------------------------------------------------------
 * Year navigation
 * --------------------------------------------------------------- */
const goToYear = (year) => {
    if (year === props.year) return;
    router.get('/flujo-caja', { year }, { preserveScroll: true });
};

/* ---------------------------------------------------------------
 * Cell editor (Popover)
 * --------------------------------------------------------------- */
const popoverRef = ref();
const editingCell = ref(null);
const cellForm = ref({ amount: null, paid: false });

const openCellEditor = (event, section, rowIdx, monthIdx) => {
    if (dragState.value) return;
    const row = rowFor(section, rowIdx);
    cellForm.value = {
        amount: row.monthly[monthIdx] || null,
        paid: row.paid[monthIdx] ?? false,
    };
    editingCell.value = { section, rowIdx, monthIdx };
    popoverRef.value.show(event);
};

const rowFor = (section, rowIdx) => {
    if (section === 'incomes') return incomesState[rowIdx];
    if (section === 'expenses') return expensesState[rowIdx];
    return salaryState;
};

const saveCellEdit = () => {
    if (!editingCell.value) return;
    const { section, rowIdx, monthIdx } = editingCell.value;
    const row = rowFor(section, rowIdx);
    row.monthly[monthIdx] = cellForm.value.amount || 0;
    row.paid[monthIdx] = cellForm.value.paid;
    popoverRef.value.hide();
    editingCell.value = null;
};

const clearCell = () => {
    if (!editingCell.value) return;
    const { section, rowIdx, monthIdx } = editingCell.value;
    const row = rowFor(section, rowIdx);
    row.monthly[monthIdx] = 0;
    row.paid[monthIdx] = false;
    popoverRef.value.hide();
    editingCell.value = null;
};

const editingMonthLabel = computed(() => {
    if (!editingCell.value) return '';
    return `${months[editingCell.value.monthIdx]} ${props.year}`;
});

const editingRowLabel = computed(() => {
    if (!editingCell.value) return '';
    const { section, rowIdx } = editingCell.value;
    return rowFor(section, rowIdx).label;
});

/* ---------------------------------------------------------------
 * Drag-to-fill
 * --------------------------------------------------------------- */
const dragState = ref(null);

const startDrag = (event, section, rowIdx, monthIdx) => {
    event.preventDefault();
    event.stopPropagation();
    const row = rowFor(section, rowIdx);
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
    const row = rowFor(section, rowIdx);
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
        label: '',
        categoryId: null,
        hasIva: true,
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
    if (!f.label || !f.amount) return;
    const monthly = new Array(12).fill(0);
    if (f.mode === 'monthly') {
        for (let i = 0; i < 12; i++) monthly[i] = f.amount;
    } else {
        monthly[f.singleMonth] = f.amount;
    }
    if (drawerSection.value === 'incomes') {
        incomesState.push({
            id: null,
            label: f.label,
            categoryId: f.categoryId,
            hasIva: f.hasIva,
            monthly,
            paid: new Array(12).fill(false),
        });
    } else {
        expensesState.push({
            id: null,
            label: f.label,
            categoryId: f.categoryId,
            hasIva: f.hasIva,
            monthly,
            paid: new Array(12).fill(false),
        });
    }
    drawerOpen.value = false;
};

/* ---------------------------------------------------------------
 * Row deletion (modal dialog)
 * --------------------------------------------------------------- */
const deleteDialog = ref({
    visible: false,
    section: null,
    rowIdx: null,
    label: '',
    sublabel: '',
    total: 0,
    filledMonths: 0,
    realizedCount: 0,
});

const confirmDeleteRow = (event, section, rowIdx) => {
    event.stopPropagation();
    const row = rowFor(section, rowIdx);
    const label = row.label;
    const sublabel = section === 'expenses' ? categoryName(row.categoryId) : '';
    const total = row.monthly.reduce((s, v) => s + (v || 0), 0);
    const filledMonths = row.monthly.filter((v) => v > 0).length;
    const realizedCount = row.monthly.reduce((s, v, i) => s + (v > 0 && row.paid[i] ? 1 : 0), 0);
    deleteDialog.value = {
        visible: true,
        section,
        rowIdx,
        label,
        sublabel,
        total,
        filledMonths,
        realizedCount,
    };
};

const executeDelete = () => {
    const { section, rowIdx } = deleteDialog.value;
    if (section === 'incomes') incomesState.splice(rowIdx, 1);
    else expensesState.splice(rowIdx, 1);
    deleteDialog.value.visible = false;
};

/* ---------------------------------------------------------------
 * Row name inline edit
 * --------------------------------------------------------------- */
const editingRowName = ref(null);
const tempRowName = ref('');
const rowNameInput = ref();

const startRowNameEdit = async (section, rowIdx) => {
    const row = rowFor(section, rowIdx);
    tempRowName.value = row.label;
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
    rowFor(section, rowIdx).label = tempRowName.value.trim();
    editingRowName.value = null;
};

const isEditingRow = (section, rowIdx) =>
    editingRowName.value?.section === section && editingRowName.value?.rowIdx === rowIdx;

const flash = computed(() => page.props.flash);
</script>

<template>
    <Head title="Flujo de caja" />

    <AppLayout title="Flujo de caja" fluid>
        <div class="flex h-[calc(100vh-4rem)] flex-col">
            <!-- Top controls -->
            <div class="flex-shrink-0 space-y-4 px-4 pt-6 md:px-8">
                <Message v-if="flash?.success" severity="success" :closable="true" :life="2500">{{ flash.success }}</Message>

                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-3">
                        <Button icon="pi pi-chevron-left" severity="secondary" outlined size="small" @click="goToYear(year - 1)" />
                        <span class="min-w-[80px] text-center text-xl font-bold text-surface-900">{{ year }}</span>
                        <Button icon="pi pi-chevron-right" severity="secondary" outlined size="small" @click="goToYear(year + 1)" />
                        <span v-if="saveStateLabel" class="ml-3 text-xs" :class="saving ? 'text-amber-600' : 'text-emerald-600'">
                            <i class="pi" :class="saving ? 'pi-spin pi-spinner' : 'pi-check'" />
                            {{ saveStateLabel }}
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <Link href="/asistente">
                            <Button label="Reajustar año" icon="pi pi-sparkles" severity="secondary" outlined size="small" />
                        </Link>
                    </div>
                </div>

                <Message v-if="irpfExempt" severity="info" size="small" variant="simple">
                    Estás exento de Modelo 130 (más del 70% de tus ingresos llevan retención IRPF).
                </Message>

                <p class="text-xs text-surface-400">
                    Click en una celda para editar · Arrastra la esquina inferior derecha para copiar · Click en el nombre para renombrar.
                </p>
            </div>

            <!-- Table scroll area -->
            <div class="mt-4 min-h-0 flex-1 overflow-auto border-t border-surface-200 bg-white">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-200 bg-surface-50">
                            <th class="sticky left-0 z-10 min-w-[240px] bg-surface-50 px-4 py-3 text-left font-semibold text-surface-700">Concepto</th>
                            <th
                                v-for="(m, i) in months"
                                :key="m"
                                class="min-w-[110px] px-2 py-3 text-right font-semibold uppercase tracking-wider"
                                :class="[
                                    isCurrentMonth(i) ? 'bg-sky-100 text-sky-800' : quarterCols.includes(i) ? 'bg-emerald-50/40 text-surface-500' : 'text-surface-500',
                                ]"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    <span v-if="isCurrentMonth(i)" class="rounded-full bg-sky-600 px-1.5 py-px text-[9px] font-bold uppercase text-white">Hoy</span>
                                    {{ m }}
                                </span>
                            </th>
                            <th class="min-w-[130px] bg-surface-100 px-3 py-3 text-right font-bold text-surface-700">Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        <!-- INGRESOS -->
                        <tr class="bg-emerald-50/30">
                            <td class="sticky left-0 z-10 bg-emerald-50 px-4 py-2" :colspan="14">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Ingresos</span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 shadow-sm transition-all hover:border-emerald-400 hover:bg-emerald-50 hover:shadow"
                                        @click="openNewRow('incomes')"
                                    >
                                        <i class="pi pi-plus text-[10px]" />
                                        Añadir cliente
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="(line, rowIdx) in incomesState" :key="'inc-' + rowIdx" class="border-b border-surface-100 hover:bg-emerald-50/20">
                            <td class="group/row sticky left-0 z-10 bg-white px-4 py-2 font-medium text-surface-800">
                                <div class="flex items-center justify-between">
                                    <InputText v-if="isEditingRow('incomes', rowIdx)" ref="rowNameInput" v-model="tempRowName" size="small" @blur="saveRowName" @keydown.enter="saveRowName" @keydown.esc="editingRowName = null" />
                                    <button v-else type="button" class="flex-1 text-left hover:text-emerald-700" @click="startRowNameEdit('incomes', rowIdx)">
                                        {{ line.label }}
                                        <span
                                            v-if="!line.hasIva"
                                            v-tooltip="'Sin IVA — no entra en Modelo 303'"
                                            class="ml-1.5 inline-flex items-center rounded bg-surface-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-surface-500"
                                        >sin IVA</span>
                                    </button>
                                    <button v-if="!isEditingRow('incomes', rowIdx)" type="button" class="ml-2 rounded p-1 text-surface-400 opacity-0 transition-opacity hover:bg-red-50 hover:text-red-600 group-hover/row:opacity-100" v-tooltip.left="'Eliminar línea'" @click="confirmDeleteRow($event, 'incomes', rowIdx)">
                                        <i class="pi pi-trash text-xs" />
                                    </button>
                                </div>
                            </td>
                            <td
                                v-for="(v, monthIdx) in line.monthly"
                                :key="monthIdx"
                                class="group relative cursor-pointer px-2 py-2 text-right tabular-nums transition-colors hover:bg-emerald-100/40"
                                :class="[
                                    !v && !line.paid[monthIdx] ? 'text-surface-300' : '',
                                    isCurrentMonth(monthIdx) ? 'bg-sky-50/50' : quarterCols.includes(monthIdx) ? 'bg-emerald-50/30' : '',
                                    isVencido('incomes', rowIdx, monthIdx) ? 'bg-red-50/60' : '',
                                    isDragHighlighted('incomes', rowIdx, monthIdx) ? 'bg-emerald-200/50 ring-1 ring-inset ring-emerald-400' : '',
                                ]"
                                @click="openCellEditor($event, 'incomes', rowIdx, monthIdx)"
                                @mouseenter="onCellEnter('incomes', rowIdx, monthIdx)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <i v-if="isVencido('incomes', rowIdx, monthIdx)" v-tooltip="'Vencido sin cobrar'" class="pi pi-exclamation-circle text-[10px] text-red-600" />
                                    <i v-else-if="line.paid[monthIdx] && v" class="pi pi-check-circle text-[10px] text-emerald-600" />
                                    <span :class="[isVencido('incomes', rowIdx, monthIdx) ? 'font-medium text-red-700' : '', line.paid[monthIdx] && v ? 'font-semibold text-emerald-700' : '']">
                                        {{ formatCompact(v) }}
                                    </span>
                                </span>
                                <span v-if="v" class="fill-handle absolute bottom-0.5 right-0.5 h-2 w-2 cursor-crosshair rounded-sm bg-emerald-600 opacity-0 transition-opacity group-hover:opacity-100" @mousedown="startDrag($event, 'incomes', rowIdx, monthIdx)" @click.stop />
                            </td>
                            <td class="bg-surface-50 px-3 py-2 text-right font-semibold tabular-nums text-emerald-700">{{ formatCompact(sumRow(line.monthly)) }}</td>
                        </tr>
                        <tr class="border-b border-emerald-100">
                            <td class="sticky left-0 z-10 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
                                <i class="pi pi-check-circle mr-1 text-xs" />Cobrado
                            </td>
                            <td v-for="(v, i) in incomesRealizedByMonth" :key="i" class="px-2 py-2 text-right tabular-nums text-emerald-700" :class="!v ? 'text-emerald-700/40' : ''">{{ formatCompact(v) }}</td>
                            <td class="bg-emerald-50 px-3 py-2 text-right font-semibold tabular-nums text-emerald-700">{{ formatCompact(sumRow(incomesRealizedByMonth)) }}</td>
                        </tr>
                        <tr class="border-b-2 border-emerald-200 bg-emerald-100/40">
                            <td class="sticky left-0 z-10 bg-emerald-100 px-4 py-2 font-semibold text-emerald-800">Total previsto</td>
                            <td v-for="(v, i) in incomesByMonth" :key="i" class="px-2 py-2 text-right font-semibold tabular-nums text-emerald-800">{{ formatCompact(v) }}</td>
                            <td class="bg-emerald-200/60 px-3 py-2 text-right font-bold tabular-nums text-emerald-900">{{ formatCompact(sumRow(incomesByMonth)) }}</td>
                        </tr>

                        <!-- GASTOS -->
                        <tr class="bg-red-50/30">
                            <td class="sticky left-0 z-10 bg-red-50 px-4 py-2" :colspan="14">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-bold uppercase tracking-wider text-red-700">Gastos</span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-white px-2.5 py-1 text-xs font-semibold text-red-700 shadow-sm transition-all hover:border-red-400 hover:bg-red-50 hover:shadow"
                                        @click="openNewRow('expenses')"
                                    >
                                        <i class="pi pi-plus text-[10px]" />
                                        Añadir gasto
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="(line, rowIdx) in expensesState" :key="'exp-' + rowIdx" class="border-b border-surface-100 hover:bg-red-50/20">
                            <td class="group/row sticky left-0 z-10 bg-white px-4 py-2 text-surface-800">
                                <div class="flex items-center justify-between">
                                    <InputText v-if="isEditingRow('expenses', rowIdx)" ref="rowNameInput" v-model="tempRowName" size="small" @blur="saveRowName" @keydown.enter="saveRowName" @keydown.esc="editingRowName = null" />
                                    <button v-else type="button" class="flex-1 text-left hover:text-red-700" @click="startRowNameEdit('expenses', rowIdx)">
                                        <span class="font-medium">{{ line.label }}</span>
                                        <span v-if="categoryName(line.categoryId)" class="ml-1 text-xs text-surface-400">· {{ categoryName(line.categoryId) }}</span>
                                        <span
                                            v-if="!line.hasIva"
                                            v-tooltip="'Sin IVA — no entra en Modelo 303'"
                                            class="ml-1.5 inline-flex items-center rounded bg-surface-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-surface-500"
                                        >sin IVA</span>
                                    </button>
                                    <button v-if="!isEditingRow('expenses', rowIdx)" type="button" class="ml-2 rounded p-1 text-surface-400 opacity-0 transition-opacity hover:bg-red-50 hover:text-red-600 group-hover/row:opacity-100" v-tooltip.left="'Eliminar línea'" @click="confirmDeleteRow($event, 'expenses', rowIdx)">
                                        <i class="pi pi-trash text-xs" />
                                    </button>
                                </div>
                            </td>
                            <td
                                v-for="(v, monthIdx) in line.monthly"
                                :key="monthIdx"
                                class="group relative cursor-pointer px-2 py-2 text-right tabular-nums transition-colors hover:bg-red-100/40"
                                :class="[
                                    !v && !line.paid[monthIdx] ? 'text-surface-300' : '',
                                    isCurrentMonth(monthIdx) ? 'bg-sky-50/50' : quarterCols.includes(monthIdx) ? 'bg-red-50/30' : '',
                                    isVencido('expenses', rowIdx, monthIdx) ? 'bg-red-50/60' : '',
                                    isDragHighlighted('expenses', rowIdx, monthIdx) ? 'bg-red-200/50 ring-1 ring-inset ring-red-400' : '',
                                ]"
                                @click="openCellEditor($event, 'expenses', rowIdx, monthIdx)"
                                @mouseenter="onCellEnter('expenses', rowIdx, monthIdx)"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <i v-if="isVencido('expenses', rowIdx, monthIdx)" v-tooltip="'Vencido sin pagar'" class="pi pi-exclamation-circle text-[10px] text-red-600" />
                                    <i v-else-if="line.paid[monthIdx] && v" class="pi pi-check-circle text-[10px] text-red-700" />
                                    <span :class="[isVencido('expenses', rowIdx, monthIdx) ? 'font-medium text-red-700' : '', line.paid[monthIdx] && v ? 'font-semibold text-red-800' : '']">
                                        {{ formatCompact(v) }}
                                    </span>
                                </span>
                                <span v-if="v" class="fill-handle absolute bottom-0.5 right-0.5 h-2 w-2 cursor-crosshair rounded-sm bg-red-600 opacity-0 transition-opacity group-hover:opacity-100" @mousedown="startDrag($event, 'expenses', rowIdx, monthIdx)" @click.stop />
                            </td>
                            <td class="bg-surface-50 px-3 py-2 text-right font-semibold tabular-nums text-red-700">{{ formatCompact(sumRow(line.monthly)) }}</td>
                        </tr>
                        <tr class="border-b border-red-100">
                            <td class="sticky left-0 z-10 bg-red-50 px-4 py-2 text-sm text-red-700">
                                <i class="pi pi-check-circle mr-1 text-xs" />Pagado
                            </td>
                            <td v-for="(v, i) in expensesPaidByMonth" :key="i" class="px-2 py-2 text-right tabular-nums text-red-700" :class="!v ? 'text-red-700/40' : ''">{{ formatCompact(v) }}</td>
                            <td class="bg-red-50 px-3 py-2 text-right font-semibold tabular-nums text-red-700">{{ formatCompact(sumRow(expensesPaidByMonth)) }}</td>
                        </tr>
                        <tr class="border-b-2 border-red-200 bg-red-100/40">
                            <td class="sticky left-0 z-10 bg-red-100 px-4 py-2 font-semibold text-red-800">Total previsto</td>
                            <td v-for="(v, i) in expensesByMonth" :key="i" class="px-2 py-2 text-right font-semibold tabular-nums text-red-800">{{ formatCompact(v) }}</td>
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
                            <td v-for="(v, i) in ivaMonthly" :key="i" class="px-2 py-2 text-right tabular-nums" :class="!v ? 'text-surface-300' : ''">{{ formatCompact(v) }}</td>
                            <td class="bg-surface-50 px-3 py-2 text-right font-semibold tabular-nums text-amber-700">{{ formatCompact(sumRow(ivaMonthly)) }}</td>
                        </tr>
                        <tr v-if="!irpfExempt" class="border-b border-surface-100">
                            <td class="sticky left-0 z-10 bg-white px-4 py-2 text-surface-800">
                                <span class="font-medium">IRPF (pago fraccionado)</span>
                                <Tag value="Modelo 130" severity="secondary" class="ml-2 !text-[10px]" />
                            </td>
                            <td v-for="(v, i) in irpfMonthly" :key="i" class="px-2 py-2 text-right tabular-nums" :class="!v ? 'text-surface-300' : ''">{{ formatCompact(v) }}</td>
                            <td class="bg-surface-50 px-3 py-2 text-right font-semibold tabular-nums text-amber-700">{{ formatCompact(sumRow(irpfMonthly)) }}</td>
                        </tr>
                        <tr class="border-b-2 border-amber-200 bg-amber-100/40">
                            <td class="sticky left-0 z-10 bg-amber-100 px-4 py-2 font-semibold text-amber-800">Total fiscal</td>
                            <td v-for="(v, i) in taxesByMonth" :key="i" class="px-2 py-2 text-right font-semibold tabular-nums text-amber-800">{{ formatCompact(v) }}</td>
                            <td class="bg-amber-200/60 px-3 py-2 text-right font-bold tabular-nums text-amber-900">{{ formatCompact(sumRow(taxesByMonth)) }}</td>
                        </tr>

                        <!-- SALARIO -->
                        <tr class="bg-violet-50/40">
                            <td class="sticky left-0 z-10 bg-violet-50 px-4 py-2 text-xs font-bold uppercase tracking-wider text-violet-700" :colspan="14">
                                Pago a mí mismo
                            </td>
                        </tr>
                        <tr class="border-b-2 border-violet-200 bg-violet-100/30">
                            <td class="sticky left-0 z-10 bg-violet-100 px-4 py-2 font-medium text-violet-900">{{ salaryState.label }}</td>
                            <td
                                v-for="(v, monthIdx) in salaryState.monthly"
                                :key="monthIdx"
                                class="group relative cursor-pointer px-2 py-2 text-right font-semibold tabular-nums text-violet-800 transition-colors hover:bg-violet-100/60"
                                @click="openCellEditor($event, 'salary', 0, monthIdx)"
                            >
                                {{ formatCompact(v) }}
                            </td>
                            <td class="bg-violet-200/60 px-3 py-2 text-right font-bold tabular-nums text-violet-900">{{ formatCompact(sumRow(salaryState.monthly)) }}</td>
                        </tr>

                        <!-- BALANCE -->
                        <tr class="border-b border-surface-200">
                            <td class="sticky left-0 z-10 bg-white px-4 py-3 font-semibold text-surface-700">Saldo del mes</td>
                            <td v-for="(v, i) in monthlyBalance" :key="i" class="px-2 py-3 text-right font-semibold tabular-nums" :class="v >= 0 ? 'text-surface-700' : 'text-red-600'">{{ formatCompact(v) }}</td>
                            <td class="bg-surface-100 px-3 py-3 text-right font-bold tabular-nums text-surface-900">{{ formatCompact(sumRow(monthlyBalance)) }}</td>
                        </tr>
                        <tr class="bg-surface-50">
                            <td class="sticky left-0 z-10 bg-surface-50 px-4 py-3 font-bold text-surface-900">Saldo acumulado</td>
                            <td v-for="(v, i) in cumulativeBalance" :key="i" class="px-2 py-3 text-right font-bold tabular-nums" :class="v >= 0 ? 'text-emerald-700' : 'text-red-700'">{{ formatCompact(v) }}</td>
                            <td class="bg-surface-200/80 px-3 py-3 text-right font-bold tabular-nums text-surface-900">{{ formatCompact(cumulativeBalance[11]) }}</td>
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
                    <InputNumber v-model="cellForm.amount" :minFractionDigits="0" :maxFractionDigits="2" locale="es-ES" suffix=" €" autofocus fluid @keydown.enter="saveCellEdit" />
                </div>

                <div v-if="editingCell && editingCell.section !== 'salary'" class="flex items-center justify-between rounded-md px-3 py-2" :class="editingCell.section === 'incomes' ? 'bg-emerald-50' : 'bg-red-50'">
                    <span class="text-sm font-medium" :class="editingCell.section === 'incomes' ? 'text-emerald-800' : 'text-red-800'">
                        {{ editingCell.section === 'incomes' ? 'Cobrado' : 'Pagado' }}
                    </span>
                    <ToggleSwitch v-model="cellForm.paid" />
                </div>

                <div class="flex gap-2">
                    <Button v-if="editingCell?.rowIdx !== null && cellForm.amount" type="button" icon="pi pi-trash" severity="secondary" text size="small" v-tooltip="'Vaciar celda'" @click="clearCell" />
                    <Button label="Guardar" size="small" fluid @click="saveCellEdit" />
                </div>
            </div>
        </Popover>

        <!-- Modal: nueva fila -->
        <Dialog
            v-model:visible="drawerOpen"
            modal
            :style="{ width: '460px' }"
            :pt="{ root: { class: '!rounded-2xl !overflow-hidden' } }"
            :dismissableMask="true"
        >
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
                    <InputText v-model="newRowForm.label" :placeholder="drawerSection === 'incomes' ? 'Ej: Cliente Acme S.L.' : 'Ej: Coworking'" fluid />
                </div>

                <div v-if="drawerSection === 'expenses'" class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Categoría</label>
                    <CategorySelect v-model="newRowForm.categoryId" :options="expenseCategories" type="expense" @created="addCategoryFromCreate" />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Importe (€)</label>
                    <InputNumber v-model="newRowForm.amount" :minFractionDigits="0" :maxFractionDigits="2" locale="es-ES" suffix=" €" fluid />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Frecuencia</label>
                    <SelectButton v-model="newRowForm.mode" :options="modeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
                </div>

                <div v-if="newRowForm.mode === 'single'" class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Mes</label>
                    <Select v-model="newRowForm.singleMonth" :options="monthOptions" optionLabel="label" optionValue="value" fluid />
                </div>

                <div class="flex items-center justify-between rounded-lg border border-surface-200 p-3">
                    <div>
                        <p class="text-sm font-medium text-surface-700">Con IVA</p>
                        <p class="text-xs text-surface-500">
                            {{ drawerSection === 'incomes'
                                ? 'Desactivar si facturas sin IVA (ej: cliente UE intracomunitario).'
                                : 'Desactivar si el proveedor no aplica IVA.' }}
                        </p>
                    </div>
                    <ToggleSwitch v-model="newRowForm.hasIva" />
                </div>

                <div class="mt-2 flex gap-2">
                    <Button type="button" label="Cancelar" severity="secondary" outlined fluid @click="drawerOpen = false" />
                    <Button type="submit" label="Añadir" fluid />
                </div>
            </form>
        </Dialog>

        <!-- Modal de suppression de ligne -->
        <Dialog v-model:visible="deleteDialog.visible" modal :showHeader="false" :style="{ width: '440px' }" :pt="{ root: { class: '!rounded-2xl !overflow-hidden' } }" :dismissableMask="true">
            <div class="p-2">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <i class="pi pi-trash text-xl text-red-600" />
                    </div>
                    <div class="flex-1 pt-1">
                        <h3 class="text-lg font-semibold text-surface-900">¿Eliminar esta línea?</h3>
                        <p class="mt-1 text-sm text-surface-500">Se quitará del flujo de caja {{ year }}.</p>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-surface-200 bg-surface-50/60 p-4">
                    <p class="text-base font-semibold text-surface-900">{{ deleteDialog.label }}</p>
                    <p v-if="deleteDialog.sublabel" class="mt-0.5 text-xs text-surface-500">{{ deleteDialog.sublabel }}</p>

                    <dl class="mt-3 grid grid-cols-2 gap-3 border-t border-surface-200 pt-3 text-sm">
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-surface-500">Total anual</dt>
                            <dd class="mt-0.5 font-semibold tabular-nums" :class="deleteDialog.section === 'incomes' ? 'text-emerald-700' : 'text-red-700'">{{ formatEuros(deleteDialog.total) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wider text-surface-500">Meses con valor</dt>
                            <dd class="mt-0.5 font-semibold text-surface-900">
                                {{ deleteDialog.filledMonths }}
                                <span v-if="deleteDialog.realizedCount > 0" class="ml-1 text-xs font-normal text-emerald-600">
                                    ({{ deleteDialog.realizedCount }} {{ deleteDialog.section === 'incomes' ? 'cobrado' : 'pagado' }}{{ deleteDialog.realizedCount > 1 ? 's' : '' }})
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <div v-if="deleteDialog.realizedCount > 0" class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <i class="pi pi-exclamation-triangle mt-0.5" />
                    <span>
                        Esta línea contiene movimientos ya {{ deleteDialog.section === 'incomes' ? 'cobrados' : 'pagados' }}.
                        Si los eliminas, perderás ese histórico.
                    </span>
                </div>

                <p v-else class="mt-3 text-xs text-surface-400">Esta acción no se puede deshacer.</p>

                <div class="mt-5 flex justify-end gap-2">
                    <Button label="Cancelar" severity="secondary" outlined @click="deleteDialog.visible = false" />
                    <Button label="Eliminar" icon="pi pi-trash" severity="danger" @click="executeDelete" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.fill-handle {
    box-shadow: 0 0 0 1px white;
}
</style>
