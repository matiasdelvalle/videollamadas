<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { api } from '@/lib/api'
import { DateFormat } from '@/lib/dateUtils'

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
const ingresoConfirmado = ref(false)

const jitsiContainer = ref(null)
const jitsiData = ref({
    domain: null,
    room: null,
    jwt: null,
})

let poller = null
let jitsiApi = null
let cargandoJitsi = false

const fetchConsulta = async () => {
    try {
        const { data } = await api.get(`/api/video-consultas/${props.consultaId}`)
        consulta.value = data
        error.value = ''

        if (consulta.value?.estado === 'en_consulta' && !jitsiApi && !cargandoJitsi) {
            await loadJitsi({ silencioso: true })
        }
    } catch (e) {
        console.error(e)
        error.value = 'No se pudo cargar la consulta.'
    } finally {
        loading.value = false
    }
}

const avisarConexionPaciente = async (confirmarIngreso = false) => {
    try {
        await api.post(`/api/video-consultas/${props.consultaId}/paciente-conectado`)
        if (confirmarIngreso) ingresoConfirmado.value = true
        await fetchConsulta()
    } catch (e) {
        console.error(e)
        error.value = 'No se pudo registrar la conexión del paciente.'
    }
}

const loadJitsiScript = (domain) => {
    return new Promise((resolve, reject) => {
        if (window.JitsiMeetExternalAPI) {
            return resolve()
        }

        const existing = document.querySelector('script[data-jitsi-api="1"]')
        if (existing) {
            existing.addEventListener('load', resolve)
            existing.addEventListener('error', reject)
            return
        }

        const script = document.createElement('script')
        script.src = `https://${domain}/external_api.js`
        script.dataset.jitsiApi = '1'
        script.onload = resolve
        script.onerror = reject
        document.body.appendChild(script)
    })
}

const destroyJitsi = () => {
    if (jitsiApi) {
        try {
            jitsiApi.dispose()
        } catch (e) {
            console.error('Error disposing Jitsi', e)
        }
        jitsiApi = null
    }

    jitsiData.value = {
        domain: null,
        room: null,
        jwt: null,
    }

    if (jitsiContainer.value) {
        jitsiContainer.value.innerHTML = ''
    }
}

const mountJitsi = async () => {
    if (!jitsiContainer.value) return
    if (!jitsiData.value.domain || !jitsiData.value.room || !jitsiData.value.jwt) return
    if (jitsiApi) return

    try {
        await loadJitsiScript(jitsiData.value.domain)

        jitsiApi = new window.JitsiMeetExternalAPI(jitsiData.value.domain, {
            parentNode: jitsiContainer.value,
            roomName: jitsiData.value.room,
            jwt: jitsiData.value.jwt,
            width: '100%',
            height: '100%',
            configOverwrite: {
                disableDeepLinking: true,
                prejoinPageEnabled: false,
                prejoinConfig: {
                    enabled: false,
                },
                startWithAudioMuted: true,
                startWithVideoMuted: true,
            },
            interfaceConfigOverwrite: {
                TOOLBAR_BUTTONS: [
                    'microphone',
                    'camera',
                    'fullscreen',
                    'chat',
                    'participants-pane',
                    'hangup'
                ],
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                SHOW_BRAND_WATERMARK: false,
                DEFAULT_LOGO_URL: '',
                DEFAULT_WATERMARK_LOGO: '',
            },
        })

        error.value = ''
    } catch (e) {
        console.error('Error mounting Jitsi', e)
        error.value = 'No se pudo montar la videollamada.'
    }
}

const loadJitsi = async ({ silencioso = false } = {}) => {
    if (jitsiApi || cargandoJitsi) return

    cargandoJitsi = true

    try {
        const { data } = await api.get(`/api/video-consultas/paciente/${props.accessToken}/join`)

        if (data?.ok) {
            jitsiData.value = {
                domain: data.data.domain,
                room: data.data.room,
                jwt: data.data.jwt,
            }

            await nextTick()
            await mountJitsi()
            error.value = ''
        }
    } catch (e) {
        console.error(e)
        destroyJitsi()
        if (!silencioso) {
            error.value = e.response?.data?.error || 'No se pudo iniciar la videollamada.'
        }
    } finally {
        cargandoJitsi = false
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

const estadoSalaTitulo = computed(() => {
    if (!consulta.value) return ''

    if (consulta.value.estado === 'inactiva') return 'Sala no disponible'
    if (['activa', 'en_espera'].includes(consulta.value.estado)) {
        return ingresoConfirmado.value ? 'Conectado - en sala de espera' : 'Sala habilitada'
    }
    if (consulta.value.estado === 'en_consulta') return 'Consulta en curso'
    if (consulta.value.estado === 'finalizada') return 'Consulta finalizada'
    if (consulta.value.estado === 'cancelada') return 'Consulta cancelada'
    if (consulta.value.estado === 'vencida') return 'Consulta vencida'
    if (['incompleta_paciente', 'incompleta_medico'].includes(consulta.value.estado)) return 'Consulta incompleta'

    return 'No conectado'
})

const estadoSalaTexto = computed(() => {
    if (!consulta.value) return ''

    if (consulta.value.estado === 'inactiva') {
        return 'La videollamada estará disponible 15 minutos antes del turno.'
    }

    if (['activa', 'en_espera'].includes(consulta.value.estado)) {
        return ingresoConfirmado.value
            ? 'El profesional habilitará su ingreso en unos minutos.'
            : 'Ya puede conectarse a la videollamada. El profesional lo atenderá en el horario del turno.'
    }

    if (consulta.value.estado === 'en_consulta') return 'La videollamada está activa.'
    if (consulta.value.estado === 'finalizada') return 'La atención ha finalizado.'
    if (consulta.value.estado === 'cancelada') return 'La atención fue cancelada.'
    if (consulta.value.estado === 'vencida') return 'El horario previsto para la atención ya pasó.'

    return 'No fue posible completar la atención.'
})

const estadoSalaClase = computed(() => {
    if (!consulta.value) return 'border-slate-200 bg-slate-50 text-slate-700'

    if (consulta.value.estado === 'inactiva') return 'border-red-200 bg-red-50 text-red-700'
    if (['activa', 'en_espera'].includes(consulta.value.estado)) {
        return ingresoConfirmado.value
            ? 'border-green-200 bg-green-50 text-green-700'
            : 'border-yellow-200 bg-yellow-50 text-yellow-800'
    }
    if (consulta.value.estado === 'en_consulta') {
        return 'border-green-200 bg-green-50 text-green-700'
    }
    if (consulta.value.estado === 'cancelada') return 'border-red-200 bg-red-50 text-red-700'

    return 'border-slate-200 bg-slate-50 text-slate-700'
})

const mostrarBotonConectar = computed(() => {
    if (!consulta.value) return false
    return ['activa', 'en_espera'].includes(consulta.value.estado) && !ingresoConfirmado.value
})

const mostrarJitsi = computed(() => {
    if (!consulta.value) return false
    return consulta.value.estado === 'en_consulta'
})

const mostrarBloqueFinal = computed(() => {
    if (!consulta.value) return false
    return ['finalizada', 'cancelada', 'vencida', 'incompleta_paciente', 'incompleta_medico'].includes(consulta.value.estado)
})

watch(
    () => consulta.value?.estado,
    async (nuevoEstado) => {
        if (nuevoEstado === 'activa') {
            await avisarConexionPaciente()
            return
        }

        if (nuevoEstado !== 'en_consulta') {
            destroyJitsi()
        }
    }
)

onMounted(async () => {
    await fetchConsulta()

    if (consulta.value && ['inactiva', 'activa', 'en_espera'].includes(consulta.value.estado)) {
        await avisarConexionPaciente()
    }

    poller = setInterval(fetchConsulta, 5000)
})

onBeforeUnmount(() => {
    if (poller) clearInterval(poller)
    destroyJitsi()
})
</script>

<template>
    <div class="min-h-screen bg-slate-100 p-2 md:p-3">
        <div class="mx-auto max-w-md space-y-2">
            <div class="rounded-t-xl bg-blue-800 px-3 py-2 text-center text-white shadow">
                <h1 class="text-base font-semibold">Videoconsulta Médica</h1>
            </div>

            <div v-if="loading" class="rounded-xl bg-white p-4 shadow">
                <div class="text-slate-500">Cargando consulta...</div>
            </div>

            <div v-else-if="error && !consulta" class="rounded-xl bg-white p-4 shadow">
                <div class="text-red-600">{{ error }}</div>
            </div>

            <template v-else-if="consulta">
                <div class="rounded-xl bg-white p-3 shadow">


                    <div v-if="consulta.estado === 'inactiva'" class="flex justify-center py-3">
                        <div class="flex h-24 w-24 items-center justify-center rounded-full text-blue-500">
                            <svg class="h-24 w-24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        
                    </div>

                    <div class="text-center">
                        <h2 class="text-xl font-semibold text-blue-800">{{ titulo }}</h2>
                    </div>

                    <div class="mt-2 rounded-lg border px-3 py-2 text-center" :class="estadoSalaClase">
                        <div class="text-sm font-semibold">{{ estadoSalaTitulo }}</div>
                        <div class="text-xs leading-4">{{ estadoSalaTexto }}</div>
                    </div>

                    <div class="mt-2 rounded-xl bg-white p-3 shadow-sm">
                        <div class="text-center text-xl font-semibold text-blue-700">
                            {{ consulta.medico_nombre || 'Profesional' }}
                        </div>
                        <div class="mt-0.5 text-center text-xs text-slate-500">
                            {{ consulta.especialidad || 'Clínica médica' }}
                        </div>

                        <div class="mt-3 flex items-center justify-center gap-5 text-sm text-slate-700">
                            <div class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 2v3m8-3v3M3 9h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z" />
                                </svg>
                                <span>{{ consulta.inicio_programado ? DateFormat(consulta.inicio_programado, 'DD/MM/YYYY') : '-' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2" />
                                </svg>
                                <span>{{ consulta.inicio_programado ? DateFormat(consulta.inicio_programado, 'HH:mm') + ' hs.' : '-' }}</span>
                            </div>
                        </div>

                        <button
                            v-if="mostrarBotonConectar"
                            type="button"
                            class="mt-3 w-full rounded-full bg-blue-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-600"
                            @click="avisarConexionPaciente(true)"
                        >
                            Ingresar a la Videollamada
                        </button>

                        <button
                            v-else-if="consulta.estado === 'inactiva'"
                            type="button"
                            class="mt-3 w-full cursor-not-allowed rounded-full bg-slate-300 px-4 py-2 text-sm font-semibold text-white"
                            disabled
                        >
                            Ingresar a la videollamada
                        </button>
                    </div>

                    <div v-if="mostrarBloqueFinal" class="mt-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-center text-xs text-slate-600">
                        La consulta ya no se encuentra activa.
                    </div>
                </div>

                <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 shadow">
                    {{ error }}
                </div>

                <div v-if="!mostrarJitsi && !mostrarBloqueFinal" class="rounded-xl bg-white p-3 shadow">
                    <h3 class="mb-2 text-sm font-semibold text-slate-700">Recomendaciones para la consulta</h3>
                    <ul class="space-y-1 text-xs leading-4 text-slate-500">
                        <li>• Busque un lugar tranquilo y con buena conexión.</li>
                        <li>• Permanezca con el micrófono habilitado.</li>
                        <li>• Tenga a mano sus estudios, si los posee.</li>
                    </ul>
                </div>

                <div v-if="mostrarJitsi" class="overflow-hidden rounded-xl bg-white p-2 shadow">
                    <div
                        ref="jitsiContainer"
                        class="h-[65vh] min-h-[360px] max-h-[620px] w-full overflow-hidden rounded-xl border border-slate-200"
                    ></div>
                </div>

                <div v-if="mostrarJitsi" class="rounded-xl bg-white p-3 text-center text-xs text-slate-500 shadow">
                    La consulta está en curso. Utilice los controles del video para cámara, micrófono y salida.
                </div>
            </template>
        </div>
    </div>
</template>
