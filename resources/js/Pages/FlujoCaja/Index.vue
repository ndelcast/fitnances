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

const recurrenceOf = (line) => ({
    isRecurring: line.isRecurring ?? false,
    recurrenceInterval: line.recurrenceInterval ?? null,
    recurrenceStartMonth: line.recurrenceStartMonth ?? null,
    recurrenceEndMonth: line.recurrenceEndMonth ?? null,
});

// Ordre au chargement : récurrentes d'abord, puis alphabétique (FT-1042).
const byRecurringThenLabel = (a, b) =>
    Number(b.isRecurring ?? false) - Number(a.isRecurring ?? false) ||
    (a.clientName || a.label || '').localeCompare(b.clientName || b.label || '', 'es', { sensitivity: 'base' });

const incomesState = reactive(
    [...props.incomes].sort(byRecurringThenLabel).map((line) => ({
        id: line.id,
        label: line.clientName || line.label,
        categoryId: line.categoryId,
        hasIva: line.hasIva ?? true,
        hasIrpf: line.hasIrpf ?? true,
        ...recurrenceOf(line),
        monthly: [...line.monthly],
    })),
);

const expensesState = reactive(
    [...props.expenses].sort(byRecurringThenLabel).map((line) => ({
        id: line.id,
        label: line.label,
        categoryId: line.categoryId,
        hasIva: line.hasIva ?? true,
        ...recurrenceOf(line),
        monthly: [...line.monthly],
    })),
);

const salaryState = reactive({
    id: props.salary.id,
    label: props.salary.label || 'Salario',
    monthly: [...props.salary.monthly],
});

const isCurrentMonth = (i) => i === todayMonth.value;

// Distinction obligation / paiement :
//  - L'obligation est calculée par trimestre et affichée le dernier
//    mois du trimestre (Mar / Jun / Sep / Dic) : c'est ce que tu dois.
//  - Le paiement effectif sort le 1ᵉʳ mois du trimestre suivant
//    (Abr / Jul / Oct / Ene N+1) et impacte alors le balance acumulado.
//  - Q4 est payé en enero N+1, donc hors fenêtre annuelle pour le cash,
//    mais l'obligation reste visible sur Dic (à provisionner).
const quarterAccrualMonth = [2, 5, 8, 11]; // dernier mes del trimestre
const quarterPaymentMonth = [3, 6, 9, null]; // 1er mes du trimestre siguiente

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
// Toutes les lignes comptent dans le rendimiento neto. Les retenciones ne
// s'appliquent qu'aux ingresos explicitement marqués `Con IRPF retenido`.
const sumQuarterHtBy = (rows, range, withIva, filterFn = null) =>
    rows.reduce((sum, row) => {
        if (filterFn && !filterFn(row)) return sum;
        const ttcMatchesIvaFilter = withIva === null || row.hasIva === withIva;
        if (!ttcMatchesIvaFilter) return sum;
        const ttc = range.reduce((s, m) => s + (row.monthly[m] ?? 0), 0);
        const ivaRate = (props.taxRates.iva ?? 0) / 100;
        return sum + (row.hasIva ? ttc - extractIva(ttc, ivaRate) : ttc);
    }, 0);

const irpfState = computed(() => {
    const irpfRetentionRate = (props.taxRates.irpf ?? 0) / 100;
    const modelo130Rate = (props.taxRates.modelo130 ?? 20) / 100;

    return [0, 1, 2, 3].map((q) => {
        const range = [q * 3, q * 3 + 1, q * 3 + 2];
        const incomeHt = sumQuarterHtBy(incomesState, range, null);
        const expenseHt = sumQuarterHtBy(expensesState, range, null);
        const incomeHtWithIrpf = sumQuarterHtBy(incomesState, range, null, (r) => r.hasIrpf);

        const netRendimiento = Math.max(0, incomeHt - expenseHt);
        const base = netRendimiento * modelo130Rate;
        const retenido = incomeHtWithIrpf * irpfRetentionRate;
        return Math.max(0, Math.round((base - retenido) * 100) / 100);
    });
});

const mapQuarterly = (quarterly, monthMap) => {
    const arr = new Array(12).fill(0);
    quarterly.forEach((amount, q) => {
        const m = monthMap[q];
        if (m !== null) arr[m] = amount;
    });
    return arr;
};

// Affichage de l'obligation dans la ligne fiscale : end of quarter.
const ivaMonthly = computed(() => mapQuarterly(ivaState.value, quarterAccrualMonth));
const irpfMonthly = computed(() => mapQuarterly(irpfState.value, quarterAccrualMonth));

// Impact sur la trésorerie : 1ᵉʳ mes du trimestre siguiente (Q4 hors année).
const ivaPaymentMonthly = computed(() => mapQuarterly(ivaState.value, quarterPaymentMonth));
const irpfPaymentMonthly = computed(() => mapQuarterly(irpfState.value, quarterPaymentMonth));

// IRPF mensuel estimé : on lisse la valeur trimestrielle Modelo 130 sur
// les 3 mois du trimestre (montant à provisionner chaque mois).
const irpfMonthlyAccrual = computed(() => {
    const arr = new Array(12).fill(0);
    irpfState.value.forEach((quarterAmount, q) => {
        const monthly = quarterAmount / 3;
        for (let i = 0; i < 3; i++) {
            arr[q * 3 + i] = monthly;
        }
    });
    return arr;
});

/* ---------------------------------------------------------------
 * Renta annuelle (IRPF réel selon barème progressif)
 * ---------------------------------------------------------------
 * Le Modelo 130 est une simple avance à 20 % du rendimiento. La
 * Renta calcule l'IRPF réel selon les tranches progressives. À la
 * fin de l'année, on régularise : si Modelo 130 < Renta, on paie
 * la différence ; sinon Hacienda rembourse.
 *
 * Barème 2025 (état + autonomique général, approximation) :
 *   0 – 12 450    19 %
 *   12 450 – 20 200    24 %
 *   20 200 – 35 200    30 %
 *   35 200 – 60 000    37 %
 *   60 000 – 300 000   45 %
 *   > 300 000          47 %
 *
 * Base = rendimiento neto annuel - mínimo personal (5 550 €).
 * (Approximation : on ignore les autres déductions pour le MVP.)
 */
const PERSONAL_MINIMUM = 5550;
const IRPF_BRACKETS = [
    { upTo: 12450, rate: 0.19 },
    { upTo: 20200, rate: 0.24 },
    { upTo: 35200, rate: 0.30 },
    { upTo: 60000, rate: 0.37 },
    { upTo: 300000, rate: 0.45 },
    { upTo: Infinity, rate: 0.47 },
];

const applyIrpfBrackets = (base) => {
    if (base <= 0) return 0;
    let owed = 0;
    let prev = 0;
    for (const bracket of IRPF_BRACKETS) {
        const slice = Math.min(base, bracket.upTo) - prev;
        if (slice <= 0) break;
        owed += slice * bracket.rate;
        prev = bracket.upTo;
        if (base <= bracket.upTo) break;
    }
    return owed;
};

// Totaux annuels HT (toutes les lignes, tous les mois).
const annualIncomeHt = computed(() => sumQuarterHtBy(incomesState, [...Array(12).keys()], null));
const annualExpenseHt = computed(() => sumQuarterHtBy(expensesState, [...Array(12).keys()], null));
const annualIncomeHtWithIrpf = computed(() =>
    sumQuarterHtBy(incomesState, [...Array(12).keys()], null, (r) => r.hasIrpf),
);

const rentaAnnual = computed(() => {
    const irpfRetentionRate = (props.taxRates.irpf ?? 0) / 100;
    const baseImponible = Math.max(0, annualIncomeHt.value - annualExpenseHt.value - PERSONAL_MINIMUM);
    const rentaIrpf = applyIrpfBrackets(baseImponible);
    const retentionsAnnual = annualIncomeHtWithIrpf.value * irpfRetentionRate;
    const modelo130Annual = irpfState.value.reduce((s, v) => s + v, 0);

    // Marginal rate approximé sur la dernière tranche atteinte.
    let marginalRate = 0.19;
    let cumul = 0;
    for (const b of IRPF_BRACKETS) {
        if (baseImponible > cumul) marginalRate = b.rate;
        cumul = b.upTo;
        if (baseImponible <= cumul) break;
    }

    // Restant à provisionner pour la déclaration de renta = Renta réelle
    // - Modelo 130 déjà avancé - retenciones déjà appliquées par les clients.
    const restanteRenta = Math.max(0, rentaIrpf - modelo130Annual - retentionsAnnual);

    return {
        baseImponible,
        rentaIrpf,
        marginalRate,
        modelo130Annual,
        retentionsAnnual,
        restanteRenta,
    };
});

// Provision cumulative à atteindre à la fin de chaque mois pour la renta :
// /12 × (mois écoulés). En décembre, on doit avoir la totalité.
const rentaMonthlyAccrual = computed(() => {
    const monthly = rentaAnnual.value.restanteRenta / 12;
    return new Array(12).fill(0).map((_, i) => monthly * (i + 1));
});

const sumRow = (row) => row.reduce((s, v) => s + (v || 0), 0);

/* ---------------------------------------------------------------
 * Groupe RECURRENTES (FT-1042)
 * --------------------------------------------------------------- */
const recurringCollapsed = reactive({ incomes: false, expenses: false });

const groupEntries = (state) => {
    const entries = state.map((line, idx) => ({ line, idx }));
    return {
        recurring: entries.filter((e) => e.line.isRecurring),
        oneOff: entries.filter((e) => !e.line.isRecurring),
    };
};
const incomeGroups = computed(() => groupEntries(incomesState));
const expenseGroups = computed(() => groupEntries(expensesState));
const groupsFor = (section) => (section === 'incomes' ? incomeGroups.value : expenseGroups.value);

// Lignes rendues d'une section : récurrentes (si dépliées), séparateur,
// puis ponctuelles. `idx` reste l'index d'origine dans le state, utilisé
// par l'édition de cellule, le drag, le rename et la suppression.
const sectionRows = (section) => {
    const groups = groupsFor(section);
    const items = [];
    if (groups.recurring.length && !recurringCollapsed[section]) {
        items.push(...groups.recurring.map((e) => ({ type: 'row', ...e })));
    }
    if (groups.recurring.length && groups.oneOff.length) {
        items.push({ type: 'divider', label: section === 'incomes' ? 'Otras facturas' : 'Gastos casuales' });
    }
    items.push(...groups.oneOff.map((e) => ({ type: 'row', ...e })));
    return items;
};

const recurringTotals = (section) =>
    months.map((_, m) => groupsFor(section).recurring.reduce((s, e) => s + (Number(e.line.monthly[m]) || 0), 0));

const incomesByMonth = computed(() =>
    months.map((_, i) => incomesState.reduce((s, l) => s + (l.monthly[i] ?? 0), 0)),
);
const expensesByMonth = computed(() =>
    months.map((_, i) => expensesState.reduce((s, l) => s + (l.monthly[i] ?? 0), 0)),
);

// Ligne "Total fiscal" : somme des obligations affichées (accrual).
const taxesAccrualByMonth = computed(() => months.map((_, i) => ivaMonthly.value[i] + irpfMonthly.value[i]));
// Pour le balance acumulado : impact cash réel (payment timing), Q4 hors année.
const taxesPaymentByMonth = computed(() => months.map((_, i) => ivaPaymentMonthly.value[i] + irpfPaymentMonthly.value[i]));

// Projection pure : tous les montants prévus comptent dans le saldo.
// Le saldo retire aussi 1/12 du complément Renta annuel chaque mois
// (provision lissée pour la déclaration de mai N+1).
// Pour suivre la réalité (paid_at), aller dans /movements.
const rentaMonthlyDelta = computed(() => rentaAnnual.value.restanteRenta / 12);
const monthlyBalance = computed(() =>
    months.map(
        (_, i) =>
            incomesByMonth.value[i] -
            expensesByMonth.value[i] -
            taxesPaymentByMonth.value[i] -
            (salaryState.monthly[i] ?? 0) -
            rentaMonthlyDelta.value,
    ),
);

const startingBalanceState = ref(props.startingBalance);

const cumulativeBalance = computed(() => {
    let acc = startingBalanceState.value;
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
    startingBalance: startingBalanceState.value,
    irpfExempt: props.irpfExempt,
    incomes: incomesState.map((r) => ({
        id: r.id,
        label: r.label,
        clientName: r.label,
        categoryId: r.categoryId,
        hasIva: r.hasIva,
        hasIrpf: r.hasIrpf,
        ...recurrenceOf(r),
        monthly: r.monthly.map((v) => Number(v) || 0),
    })),
    expenses: expensesState.map((r) => ({
        id: r.id,
        label: r.label,
        categoryId: r.categoryId,
        hasIva: r.hasIva,
        ...recurrenceOf(r),
        monthly: r.monthly.map((v) => Number(v) || 0),
    })),
    salary: {
        id: salaryState.id,
        label: salaryState.label,
        monthly: salaryState.monthly.map((v) => Number(v) || 0),
    },
});

const persistNow = () => {
    saving.value = true;
    router.put(`/cash-flow/${props.year}`, buildPayload(), {
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
watch([incomesState, expensesState, salaryState, startingBalanceState], scheduleSave, { deep: true });

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
    router.get('/cash-flow', { year }, { preserveScroll: true });
};

/* ---------------------------------------------------------------
 * Cell editor (Popover)
 * --------------------------------------------------------------- */
const popoverRef = ref();
const editingCell = ref(null);
const cellForm = ref({ amount: null });

const openCellEditor = (event, section, rowIdx, monthIdx) => {
    if (dragState.value) return;
    const row = rowFor(section, rowIdx);
    cellForm.value = { amount: row.monthly[monthIdx] || null };
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
    popoverRef.value.hide();
    editingCell.value = null;
};

const clearCell = () => {
    if (!editingCell.value) return;
    const { section, rowIdx, monthIdx } = editingCell.value;
    const row = rowFor(section, rowIdx);
    row.monthly[monthIdx] = 0;
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
        hasIrpf: true,
        amount: null,
        mode: 'monthly',
        singleMonth: 0,
        interval: 12,
        startMonth: 0,
        endMonth: 11,
    };
}

const openNewRow = (section) => {
    drawerSection.value = section;
    newRowForm.value = emptyNewRow();
    drawerOpen.value = true;
};

const modeOptions = [
    { label: 'Todos los meses', value: 'monthly' },
    { label: 'Cada X meses', value: 'every' },
    { label: 'Un mes concreto', value: 'single' },
];

const monthOptions = months.map((m, i) => ({ label: m, value: i }));

const intervalOptions = [2, 3, 4, 6, 12].map((n) => ({
    label: n === 12 ? 'Cada año' : `Cada ${n} meses`,
    value: n,
}));

const editIntervalOptions = [{ label: 'Cada mes', value: 1 }, ...intervalOptions];

const saveNewRow = () => {
    const f = newRowForm.value;
    if (!f.label || !f.amount) return;
    if (f.mode === 'every' && f.endMonth < f.startMonth) return;

    const monthly = new Array(12).fill(0);
    if (f.mode === 'monthly') {
        for (let i = 0; i < 12; i++) monthly[i] = f.amount;
    } else if (f.mode === 'every') {
        for (let i = f.startMonth; i <= f.endMonth; i += f.interval) monthly[i] = f.amount;
    } else {
        monthly[f.singleMonth] = f.amount;
    }

    // 'monthly' est une récurrence mensuelle sur l'année entière ;
    // 'single' est ponctuel. Mois persistés en 1-12 côté backend.
    const recurrence =
        f.mode === 'single'
            ? { isRecurring: false, recurrenceInterval: null, recurrenceStartMonth: null, recurrenceEndMonth: null }
            : {
                  isRecurring: true,
                  recurrenceInterval: f.mode === 'monthly' ? 1 : f.interval,
                  recurrenceStartMonth: (f.mode === 'monthly' ? 0 : f.startMonth) + 1,
                  recurrenceEndMonth: (f.mode === 'monthly' ? 11 : f.endMonth) + 1,
              };

    if (drawerSection.value === 'incomes') {
        incomesState.push({
            id: null,
            label: f.label,
            categoryId: f.categoryId,
            hasIva: f.hasIva,
            hasIrpf: f.hasIrpf,
            ...recurrence,
            monthly,
        });
    } else {
        expensesState.push({
            id: null,
            label: f.label,
            categoryId: f.categoryId,
            hasIva: f.hasIva,
            ...recurrence,
            monthly,
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
});

const confirmDeleteRow = (event, section, rowIdx) => {
    event.stopPropagation();
    const row = rowFor(section, rowIdx);
    const label = row.label;
    const sublabel = section === 'expenses' ? categoryName(row.categoryId) : '';
    const total = row.monthly.reduce((s, v) => s + (v || 0), 0);
    const filledMonths = row.monthly.filter((v) => v > 0).length;
    deleteDialog.value = {
        visible: true,
        section,
        rowIdx,
        label,
        sublabel,
        total,
        filledMonths,
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

/* ---------------------------------------------------------------
 * Edición de ficha (FT-1041)
 * ---------------------------------------------------------------
 * Édite la config d'une row : nombre, IVA/IRPF, recurrencia, importe.
 * Convention validée : si le patrón de récurrence ou l'importe change,
 * seules les cellules des MOIS FUTURS sont réécrites — les mois passés
 * (et leurs movements, dont paid_at) restent intacts.
 */
const editRowDialog = ref({ visible: false, section: null, rowIdx: null });
const editRowForm = ref(null);
let editRowInitialPattern = null;

const monthIsFuture = (m) =>
    props.year > today.getFullYear() || (props.year === today.getFullYear() && m > today.getMonth());

const recurrencePatternOf = (f) => ({
    isRecurring: f.isRecurring,
    interval: f.interval,
    startMonth: f.startMonth,
    endMonth: f.endMonth,
    amount: f.amount,
});

const openRowEditor = (section, rowIdx) => {
    const row = rowFor(section, rowIdx);
    const amount =
        row.monthly.find((v, m) => monthIsFuture(m) && v > 0) ?? row.monthly.find((v) => v > 0) ?? null;
    editRowForm.value = {
        label: row.label,
        categoryId: row.categoryId ?? null,
        hasIva: row.hasIva,
        hasIrpf: row.hasIrpf ?? true,
        isRecurring: row.isRecurring,
        interval: row.recurrenceInterval ?? 1,
        startMonth: (row.recurrenceStartMonth ?? 1) - 1,
        endMonth: (row.recurrenceEndMonth ?? 12) - 1,
        amount,
    };
    editRowInitialPattern = JSON.stringify(recurrencePatternOf(editRowForm.value));
    editRowDialog.value = { visible: true, section, rowIdx };
};

const editRowFromCell = () => {
    if (!editingCell.value) return;
    const { section, rowIdx } = editingCell.value;
    popoverRef.value.hide();
    editingCell.value = null;
    openRowEditor(section, rowIdx);
};

const saveRowEdit = () => {
    const f = editRowForm.value;
    const { section, rowIdx } = editRowDialog.value;
    if (!f.label?.trim()) return;
    if (f.isRecurring && f.endMonth < f.startMonth) return;

    const row = rowFor(section, rowIdx);
    row.label = f.label.trim();
    if (section === 'expenses') row.categoryId = f.categoryId;
    row.hasIva = f.hasIva;
    if (section === 'incomes') row.hasIrpf = f.hasIrpf;
    row.isRecurring = f.isRecurring;
    row.recurrenceInterval = f.isRecurring ? f.interval : null;
    row.recurrenceStartMonth = f.isRecurring ? f.startMonth + 1 : null;
    row.recurrenceEndMonth = f.isRecurring ? f.endMonth + 1 : null;

    const patternChanged = JSON.stringify(recurrencePatternOf(f)) !== editRowInitialPattern;
    if (f.isRecurring && f.amount && patternChanged) {
        for (let m = 0; m < 12; m++) {
            if (!monthIsFuture(m)) continue;
            const inPattern = m >= f.startMonth && m <= f.endMonth && (m - f.startMonth) % f.interval === 0;
            row.monthly[m] = inPattern ? f.amount : 0;
        }
    }
    editRowDialog.value.visible = false;
};

const flash = computed(() => page.props.flash);

/* ---------------------------------------------------------------
 * Capital inicial editor (dialog)
 * --------------------------------------------------------------- */
const capitalDialog = ref({ visible: false, value: 0 });

const openCapitalEditor = () => {
    capitalDialog.value = { visible: true, value: startingBalanceState.value };
};

const saveCapital = () => {
    startingBalanceState.value = Number(capitalDialog.value.value) || 0;
    capitalDialog.value.visible = false;
};
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

                        <button
                            type="button"
                            class="ml-2 inline-flex items-center gap-2 rounded-full border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 transition-all hover:border-violet-400 hover:bg-violet-100"
                            v-tooltip.bottom="'Dinero disponible al iniciar el año (colchón inicial). Click para editar.'"
                            @click="openCapitalEditor"
                        >
                            <i class="pi pi-wallet text-[10px]" />
                            Capital inicial · <span class="tabular-nums">{{ formatEuros(startingBalanceState) }}</span>
                            <i class="pi pi-pencil text-[10px]" />
                        </button>

                        <span v-if="saveStateLabel" class="ml-3 text-xs" :class="saving ? 'text-amber-600' : 'text-emerald-600'">
                            <i class="pi" :class="saving ? 'pi-spin pi-spinner' : 'pi-check'" />
                            {{ saveStateLabel }}
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <a :href="`/cash-flow/${year}/export`">
                            <Button label="Exportar Excel" icon="pi pi-file-excel" severity="secondary" outlined size="small" />
                        </a>
                        <Link href="/onboarding">
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
                            <th class="sticky left-0 top-0 z-30 min-w-[240px] bg-surface-50 px-4 py-3 text-left font-semibold text-surface-700 shadow-[inset_0_-1px_0_#e2e8f0]">Concepto</th>
                            <th
                                v-for="(m, i) in months"
                                :key="m"
                                class="sticky top-0 z-20 min-w-[110px] px-2 py-3 text-right font-semibold uppercase tracking-wider shadow-[inset_0_-1px_0_#e2e8f0]"
                                :class="[
                                    isCurrentMonth(i) ? 'bg-sky-100 text-sky-800' : quarterCols.includes(i) ? 'bg-emerald-50 text-surface-500' : 'bg-surface-50 text-surface-500',
                                ]"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    <span v-if="isCurrentMonth(i)" class="rounded-full bg-sky-600 px-1.5 py-px text-[9px] font-bold uppercase text-white">Hoy</span>
                                    {{ m }}
                                </span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <!-- INGRESOS -->
                        <tr class="bg-emerald-50/30">
                            <td class="sticky left-0 z-10 bg-emerald-50 px-4 py-2" :colspan="13">
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
                        <tr
                            v-if="incomeGroups.recurring.length"
                            class="cursor-pointer select-none border-b border-emerald-100 bg-emerald-50/50"
                            @click="recurringCollapsed.incomes = !recurringCollapsed.incomes"
                        >
                            <td class="sticky left-0 z-10 bg-emerald-50 px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <i class="pi text-[10px] text-emerald-700" :class="recurringCollapsed.incomes ? 'pi-plus' : 'pi-minus'" />
                                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Recurrentes</span>
                                    <span class="rounded-full bg-emerald-100 px-1.5 py-px text-[10px] font-semibold tabular-nums text-emerald-700">{{ incomeGroups.recurring.length }}</span>
                                </div>
                            </td>
                            <td v-for="(t, i) in recurringTotals('incomes')" :key="i" class="px-2 py-2 text-right font-semibold tabular-nums text-emerald-700">
                                {{ recurringCollapsed.incomes ? formatCompact(t) : '' }}
                            </td>
                        </tr>
                        <template v-for="item in sectionRows('incomes')" :key="item.type === 'row' ? 'inc-' + item.idx : 'inc-divider'">
                            <tr v-if="item.type === 'divider'" class="border-b border-surface-100 bg-surface-50/60">
                                <td class="sticky left-0 z-10 bg-surface-50 px-4 py-1.5" :colspan="13">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-surface-400">{{ item.label }}</span>
                                </td>
                            </tr>
                            <tr v-else class="border-b border-surface-100 hover:bg-emerald-50/20">
                                <td class="group/row sticky left-0 z-10 bg-white px-4 py-2 font-medium text-surface-800">
                                    <div class="flex items-center justify-between">
                                        <InputText v-if="isEditingRow('incomes', item.idx)" ref="rowNameInput" v-model="tempRowName" size="small" @blur="saveRowName" @keydown.enter="saveRowName" @keydown.esc="editingRowName = null" />
                                        <button v-else type="button" class="flex-1 text-left hover:text-emerald-700" @click="startRowNameEdit('incomes', item.idx)">
                                            {{ item.line.label }}
                                            <span
                                                v-if="!item.line.hasIva"
                                                v-tooltip="'Sin IVA — no entra en Modelo 303'"
                                                class="ml-1.5 inline-flex items-center rounded bg-surface-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-surface-500"
                                            >sin IVA</span>
                                            <span
                                                v-if="!item.line.hasIrpf"
                                                v-tooltip="'Sin retención IRPF — no descuenta de Modelo 130'"
                                                class="ml-1.5 inline-flex items-center rounded bg-surface-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-surface-500"
                                            >sin IRPF</span>
                                        </button>
                                        <button v-if="!isEditingRow('incomes', item.idx)" type="button" class="ml-2 rounded p-1 text-surface-400 opacity-0 transition-opacity hover:bg-emerald-50 hover:text-emerald-700 group-hover/row:opacity-100" v-tooltip.left="'Editar cliente'" @click="openRowEditor('incomes', item.idx)">
                                            <i class="pi pi-pencil text-xs" />
                                        </button>
                                        <button v-if="!isEditingRow('incomes', item.idx)" type="button" class="ml-1 rounded p-1 text-surface-400 opacity-0 transition-opacity hover:bg-red-50 hover:text-red-600 group-hover/row:opacity-100" v-tooltip.left="'Eliminar línea'" @click="confirmDeleteRow($event, 'incomes', item.idx)">
                                            <i class="pi pi-trash text-xs" />
                                        </button>
                                    </div>
                                </td>
                                <td
                                    v-for="(v, monthIdx) in item.line.monthly"
                                    :key="monthIdx"
                                    class="group relative cursor-pointer px-2 py-2 text-right tabular-nums transition-colors hover:bg-emerald-100/40"
                                    :class="[
                                        !v ? 'text-surface-300' : '',
                                        isCurrentMonth(monthIdx) ? 'bg-sky-50/50' : quarterCols.includes(monthIdx) ? 'bg-emerald-50/30' : '',
                                        isDragHighlighted('incomes', item.idx, monthIdx) ? 'bg-emerald-200/50 ring-1 ring-inset ring-emerald-400' : '',
                                    ]"
                                    @click="openCellEditor($event, 'incomes', item.idx, monthIdx)"
                                    @mouseenter="onCellEnter('incomes', item.idx, monthIdx)"
                                >
                                    <span class="inline-flex items-center gap-1">{{ formatCompact(v) }}</span>
                                    <span v-if="v" class="fill-handle absolute bottom-0.5 right-0.5 h-2 w-2 cursor-crosshair rounded-sm bg-emerald-600 opacity-0 transition-opacity group-hover:opacity-100" @mousedown="startDrag($event, 'incomes', item.idx, monthIdx)" @click.stop />
                                </td>
                            </tr>
                        </template>
                        <tr class="border-b-2 border-emerald-200 bg-emerald-100/40">
                            <td class="sticky left-0 z-10 bg-emerald-100 px-4 py-2 font-semibold text-emerald-800">Total previsto</td>
                            <td v-for="(v, i) in incomesByMonth" :key="i" class="px-2 py-2 text-right font-semibold tabular-nums text-emerald-800">{{ formatCompact(v) }}</td>
                        </tr>

                        <!-- GASTOS -->
                        <tr class="bg-red-50/30">
                            <td class="sticky left-0 z-10 bg-red-50 px-4 py-2" :colspan="13">
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
                        <tr
                            v-if="expenseGroups.recurring.length"
                            class="cursor-pointer select-none border-b border-red-100 bg-red-50/50"
                            @click="recurringCollapsed.expenses = !recurringCollapsed.expenses"
                        >
                            <td class="sticky left-0 z-10 bg-red-50 px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <i class="pi text-[10px] text-red-700" :class="recurringCollapsed.expenses ? 'pi-plus' : 'pi-minus'" />
                                    <span class="text-xs font-bold uppercase tracking-wider text-red-700">Recurrentes</span>
                                    <span class="rounded-full bg-red-100 px-1.5 py-px text-[10px] font-semibold tabular-nums text-red-700">{{ expenseGroups.recurring.length }}</span>
                                </div>
                            </td>
                            <td v-for="(t, i) in recurringTotals('expenses')" :key="i" class="px-2 py-2 text-right font-semibold tabular-nums text-red-700">
                                {{ recurringCollapsed.expenses ? formatCompact(t) : '' }}
                            </td>
                        </tr>
                        <template v-for="item in sectionRows('expenses')" :key="item.type === 'row' ? 'exp-' + item.idx : 'exp-divider'">
                            <tr v-if="item.type === 'divider'" class="border-b border-surface-100 bg-surface-50/60">
                                <td class="sticky left-0 z-10 bg-surface-50 px-4 py-1.5" :colspan="13">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-surface-400">{{ item.label }}</span>
                                </td>
                            </tr>
                            <tr v-else class="border-b border-surface-100 hover:bg-red-50/20">
                                <td class="group/row sticky left-0 z-10 bg-white px-4 py-2 text-surface-800">
                                    <div class="flex items-center justify-between">
                                        <InputText v-if="isEditingRow('expenses', item.idx)" ref="rowNameInput" v-model="tempRowName" size="small" @blur="saveRowName" @keydown.enter="saveRowName" @keydown.esc="editingRowName = null" />
                                        <button v-else type="button" class="flex-1 text-left hover:text-red-700" @click="startRowNameEdit('expenses', item.idx)">
                                            <span class="font-medium">{{ item.line.label }}</span>
                                            <span v-if="categoryName(item.line.categoryId)" class="ml-1 text-xs text-surface-400">· {{ categoryName(item.line.categoryId) }}</span>
                                            <span
                                                v-if="!item.line.hasIva"
                                                v-tooltip="'Sin IVA — no entra en Modelo 303'"
                                                class="ml-1.5 inline-flex items-center rounded bg-surface-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-surface-500"
                                            >sin IVA</span>
                                        </button>
                                        <button v-if="!isEditingRow('expenses', item.idx)" type="button" class="ml-2 rounded p-1 text-surface-400 opacity-0 transition-opacity hover:bg-red-50 hover:text-red-700 group-hover/row:opacity-100" v-tooltip.left="'Editar gasto'" @click="openRowEditor('expenses', item.idx)">
                                            <i class="pi pi-pencil text-xs" />
                                        </button>
                                        <button v-if="!isEditingRow('expenses', item.idx)" type="button" class="ml-1 rounded p-1 text-surface-400 opacity-0 transition-opacity hover:bg-red-50 hover:text-red-600 group-hover/row:opacity-100" v-tooltip.left="'Eliminar línea'" @click="confirmDeleteRow($event, 'expenses', item.idx)">
                                            <i class="pi pi-trash text-xs" />
                                        </button>
                                    </div>
                                </td>
                                <td
                                    v-for="(v, monthIdx) in item.line.monthly"
                                    :key="monthIdx"
                                    class="group relative cursor-pointer px-2 py-2 text-right tabular-nums transition-colors hover:bg-red-100/40"
                                    :class="[
                                        !v ? 'text-surface-300' : '',
                                        isCurrentMonth(monthIdx) ? 'bg-sky-50/50' : quarterCols.includes(monthIdx) ? 'bg-red-50/30' : '',
                                        isDragHighlighted('expenses', item.idx, monthIdx) ? 'bg-red-200/50 ring-1 ring-inset ring-red-400' : '',
                                    ]"
                                    @click="openCellEditor($event, 'expenses', item.idx, monthIdx)"
                                    @mouseenter="onCellEnter('expenses', item.idx, monthIdx)"
                                >
                                    <span class="inline-flex items-center gap-1">{{ formatCompact(v) }}</span>
                                    <span v-if="v" class="fill-handle absolute bottom-0.5 right-0.5 h-2 w-2 cursor-crosshair rounded-sm bg-red-600 opacity-0 transition-opacity group-hover:opacity-100" @mousedown="startDrag($event, 'expenses', item.idx, monthIdx)" @click.stop />
                                </td>
                            </tr>
                        </template>
                        <tr class="border-b-2 border-red-200 bg-red-100/40">
                            <td class="sticky left-0 z-10 bg-red-100 px-4 py-2 font-semibold text-red-800">Total previsto</td>
                            <td v-for="(v, i) in expensesByMonth" :key="i" class="px-2 py-2 text-right font-semibold tabular-nums text-red-800">{{ formatCompact(v) }}</td>
                        </tr>

                        <!-- OBLIGACIONES FISCALES -->
                        <tr class="bg-amber-50/40">
                            <td class="sticky left-0 z-10 bg-amber-50 px-4 py-2 text-xs font-bold uppercase tracking-wider text-amber-700" :colspan="13">
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
                        </tr>
                        <tr v-if="!irpfExempt" class="border-b border-surface-100">
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
                        </tr>
                        <tr v-if="!irpfExempt" class="border-b border-surface-100 bg-amber-50/20">
                            <td class="sticky left-0 z-10 bg-amber-50/40 px-4 py-1.5 text-xs italic text-amber-700">
                                <i class="pi pi-info-circle mr-1 text-[10px]" />
                                IRPF mensual a provisionar
                                <span class="ml-1 text-[10px] font-normal text-surface-500">(Modelo 130, 1/3 del trimestre)</span>
                            </td>
                            <td
                                v-for="(v, i) in irpfMonthlyAccrual"
                                :key="i"
                                class="px-2 py-1.5 text-right text-xs italic tabular-nums text-amber-700"
                                :class="!v ? 'text-amber-700/40' : ''"
                            >
                                {{ formatCompact(v) }}
                            </td>
                        </tr>
                        <tr class="border-b border-surface-100 bg-violet-50/20">
                            <td
                                class="sticky left-0 z-10 bg-violet-50/40 px-4 py-1.5 text-xs italic text-violet-700"
                                v-tooltip.top="`Base imponible ${formatEuros(rentaAnnual.baseImponible)} → IRPF Renta ${formatEuros(rentaAnnual.rentaIrpf)} (tramo marginal ${Math.round(rentaAnnual.marginalRate * 100)} %). Modelo 130 anual cubre ${formatEuros(rentaAnnual.modelo130Annual)} + retenciones ${formatEuros(rentaAnnual.retentionsAnnual)}.`"
                            >
                                <i class="pi pi-info-circle mr-1 text-[10px]" />
                                Renta anual a provisionar
                                <span class="ml-1 text-[10px] font-normal text-surface-500">
                                    (IRPF tramo {{ Math.round(rentaAnnual.marginalRate * 100) }} %, acumulado)
                                </span>
                            </td>
                            <td
                                v-for="(v, i) in rentaMonthlyAccrual"
                                :key="i"
                                class="px-2 py-1.5 text-right text-xs italic tabular-nums text-violet-700"
                                :class="!v ? 'text-violet-700/40' : ''"
                            >
                                {{ formatCompact(v) }}
                            </td>
                        </tr>
                        <tr class="border-b-2 border-amber-200 bg-amber-100/40">
                            <td
                                class="sticky left-0 z-10 bg-amber-100 px-4 py-2 font-semibold text-amber-800"
                                v-tooltip.right="'Obligación fiscal del trimestre (IVA + Modelo 130) que se devenga al cierre del trimestre. El pago efectivo cae el primer mes del trimestre siguiente (Q4 → enero N+1).'"
                            >
                                <i class="pi pi-info-circle mr-1 text-xs text-amber-700/60" />
                                Total fiscal del trimestre
                            </td>
                            <td v-for="(v, i) in taxesAccrualByMonth" :key="i" class="px-2 py-2 text-right font-semibold tabular-nums text-amber-800">{{ formatCompact(v) }}</td>
                        </tr>

                        <!-- SALARIO -->
                        <tr class="bg-violet-50/40">
                            <td class="sticky left-0 z-10 bg-violet-50 px-4 py-2 text-xs font-bold uppercase tracking-wider text-violet-700" :colspan="13">
                                Pago a mí mismo
                            </td>
                        </tr>
                        <tr class="border-b-2 border-violet-200 bg-violet-100/30">
                            <td class="sticky left-0 z-10 bg-violet-100 px-4 py-2 font-medium text-violet-900">{{ salaryState.label }}</td>
                            <td
                                v-for="(v, monthIdx) in salaryState.monthly"
                                :key="monthIdx"
                                class="group relative cursor-pointer px-2 py-2 text-right font-semibold tabular-nums text-violet-800 transition-colors hover:bg-violet-100/60"
                                :class="[
                                    isDragHighlighted('salary', 0, monthIdx) ? 'bg-violet-200/60 ring-1 ring-inset ring-violet-400' : '',
                                ]"
                                @click="openCellEditor($event, 'salary', 0, monthIdx)"
                                @mouseenter="onCellEnter('salary', 0, monthIdx)"
                            >
                                {{ formatCompact(v) }}
                                <span
                                    v-if="v"
                                    class="fill-handle absolute bottom-0.5 right-0.5 h-2 w-2 cursor-crosshair rounded-sm bg-violet-600 opacity-0 transition-opacity group-hover:opacity-100"
                                    @mousedown="startDrag($event, 'salary', 0, monthIdx)"
                                    @click.stop
                                />
                            </td>
                        </tr>

                        <!-- BALANCE -->
                        <tr class="border-b border-surface-200">
                            <td
                                class="sticky left-0 z-10 bg-white px-4 py-3 font-semibold text-surface-700"
                                v-tooltip.right="'Ingresos − gastos − salario − pago IVA/IRPF del mes (cae el primer mes del trimestre siguiente) − provisión Renta mensualizada. La obligación Q4 se paga en enero N+1: aparece arriba en diciembre pero no descuenta del balance del año.'"
                            >
                                <i class="pi pi-info-circle mr-1 text-xs text-surface-400" />
                                Balance del mes
                            </td>
                            <td v-for="(v, i) in monthlyBalance" :key="i" class="px-2 py-3 text-right font-semibold tabular-nums" :class="v >= 0 ? 'text-surface-700' : 'text-red-600'">{{ formatCompact(v) }}</td>
                        </tr>
                        <tr class="bg-surface-50">
                            <td
                                class="sticky left-0 z-10 bg-surface-50 px-4 py-3 font-bold text-surface-900"
                                v-tooltip.right="'Saldo inicial + balances mensuales. Refleja cash real proyectado. El saldo de diciembre todavía incluye la obligación Q4 que se pagará en enero N+1: para conocer el «cash realmente libre» a fin de año, resta el Q4 que ves arriba en diciembre.'"
                            >
                                <i class="pi pi-info-circle mr-1 text-xs text-surface-400" />
                                Balance acumulado
                            </td>
                            <td v-for="(v, i) in cumulativeBalance" :key="i" class="px-2 py-3 text-right font-bold tabular-nums" :class="v >= 0 ? 'text-emerald-700' : 'text-red-700'">{{ formatCompact(v) }}</td>
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

                <div class="flex gap-2">
                    <Button v-if="editingCell?.rowIdx !== null && cellForm.amount" type="button" icon="pi pi-trash" severity="secondary" text size="small" v-tooltip="'Vaciar celda'" @click="clearCell" />
                    <Button label="Guardar" size="small" fluid @click="saveCellEdit" />
                </div>

                <button
                    v-if="editingCell && editingCell.section !== 'salary'"
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-lg border border-surface-200 px-2 py-1.5 text-xs font-medium text-surface-600 transition-colors hover:border-violet-400 hover:bg-violet-50 hover:text-violet-700"
                    @click="editRowFromCell"
                >
                    <i class="pi pi-pencil text-[10px]" />
                    {{ editingCell.section === 'incomes' ? 'Editar ficha del cliente' : 'Editar ficha del gasto' }}
                </button>
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

                <template v-if="newRowForm.mode === 'every'">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Intervalo</label>
                        <Select v-model="newRowForm.interval" :options="intervalOptions" optionLabel="label" optionValue="value" fluid />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">Mes de inicio</label>
                            <Select v-model="newRowForm.startMonth" :options="monthOptions" optionLabel="label" optionValue="value" fluid />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">Mes de fin</label>
                            <Select v-model="newRowForm.endMonth" :options="monthOptions" optionLabel="label" optionValue="value" fluid />
                        </div>
                    </div>
                    <p v-if="newRowForm.endMonth < newRowForm.startMonth" class="text-xs text-red-600">
                        El mes de fin debe ser posterior al mes de inicio.
                    </p>
                </template>

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

                <div v-if="drawerSection === 'incomes'" class="flex items-center justify-between rounded-lg border border-surface-200 p-3">
                    <div>
                        <p class="text-sm font-medium text-surface-700">Con IRPF retenido</p>
                        <p class="text-xs text-surface-500">
                            Desactivar para facturas B2C, UE o exentas (sin retención del 15 %).
                        </p>
                    </div>
                    <ToggleSwitch v-model="newRowForm.hasIrpf" />
                </div>

                <div class="mt-2 flex gap-2">
                    <Button type="button" label="Cancelar" severity="secondary" outlined fluid @click="drawerOpen = false" />
                    <Button type="submit" label="Añadir" fluid />
                </div>
            </form>
        </Dialog>

        <!-- Modal: editar ficha (FT-1041) -->
        <Dialog
            v-model:visible="editRowDialog.visible"
            modal
            :style="{ width: '460px' }"
            :pt="{ root: { class: '!rounded-2xl !overflow-hidden' } }"
            :dismissableMask="true"
        >
            <template #header>
                <span class="text-lg font-semibold">
                    {{ editRowDialog.section === 'incomes' ? 'Editar cliente' : 'Editar gasto' }}
                </span>
            </template>

            <form v-if="editRowForm" class="flex flex-col gap-5" @submit.prevent="saveRowEdit">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">
                        {{ editRowDialog.section === 'incomes' ? 'Cliente' : 'Nombre del gasto' }}
                    </label>
                    <InputText v-model="editRowForm.label" fluid />
                </div>

                <div v-if="editRowDialog.section === 'expenses'" class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Categoría</label>
                    <CategorySelect v-model="editRowForm.categoryId" :options="expenseCategories" type="expense" @created="addCategoryFromCreate" />
                </div>

                <div class="flex items-center justify-between rounded-lg border border-surface-200 p-3">
                    <div>
                        <p class="text-sm font-medium text-surface-700">Recurrente</p>
                        <p class="text-xs text-surface-500">Se repite automáticamente según el intervalo elegido.</p>
                    </div>
                    <ToggleSwitch v-model="editRowForm.isRecurring" />
                </div>

                <template v-if="editRowForm.isRecurring">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Intervalo</label>
                        <Select v-model="editRowForm.interval" :options="editIntervalOptions" optionLabel="label" optionValue="value" fluid />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">Mes de inicio</label>
                            <Select v-model="editRowForm.startMonth" :options="monthOptions" optionLabel="label" optionValue="value" fluid />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-medium text-surface-700">Mes de fin</label>
                            <Select v-model="editRowForm.endMonth" :options="monthOptions" optionLabel="label" optionValue="value" fluid />
                        </div>
                    </div>
                    <p v-if="editRowForm.endMonth < editRowForm.startMonth" class="text-xs text-red-600">
                        El mes de fin debe ser posterior al mes de inicio.
                    </p>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-surface-700">Importe (€)</label>
                        <InputNumber v-model="editRowForm.amount" :minFractionDigits="0" :maxFractionDigits="2" locale="es-ES" suffix=" €" fluid />
                    </div>
                </template>

                <div class="flex items-center justify-between rounded-lg border border-surface-200 p-3">
                    <div>
                        <p class="text-sm font-medium text-surface-700">Con IVA</p>
                        <p class="text-xs text-surface-500">
                            {{ editRowDialog.section === 'incomes'
                                ? 'Desactivar si facturas sin IVA (ej: cliente UE intracomunitario).'
                                : 'Desactivar si el proveedor no aplica IVA.' }}
                        </p>
                    </div>
                    <ToggleSwitch v-model="editRowForm.hasIva" />
                </div>

                <div v-if="editRowDialog.section === 'incomes'" class="flex items-center justify-between rounded-lg border border-surface-200 p-3">
                    <div>
                        <p class="text-sm font-medium text-surface-700">Con IRPF retenido</p>
                        <p class="text-xs text-surface-500">
                            Desactivar para facturas B2C, UE o exentas (sin retención del 15 %).
                        </p>
                    </div>
                    <ToggleSwitch v-model="editRowForm.hasIrpf" />
                </div>

                <p class="text-xs text-surface-400">
                    Los cambios de recurrencia e importe solo se aplican a los meses futuros; los meses pasados no se modifican.
                </p>

                <div class="mt-2 flex gap-2">
                    <Button type="button" label="Cancelar" severity="secondary" outlined fluid @click="editRowDialog.visible = false" />
                    <Button type="submit" label="Guardar" fluid />
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
                            <dd class="mt-0.5 font-semibold text-surface-900">{{ deleteDialog.filledMonths }}</dd>
                        </div>
                    </dl>
                </div>

                <p class="mt-3 text-xs text-surface-400">Esta acción no se puede deshacer.</p>

                <div class="mt-5 flex justify-end gap-2">
                    <Button label="Cancelar" severity="secondary" outlined @click="deleteDialog.visible = false" />
                    <Button label="Eliminar" icon="pi pi-trash" severity="danger" @click="executeDelete" />
                </div>
            </div>
        </Dialog>

        <!-- Modal Capital inicial -->
        <Dialog v-model:visible="capitalDialog.visible" modal :style="{ width: '420px' }" :pt="{ root: { class: '!rounded-2xl !overflow-hidden' } }" :dismissableMask="true">
            <template #header>
                <span class="text-lg font-semibold">Capital inicial · {{ year }}</span>
            </template>

            <form class="flex flex-col gap-4" @submit.prevent="saveCapital">
                <p class="text-sm text-surface-500">
                    Dinero del que ya dispones al iniciar el año. Sirve de <strong>colchón inicial</strong> y se suma al saldo de cada mes.
                </p>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Importe (€)</label>
                    <InputNumber
                        v-model="capitalDialog.value"
                        :minFractionDigits="0"
                        :maxFractionDigits="2"
                        locale="es-ES"
                        suffix=" €"
                        :min="0"
                        autofocus
                        fluid
                    />
                </div>

                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="value in [0, 1500, 3000, 6000]"
                        :key="value"
                        type="button"
                        class="rounded-lg border border-surface-200 px-2 py-1.5 text-xs font-medium text-surface-700 transition-colors hover:border-violet-400 hover:bg-violet-50"
                        @click="capitalDialog.value = value"
                    >
                        {{ formatEuros(value) }}
                    </button>
                </div>

                <div class="mt-1 flex justify-end gap-2">
                    <Button type="button" label="Cancelar" severity="secondary" outlined @click="capitalDialog.visible = false" />
                    <Button type="submit" label="Guardar" />
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.fill-handle {
    box-shadow: 0 0 0 1px white;
}
</style>
