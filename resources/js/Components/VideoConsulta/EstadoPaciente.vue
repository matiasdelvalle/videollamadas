<script setup>
import { computed } from 'vue'

const props = defineProps({
    estado: {
        type: String,
        default: 'inactiva',
    },
    pacienteConectado: {
        type: Boolean,
        default: false,
    },
})

const label = computed(() => {
    if (props.estado === 'en_consulta') return 'En consulta'
    if (props.pacienteConectado || props.estado === 'en_espera') return 'Conectado - en sala de espera'
    return 'No conectado'
})

const clases = computed(() => {
    if (props.estado === 'en_consulta') return 'bg-green-100 text-green-700 border-green-200'
    if (props.pacienteConectado || props.estado === 'en_espera') return 'bg-yellow-100 text-yellow-800 border-yellow-200'
    return 'bg-red-100 text-red-700 border-red-200'
})
</script>

<template>
    <div v-if="props.estado !== 'finalizada'" class="inline-flex px-3 py-2 text-sm font-medium items-center justify-center w-full rounded-md" :class="clases">
        {{ label }}
    </div>
</template>