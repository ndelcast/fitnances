<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: { type: Number, required: true }, // 0-100, contrôle l'arc
    centerText: { type: String, default: null }, // texte au centre (sinon affiche value)
    color: { type: String, default: 'emerald' }, // emerald | amber | violet | red | sky
    size: { type: Number, default: 130 },
    stroke: { type: Number, default: 11 },
});

const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const clamped = computed(() => Math.max(0, Math.min(100, props.value)));
const offset = computed(() => circumference.value * (1 - clamped.value / 100));

const palette = {
    emerald: { track: '#d1fae5', stroke: '#059669', text: 'text-emerald-700' },
    amber: { track: '#fef3c7', stroke: '#d97706', text: 'text-amber-700' },
    violet: { track: '#ede9fe', stroke: '#7c3aed', text: 'text-violet-700' },
    red: { track: '#fee2e2', stroke: '#dc2626', text: 'text-red-700' },
    sky: { track: '#e0f2fe', stroke: '#0284c7', text: 'text-sky-700' },
};

const colors = computed(() => palette[props.color] ?? palette.emerald);
const display = computed(() => props.centerText ?? `${clamped.value} %`);
</script>

<template>
    <div class="relative inline-flex items-center justify-center">
        <svg :width="size" :height="size" class="-rotate-90">
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                :stroke="colors.track"
                :stroke-width="stroke"
            />
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                :stroke="colors.stroke"
                :stroke-width="stroke"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="offset"
                class="transition-all duration-700 ease-out"
            />
        </svg>
        <div class="absolute inset-0 flex items-center justify-center px-3 text-center">
            <span class="text-lg font-bold leading-tight tabular-nums" :class="colors.text">{{ display }}</span>
        </div>
    </div>
</template>
