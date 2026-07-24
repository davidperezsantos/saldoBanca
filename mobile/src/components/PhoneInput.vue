<script setup>
import { ref, watch } from 'vue';
import countries from '../data/countries.json';

const props = defineProps({
    modelValue: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

// Un <select> nativo no puede renderizar un <img> por opción como hace el Select de PrimeVue
// en el panel web (flagcdn.com) — un <option> solo acepta texto. La bandera como emoji Unicode
// (par de "Regional Indicator Symbols" a partir del código ISO de 2 letras) sí se puede meter
// como texto, y Android la dibuja con su fuente de emoji nativa sin depender de una imagen
// externa — mejor además para uso sin conexión.
function flagEmoji(isoCode) {
    return isoCode
        .toUpperCase()
        .replace(/./g, (char) => String.fromCodePoint(char.charCodeAt(0) + 127397));
}

function splitPhone(value) {
    const raw = value || '';
    const country = countries.find((c) => raw.startsWith(c.dial)) ?? countries.find((c) => c.code === 'cu');
    const local = country && raw.startsWith(country.dial) ? raw.slice(country.dial.length) : raw;
    return { dial: country?.dial ?? '+53', local };
}

const initial = splitPhone(props.modelValue);
const dial = ref(initial.dial);
const local = ref(initial.local);

watch([dial, local], () => {
    emit('update:modelValue', local.value ? `${dial.value}${local.value}` : '');
});
</script>

<template>
  <div class="phone-input">
    <select v-model="dial" class="dial-select">
      <option v-for="c in countries" :key="c.code" :value="c.dial">{{ flagEmoji(c.code) }} {{ c.name }} ({{ c.dial }})</option>
    </select>
    <input v-model="local" type="tel" class="local-input" placeholder="Número" />
  </div>
</template>

<style scoped>
.phone-input {
    display: flex;
    gap: 0.4rem;
}
.dial-select {
    flex: 0 0 auto;
    max-width: 40%;
    padding: 0.55rem 0.4rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.85rem;
    background: white;
    color: #333;
}
.local-input {
    flex: 1;
    min-width: 0;
    padding: 0.55rem 0.65rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.95rem;
}
</style>
