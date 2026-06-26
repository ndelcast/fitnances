<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import { formatEuros } from '@/lib/format';

const props = defineProps({
    initial: {
        type: Object,
        default: () => ({ annualRevenue: null, cuotaMonthly: null, monthlySalary: null }),
    },
});

const steps = [
    { id: 1, label: 'Facturación' },
    { id: 2, label: 'Cuota' },
    { id: 3, label: 'Salario' },
    { id: 4, label: 'Resumen' },
];

const currentStep = ref(1);
const finishing = ref(false);

const data = ref({
    annualRevenue: props.initial.annualRevenue,
    cuotaMonthly: props.initial.cuotaMonthly,
    monthlySalary: props.initial.monthlySalary,
});

const cuotaSuggestion = computed(() => {
    const r = data.value.annualRevenue ?? 0;
    if (r < 12000) return 230;
    if (r < 18000) return 270;
    if (r < 25000) return 330;
    if (r < 35000) return 400;
    if (r < 50000) return 530;
    return 590;
});

const cuotaTramo = computed(() => {
    const r = data.value.annualRevenue ?? 0;
    if (r < 12000) return 'Menos de 12.000 €';
    if (r < 18000) return '12.000 € — 18.000 €';
    if (r < 25000) return '18.000 € — 25.000 €';
    if (r < 35000) return '25.000 € — 35.000 €';
    if (r < 50000) return '35.000 € — 50.000 €';
    return 'Más de 50.000 €';
});

const maxSalarySuggestion = computed(() => {
    const r = data.value.annualRevenue ?? 0;
    if (!r) return null;
    const cuota = (data.value.cuotaMonthly ?? cuotaSuggestion.value) * 12;
    const taxes = r * 0.18;
    const net = r - cuota - taxes;
    return Math.max(0, Math.round(net / 12));
});

const canContinue = computed(() => {
    if (currentStep.value === 1) return !!data.value.annualRevenue && data.value.annualRevenue > 0;
    if (currentStep.value === 2) return !!data.value.cuotaMonthly && data.value.cuotaMonthly > 0;
    if (currentStep.value === 3) return data.value.monthlySalary !== null && data.value.monthlySalary >= 0;
    return true;
});

const next = () => {
    if (currentStep.value === 1 && !data.value.cuotaMonthly) {
        data.value.cuotaMonthly = cuotaSuggestion.value;
    }
    if (currentStep.value === 2 && data.value.monthlySalary === null && maxSalarySuggestion.value) {
        data.value.monthlySalary = maxSalarySuggestion.value;
    }
    currentStep.value++;
};

const back = () => currentStep.value--;

const finish = () => {
    finishing.value = true;
    router.post(
        '/onboarding',
        {
            annualRevenue: data.value.annualRevenue,
            cuotaMonthly: data.value.cuotaMonthly,
            monthlySalary: data.value.monthlySalary,
        },
        {
            // 419 = session/CSRF expirée : on recharge plutôt que de laisser l'utilisateur bloqué.
            onError: (errors) => {
                if (errors?._token || errors?.csrf) {
                    window.location.reload();
                }
            },
            onFinish: () => (finishing.value = false),
        },
    );
};

const monthlySalaryTotal = computed(() => (data.value.monthlySalary ?? 0) * 12);
const cuotaAnnualTotal = computed(() => (data.value.cuotaMonthly ?? 0) * 12);
</script>

<template>
    <div class="space-y-6">
        <!-- Stepper -->
        <div class="flex items-center gap-3">
            <template v-for="(step, i) in steps" :key="step.id">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold"
                        :class="
                            currentStep > step.id
                                ? 'bg-emerald-600 text-white'
                                : currentStep === step.id
                                  ? 'bg-emerald-100 text-emerald-700 ring-2 ring-emerald-600'
                                  : 'bg-surface-100 text-surface-400'
                        "
                    >
                        <i v-if="currentStep > step.id" class="pi pi-check text-xs" />
                        <span v-else>{{ step.id }}</span>
                    </div>
                    <span
                        class="hidden text-sm font-medium md:inline"
                        :class="currentStep === step.id ? 'text-surface-900' : 'text-surface-500'"
                    >
                        {{ step.label }}
                    </span>
                </div>
                <div
                    v-if="i < steps.length - 1"
                    class="h-px flex-1"
                    :class="currentStep > step.id ? 'bg-emerald-600' : 'bg-surface-200'"
                />
            </template>
        </div>

        <!-- Step 1 : Facturación -->
        <section v-if="currentStep === 1" class="rounded-lg border border-surface-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-surface-900">¿Cuánto piensas facturar este año?</h2>
            <p class="mt-1 text-sm text-surface-500">
                Incluye el total de tus facturas (base imponible, sin IVA) que esperas emitir durante el año natural.
            </p>

            <div class="mt-5 flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-700">Facturación anual prevista (€)</label>
                <InputNumber
                    v-model="data.annualRevenue"
                    :minFractionDigits="0"
                    :maxFractionDigits="0"
                    locale="es-ES"
                    suffix=" €"
                    placeholder="Ej: 45.000"
                    fluid
                />
            </div>

            <div class="mt-5 grid grid-cols-2 gap-2 md:grid-cols-4">
                <button
                    v-for="value in [20000, 35000, 50000, 80000]"
                    :key="value"
                    type="button"
                    class="rounded-lg border border-surface-200 px-3 py-2 text-sm font-medium text-surface-700 transition-colors hover:border-emerald-400 hover:bg-emerald-50"
                    @click="data.annualRevenue = value"
                >
                    {{ formatEuros(value) }}
                </button>
            </div>
        </section>

        <!-- Step 2 : Cuota -->
        <section v-if="currentStep === 2" class="rounded-lg border border-surface-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-surface-900">¿Cuánto pagas de cuota de autónomos?</h2>
            <p class="mt-1 text-sm text-surface-500">
                Según la facturación que has indicado, te sugerimos una cuota orientativa. Confirma o ajusta el importe real.
            </p>

            <div class="mt-5 rounded-lg bg-emerald-50 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Sugerencia</p>
                        <p class="text-2xl font-bold text-emerald-700">{{ formatEuros(cuotaSuggestion) }}/mes</p>
                        <p class="mt-1 text-xs text-emerald-600">Tramo: {{ cuotaTramo }}</p>
                    </div>
                    <Tag value="Tabla 2026" severity="secondary" />
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-700">Cuota mensual (€)</label>
                <InputNumber
                    v-model="data.cuotaMonthly"
                    :minFractionDigits="2"
                    :maxFractionDigits="2"
                    locale="es-ES"
                    suffix=" €"
                    fluid
                />
            </div>

            <Message severity="info" size="small" variant="simple" class="mt-3">
                Este importe se añadirá como cargo recurrente mensual.
            </Message>
        </section>

        <!-- Step 3 : Salario -->
        <section v-if="currentStep === 3" class="rounded-lg border border-surface-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-surface-900">¿Cuánto te quieres pagar cada mes?</h2>
            <p class="mt-1 text-sm text-surface-500">
                Este es el importe que transferirás cada mes desde tu cuenta de negocio a tu cuenta personal.
            </p>

            <div v-if="maxSalarySuggestion" class="mt-5 rounded-lg bg-violet-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-violet-700">Máximo sostenible estimado</p>
                <p class="text-2xl font-bold text-violet-700">{{ formatEuros(maxSalarySuggestion) }}/mes</p>
                <p class="mt-1 text-xs text-violet-600">Después de cuota, IVA y IRPF estimados.</p>
            </div>

            <div class="mt-5 flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-700">Salario mensual (€)</label>
                <InputNumber
                    v-model="data.monthlySalary"
                    :minFractionDigits="0"
                    :maxFractionDigits="0"
                    locale="es-ES"
                    suffix=" €"
                    fluid
                />
            </div>

            <Message
                v-if="data.monthlySalary && maxSalarySuggestion && data.monthlySalary > maxSalarySuggestion"
                severity="warn"
                size="small"
                variant="simple"
                class="mt-3"
            >
                Ojo : este salario supera la estimación sostenible. Tu saldo acumulado podría volverse negativo.
            </Message>
        </section>

        <!-- Step 4 : Resumen -->
        <section v-if="currentStep === 4" class="space-y-4">
            <div class="rounded-lg border border-surface-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-surface-900">Resumen de tu año</h2>
                <p class="mt-1 text-sm text-surface-500">
                    Estas son tus previsiones para el año. Podrás ajustarlas en cualquier momento.
                </p>

                <dl class="mt-5 space-y-3">
                    <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                        <dt class="text-sm text-surface-600">Facturación prevista</dt>
                        <dd class="font-semibold text-surface-900">{{ formatEuros(data.annualRevenue) }}</dd>
                    </div>
                    <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                        <dt class="text-sm text-surface-600">Cuota mensual</dt>
                        <dd class="text-right">
                            <span class="font-semibold text-surface-900">{{ formatEuros(data.cuotaMonthly) }}</span>
                            <span class="ml-2 text-xs text-surface-500">({{ formatEuros(cuotaAnnualTotal) }}/año)</span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-surface-600">Salario mensual</dt>
                        <dd class="text-right">
                            <span class="font-semibold text-surface-900">{{ formatEuros(data.monthlySalary) }}</span>
                            <span class="ml-2 text-xs text-surface-500">({{ formatEuros(monthlySalaryTotal) }}/año)</span>
                        </dd>
                    </div>
                </dl>
            </div>

            <Message severity="success" size="small" variant="simple">
                Al finalizar, generaremos tu flujo de caja anual con estas previsiones. Podrás revisarlo en cualquier momento.
            </Message>
        </section>

        <!-- Navigation -->
        <div class="flex justify-between">
            <Button
                v-if="currentStep > 1"
                label="Atrás"
                icon="pi pi-arrow-left"
                severity="secondary"
                outlined
                @click="back"
            />
            <span v-else />

            <Button
                v-if="currentStep < 4"
                label="Siguiente"
                icon="pi pi-arrow-right"
                iconPos="right"
                :disabled="!canContinue"
                @click="next"
            />
            <Button
                v-else
                label="Generar flujo de caja"
                icon="pi pi-check"
                severity="success"
                :loading="finishing"
                @click="finish"
            />
        </div>
    </div>
</template>
