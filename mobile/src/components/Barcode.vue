<script setup>
import { ref, watch, onMounted } from 'vue';
import JsBarcode from 'jsbarcode';

const props = defineProps({
    value: { type: String, required: true },
});

const svgEl = ref(null);

function render() {
    if (!svgEl.value || !props.value) return;
    JsBarcode(svgEl.value, props.value, {
        format: 'CODE128',
        displayValue: false,
        margin: 0,
        height: 56,
        width: 2,
        background: 'transparent',
        lineColor: '#0b0b0b',
    });
}

onMounted(render);
watch(() => props.value, render);
</script>

<template>
  <svg ref="svgEl" class="barcode"></svg>
</template>

<style scoped>
.barcode {
    width: 100%;
    height: auto;
}
</style>
