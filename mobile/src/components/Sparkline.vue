<script setup>
import { computed } from 'vue';

const props = defineProps({
    values: { type: Array, required: true },
});

const WIDTH = 100;
const HEIGHT = 32;
const PAD = 4;

const points = computed(() => {
    const values = props.values;
    if (values.length < 2) return [];

    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const usableHeight = HEIGHT - PAD * 2;
    const step = WIDTH / (values.length - 1);

    return values.map((v, i) => ({
        x: i * step,
        y: PAD + usableHeight - ((v - min) / range) * usableHeight,
    }));
});

const linePoints = computed(() => points.value.map((p) => `${p.x},${p.y}`).join(' '));

const areaPoints = computed(() => {
    if (!points.value.length) return '';
    const first = points.value[0];
    const last = points.value[points.value.length - 1];
    return `${first.x},${HEIGHT} ${linePoints.value} ${last.x},${HEIGHT}`;
});

const lastPoint = computed(() => points.value[points.value.length - 1] ?? null);
</script>

<template>
  <svg v-if="points.length" class="sparkline" viewBox="0 0 100 32" preserveAspectRatio="none">
    <polygon :points="areaPoints" class="area" />
    <polyline :points="linePoints" class="line" />
    <circle v-if="lastPoint" :cx="lastPoint.x" :cy="lastPoint.y" r="4" class="dot-ring" />
    <circle v-if="lastPoint" :cx="lastPoint.x" :cy="lastPoint.y" r="3" class="dot" />
  </svg>
</template>

<style scoped>
.sparkline {
    width: 100%;
    height: 40px;
    display: block;
    overflow: visible;
}
.area {
    fill: var(--primary-dark);
    opacity: 0.1;
    stroke: none;
}
.line {
    fill: none;
    stroke: var(--primary-dark);
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.dot {
    fill: var(--primary-dark);
}
.dot-ring {
    fill: white;
}
</style>
