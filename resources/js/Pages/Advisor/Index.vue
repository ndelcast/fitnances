<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';

const props = defineProps({
    lastAnalysis: { type: Object, default: null },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const analyzing = ref(false);
const launchAnalysis = () => {
    analyzing.value = true;
    router.post(
        '/advisor/analyze',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                analyzing.value = false;
            },
        },
    );
};

const payload = computed(() => props.lastAnalysis?.payload || null);

const toneStyles = {
    excelente: {
        ring: 'ring-emerald-300',
        bg: 'bg-emerald-50',
        text: 'text-emerald-700',
        label: 'Excelente',
        emoji: '🌟',
    },
    bueno: {
        ring: 'ring-sky-300',
        bg: 'bg-sky-50',
        text: 'text-sky-700',
        label: 'Buen estado',
        emoji: '👍',
    },
    atencion: {
        ring: 'ring-amber-300',
        bg: 'bg-amber-50',
        text: 'text-amber-700',
        label: 'Atención',
        emoji: '⚠️',
    },
    critico: {
        ring: 'ring-red-300',
        bg: 'bg-red-50',
        text: 'text-red-700',
        label: 'Crítico',
        emoji: '🚨',
    },
};

const tone = computed(() => toneStyles[payload.value?.tono] || toneStyles.bueno);

const highlightStyles = {
    fortaleza: { bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-700', label: 'Fortaleza' },
    aviso: { bg: 'bg-amber-50', border: 'border-amber-200', text: 'text-amber-700', label: 'Aviso' },
    riesgo: { bg: 'bg-red-50', border: 'border-red-200', text: 'text-red-700', label: 'Riesgo' },
    oportunidad: { bg: 'bg-violet-50', border: 'border-violet-200', text: 'text-violet-700', label: 'Oportunidad' },
};

const priorityStyles = {
    alta: { severity: 'danger', label: 'Alta' },
    media: { severity: 'warn', label: 'Media' },
    baja: { severity: 'secondary', label: 'Baja' },
};
</script>

<template>
    <Head title="Consejero IA" />

    <AppLayout title="Consejero IA">
        <div class="mx-auto max-w-5xl space-y-6">
            <Message v-if="flash.success" severity="success" :closable="true" :life="3000">{{ flash.success }}</Message>
            <Message v-if="flash.error" severity="error" :closable="true">{{ flash.error }}</Message>

            <!-- Hero -->
            <Card class="overflow-hidden bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white">
                <template #content>
                    <div class="flex flex-col gap-4 p-2 md:flex-row md:items-center md:justify-between md:p-4">
                        <div class="flex-1">
                            <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider backdrop-blur">
                                <span>✨</span> Análisis financiero con IA
                            </div>
                            <h2 class="text-2xl font-bold md:text-3xl">¿Cómo va tu negocio realmente?</h2>
                            <p class="mt-2 max-w-xl text-sm text-white/90">
                                Un experto financiero virtual revisa tus números (cashflow, fiscal, cartera) y te
                                devuelve un veredicto con consejos accionables. Tarda unos 10-20 segundos.
                            </p>
                        </div>
                        <Button
                            :label="analyzing ? 'Analizando…' : (lastAnalysis ? 'Volver a analizar' : 'Lanzar análisis')"
                            :icon="analyzing ? 'pi pi-spin pi-spinner' : 'pi pi-sparkles'"
                            :loading="analyzing"
                            :disabled="analyzing"
                            severity="contrast"
                            size="large"
                            @click="launchAnalysis"
                        />
                    </div>
                </template>
            </Card>

            <!-- Empty state -->
            <Card v-if="!payload && !analyzing">
                <template #content>
                    <div class="flex flex-col items-center gap-3 py-12 text-center">
                        <div class="text-5xl">🤖</div>
                        <p class="text-lg font-semibold text-surface-900">Aún no has lanzado ningún análisis</p>
                        <p class="max-w-md text-sm text-surface-500">
                            Pulsa el botón de arriba y la IA mirará tus chiffres para darte un veredicto personalizado.
                        </p>
                    </div>
                </template>
            </Card>

            <!-- Loading state -->
            <Card v-if="analyzing">
                <template #content>
                    <div class="flex flex-col items-center gap-3 py-12 text-center">
                        <i class="pi pi-spin pi-cog text-4xl text-violet-500" />
                        <p class="text-lg font-semibold text-surface-900">La IA está revisando tus números…</p>
                        <p class="text-sm text-surface-500">Esto puede tardar unos segundos.</p>
                    </div>
                </template>
            </Card>

            <!-- Result -->
            <template v-if="payload && !analyzing">
                <!-- Verdict + score -->
                <Card>
                    <template #content>
                        <div class="flex flex-col gap-5 p-2 md:flex-row md:items-center md:p-4">
                            <div
                                class="flex flex-col items-center gap-1 rounded-2xl px-6 py-5 ring-2"
                                :class="[tone.bg, tone.ring]"
                            >
                                <span class="text-4xl">{{ tone.emoji }}</span>
                                <span class="text-5xl font-extrabold tabular-nums" :class="tone.text">{{ payload.puntuacion }}</span>
                                <span class="text-xs font-semibold uppercase tracking-wider" :class="tone.text">{{ tone.label }}</span>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs font-semibold uppercase tracking-wider text-surface-500">Veredicto</p>
                                <p class="mt-1 text-xl font-semibold text-surface-900">{{ payload.veredicto }}</p>
                                <p
                                    v-if="payload.felicitaciones"
                                    class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"
                                >
                                    🎉 {{ payload.felicitaciones }}
                                </p>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Highlights -->
                <Card v-if="payload.highlights?.length">
                    <template #title>
                        <span>Lo destacado</span>
                    </template>
                    <template #content>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div
                                v-for="(h, i) in payload.highlights"
                                :key="i"
                                class="rounded-xl border-2 p-4 transition-transform hover:-translate-y-0.5 hover:shadow-md"
                                :class="[
                                    (highlightStyles[h.tipo] || highlightStyles.aviso).bg,
                                    (highlightStyles[h.tipo] || highlightStyles.aviso).border,
                                ]"
                            >
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-3xl">{{ h.emoji || '💡' }}</span>
                                    <Tag
                                        :value="(highlightStyles[h.tipo] || highlightStyles.aviso).label"
                                        :class="(highlightStyles[h.tipo] || highlightStyles.aviso).text"
                                    />
                                </div>
                                <p
                                    class="text-base font-bold"
                                    :class="(highlightStyles[h.tipo] || highlightStyles.aviso).text"
                                >
                                    {{ h.titulo }}
                                </p>
                                <p class="mt-1.5 text-sm leading-relaxed text-surface-700">{{ h.detalle }}</p>
                            </div>
                        </div>
                    </template>
                </Card>

                <!-- Recommendations -->
                <Card v-if="payload.recomendaciones?.length">
                    <template #title>
                        <span>Plan de acción</span>
                    </template>
                    <template #content>
                        <ol class="space-y-3">
                            <li
                                v-for="(r, i) in payload.recomendaciones"
                                :key="i"
                                class="flex items-start gap-3 rounded-lg border border-surface-200 bg-white p-4 transition-colors hover:bg-surface-50"
                            >
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-violet-100 text-2xl">
                                    {{ r.emoji || '✅' }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-surface-900">{{ r.accion }}</span>
                                        <Tag
                                            :value="`Prioridad ${(priorityStyles[r.prioridad] || priorityStyles.media).label.toLowerCase()}`"
                                            :severity="(priorityStyles[r.prioridad] || priorityStyles.media).severity"
                                            class="!text-[10px]"
                                        />
                                    </div>
                                    <p class="mt-1 text-sm text-surface-600">{{ r.impacto }}</p>
                                </div>
                            </li>
                        </ol>
                    </template>
                </Card>

                <!-- Meta -->
                <p class="text-center text-xs text-surface-400">
                    Análisis generado el {{ formatDate(lastAnalysis.createdAt) }}
                    <span v-if="lastAnalysis.model">· modelo {{ lastAnalysis.model }}</span>
                    <span v-if="lastAnalysis.outputTokens">· {{ lastAnalysis.outputTokens }} tokens generados</span>
                </p>
            </template>
        </div>
    </AppLayout>
</template>
