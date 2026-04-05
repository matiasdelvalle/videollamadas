<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { api } from '@/lib/api'
import { DateFormat } from '@/lib/dateUtils';
import EstadoPaciente from '@/Components/VideoConsulta/EstadoPaciente.vue'

const props = defineProps({
    consultaId: {
        type: [String, Number],
        required: true,
    },
    accessToken: {
        type: String,
        required: true,
    },
})

const loading = ref(true)
const error = ref('')
const consulta = ref(null)
const jitsiUrl = ref(null)

let poller = null

const fetchConsulta = async () => {
    try {
        const { data } = await api.get(`/api/video-consultas/${props.consultaId}`)
        consulta.value = data
        error.value = ''
    } catch (e) {
        console.error(e)
        error.value = 'No se pudo cargar la consulta.'
    } finally {
        loading.value = false
    }
}

const avisarConexionPaciente = async () => {
    try {
        await api.post(`/api/video-consultas/${props.consultaId}/paciente-conectado`)
        await fetchConsulta()
    } catch (e) {
        console.error(e)
        error.value = 'No se pudo registrar la conexión del paciente.'
    }
}

const reintentarConexion = async () => {
    try {
        await api.post(`/api/video-consultas/${props.consultaId}/reintentar`)
        await avisarConexionPaciente()
    } catch (e) {
        console.error(e)
        error.value = 'No se pudo reintentar la conexión.'
    }
}

const loadJitsi = async () => {
    try {
        const { data } = await api.get(`/api/video-consultas/paciente/${props.accessToken}/join`)

        if (data?.ok) {
            const domain = data.data.domain
            const room = data.data.room
            const jwt = data.data.jwt
            jitsiUrl.value = `https://${domain}/${room}?jwt=${jwt}`
            error.value = ''
        }
    } catch (e) {
        console.error(e)
        jitsiUrl.value = null
        error.value = e.response?.data?.error || 'No se pudo iniciar la videollamada.'
    }
}

const titulo = computed(() => {
    if (!consulta.value) return 'Sala de espera'

    const estado = consulta.value.estado

    if (estado === 'inactiva') return 'Acceso aún no habilitado'
    if (estado === 'activa' || estado === 'en_espera') return 'Sala de espera'
    if (estado === 'en_consulta') return 'Consulta en curso'
    if (estado === 'finalizada') return 'Consulta finalizada'
    if (estado === 'vencida') return 'Consulta vencida'
    if (estado === 'incompleta_paciente') return 'Consulta incompleta'
    if (estado === 'incompleta_medico') return 'Consulta incompleta'
    if (estado === 'cancelada') return 'Consulta cancelada'

    return 'Sala de espera'
})

const subtitulo = computed(() => {
    if (!consulta.value) return ''

    const estado = consulta.value.estado

    if (estado === 'inactiva') {
        return 'La sala todavía no está disponible. Vas a poder ingresar cuando la consulta se habilite.'
    }

    if (estado === 'activa' || estado === 'en_espera') {
        return 'Ya estás conectado. Aguarde a que el profesional habilite el ingreso.'
    }

    if (estado === 'en_consulta') {
        return 'La videollamada está activa.'
    }

    if (estado === 'finalizada') {
        return 'Gracias por utilizar nuestro servicio.'
    }

    if (estado === 'vencida') {
        return 'La consulta no llegó a realizarse dentro del tiempo previsto.'
    }

    if (estado === 'incompleta_paciente') {
        return 'La consulta fue marcada como incompleta porque no se logró concretar la atención.'
    }

    if (estado === 'incompleta_medico') {
        return 'La consulta fue marcada como incompleta porque no se logró concretar la atención.'
    }

    if (estado === 'cancelada') {
        return 'La consulta fue cancelada antes de realizarse.'
    }

    return ''
})

const mostrarBotonConectar = computed(() => {
    if (!consulta.value) return false
    return ['activa', 'en_espera'].includes(consulta.value.estado) && !consulta.value.paciente_conectado
})

const mostrarBotonReintentar = computed(() => {
    if (!consulta.value) return false
    return ['activa', 'en_espera'].includes(consulta.value.estado)
})

const mostrarIframe = computed(() => {
    if (!consulta.value) return false
    return consulta.value.estado === 'en_consulta' && !!jitsiUrl.value
})

const mostrarBloqueFinal = computed(() => {
    if (!consulta.value) return false
    return ['finalizada', 'cancelada', 'vencida', 'incompleta_paciente', 'incompleta_medico'].includes(consulta.value.estado)
})

watch(
    () => consulta.value?.estado,
    async (nuevoEstado) => {
        if (nuevoEstado === 'en_consulta' && !jitsiUrl.value) {
            await loadJitsi()
        }

        if (nuevoEstado !== 'en_consulta') {
            jitsiUrl.value = null
        }
    }
)

onMounted(async () => {
    await fetchConsulta()

    if (consulta.value && ['activa', 'en_espera'].includes(consulta.value.estado)) {
        await avisarConexionPaciente()
    }

    if (consulta.value?.estado === 'en_consulta') {
        await loadJitsi()
    }

    poller = setInterval(fetchConsulta, 5000)
})

onBeforeUnmount(() => {
    if (poller) clearInterval(poller)
})
</script>

<template>
    <div class="min-h-screen p-4 md:p-6">
        <div class="mx-auto max-w-4xl">
            <div v-if="loading" class="rounded-3xl bg-white p-8 shadow-sm">
                <div class="text-slate-500">Cargando consulta...</div>
            </div>

            <div v-else-if="error && !consulta" class="rounded-3xl bg-white p-8 shadow-sm">
                <div class="text-red-600">{{ error }}</div>
            </div>

            <div v-else class="space-y-6">

                <div class="rounded-3xl bg-white p-8">
                    <div class="text-center gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-slate-800 md:text-3xl">{{ titulo }}</h1>
                            <p class="mt-2 text-sm text-slate-500 ">{{ subtitulo }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-center py-4 w-full">
                        <EstadoPaciente :estado="consulta.estado" :paciente-conectado="!!consulta.paciente_conectado" />
                    </div>

                    <div v-if="error" class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ error }}</div>

                    <div class="">
                        <div class="text-center p-4">
                            <div class="mt-1 font-semibold text-slate-800">
                                {{ consulta.medico_nombre || '-' }}
                            </div>
                            <div class="mt-1 font-semibold text-slate-800">
                                {{ consulta.especialidad || '-' }}
                            </div>
                        </div>
                        
                        <div class="p-4 flex justify-between">
                            <div class="mt-1 font-semibold text-slate-800 px-4">
                                {{  consulta.inicio_programado ? DateFormat(consulta.inicio_programado, 'DD-MM-YYYY') : '-' }}
                            </div>
                            <div class="mt-1 font-semibold text-slate-800 px-4">
                                {{  consulta.inicio_programado ? DateFormat(consulta.inicio_programado, 'HH:mm:ss') : '-' }}
                            </div>
                        </div>
                    </div>

                    <div v-if="consulta.estado === 'inactiva'" class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                        La sala fue creada pero todavía no está habilitada.
                    </div>

                    <div v-if="['activa', 'en_espera'].includes(consulta.estado)" class="mt-6 rounded-2xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                        Usted puede permanecer en espera hasta que el profesional le dé ingreso.
                    </div>

                    <div v-if="mostrarBloqueFinal" class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        La consulta ya no se encuentra activa.
                    </div>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <button
                            v-if="mostrarBotonConectar"
                            type="button"
                            class="rounded-xl bg-slate-800 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-900"
                            @click="avisarConexionPaciente"
                        >
                            Ingresar a sala de espera
                        </button>

                        <button
                            v-if="mostrarBotonReintentar"
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            @click="reintentarConexion"
                        >
                            Reintentar conexión
                        </button>
                    </div>
                </div>

                <div v-if="mostrarIframe" class="overflow-hidden rounded-3xl bg-white p-3 shadow-sm">
                    <div class="mb-3 px-2 text-sm font-medium text-slate-600">
                        Consulta activa
                    </div>
                    <iframe :src="jitsiUrl" class="h-[70vh] w-full rounded-2xl border border-slate-200" allow="camera; microphone; fullscreen; display-capture"/>
                </div>
            </div>
        </div>
    </div>
</template>