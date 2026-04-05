<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue'
import { api } from '@/lib/api'
import { DateFormat } from '@/lib/dateUtils'

const props = defineProps({
    consultaId: {
        type: [Number, String],
        required: true
    },
    accessToken: {
        type: String,
        required: true
    }
})

const consulta = ref(null)
const loading = ref(true)
const error = ref('')

const jitsiContainer = ref(null)

const jitsiData = ref({
    domain: null,
    room: null,
    jwt: null,
})

const invitadoEmail = ref('')
const invitados = ref([])
const invitando = ref(false)
const jitsiApi = ref(null)

let interval = null
let medicoConectadoInformado = false

const loadConsulta = async () => {
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

const informarMedicoConectado = async () => {
    if (medicoConectadoInformado) return

    try {
        await api.post(`/api/video-consultas/${props.consultaId}/medico-conectado`)
        medicoConectadoInformado = true
        await loadConsulta()
    } catch (e) {
        console.error('Error informando médico conectado', e)
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
        // script.src = `https://${domain}/external_api.js`

        script.src = "https://meet.jit.si/external_api.js"

        script.dataset.jitsiApi = '1'
        script.onload = resolve
        script.onerror = reject
        document.body.appendChild(script)
    })
}

const destroyJitsi = () => {
    if (jitsiApi.value) {
        try {
            jitsiApi.value.dispose()
        } catch (e) {
            console.error('Error disposing Jitsi', e)
        }
        jitsiApi.value = null
    }

    medicoConectadoInformado = false

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
    if (jitsiApi.value) return

    try {
        await loadJitsiScript(jitsiData.value.domain)

        jitsiApi.value = new window.JitsiMeetExternalAPI(jitsiData.value.domain, {
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

        jitsiApi.value.addEventListener('videoConferenceJoined', async () => {
            await informarMedicoConectado()
        })

        jitsiApi.value.addEventListener('participantRoleChanged', async (event) => {
            if (event?.role === 'moderator') {
                try {
                    jitsiApi.value.executeCommand('toggleLobby', true)
                } catch (e) {
                    console.error('No se pudo activar lobby', e)
                }
                await informarMedicoConectado()
            }
        })
        error.value = ''
    } catch (e) {
        console.error('Error mounting Jitsi', e)
        error.value = 'No se pudo montar la videollamada.'
    }
}

const loadJitsi = async () => {
    try {
        const { data } = await api.get(`/api/video-consultas/medico/${props.accessToken}/join`)
        if (data?.ok) {
            jitsiData.value = {
                domain: data.data.domain,
                room: data.data.room,
                jwt: data.data.jwt,
            }
            await mountJitsi()
            error.value = ''
        }
    } catch (e) {
        console.error('Error cargando Jitsi', e)
        destroyJitsi()
        error.value = e.response?.data?.error || 'No se pudo iniciar la videollamada.'
    }
}

const loadInvitados = async () => {
    try {
        const { data } = await api.get(`/api/video-consultas/${props.consultaId}`)
        if (Array.isArray(data?.invitados)) {
            invitados.value = data.invitados
        }
    } catch (e) {
        console.error('Error cargando invitados', e)
    }
}

const admitir = async () => {
    try {
        await api.post(`/api/video-consultas/${consulta.value.id}/admitir`)
        await loadConsulta()
    } catch (e) {
        console.error(e)
        error.value = 'No se pudo admitir al paciente.'
    }
}

const finalizar = async () => {
    try {
        await api.post(`/api/video-consultas/${consulta.value.id}/finalizar`)
        destroyJitsi()
        await loadConsulta()
    } catch (e) {
        console.error(e)
        error.value = 'No se pudo finalizar la consulta.'
    }
}

const cancelar = async () => {
    try {
        await api.post(`/api/video-consultas/${consulta.value.id}/cancelar`)
        destroyJitsi()
        await loadConsulta()
    } catch (e) {
        console.error(e)
        error.value = 'No se pudo cancelar la consulta.'
    }
}

const agregarInvitado = async () => {
    const email = invitadoEmail.value.trim()
    if (!email || invitando.value) return

    if (invitados.value.some(i => i.email === email)) {
        error.value = 'Ese invitado ya fue agregado.'
        return
    }

    invitando.value = true

    try {
        const { data } = await api.post(`/api/video-consultas/${props.consultaId}/invitados`, {
            email,
        })

        if (data?.ok && data?.data) {
            invitados.value.push(data.data)
            invitadoEmail.value = ''
            error.value = ''
        }
    } catch (e) {
        console.error(e)
        error.value = e.response?.data?.error || 'No se pudo agregar el invitado.'
    } finally {
        invitando.value = false
    }
}

const eliminarInvitado = async (invitado) => {
    try {
        if (invitado?.id) {
            await api.delete(`/api/video-consultas/${props.consultaId}/invitados/${invitado.id}`)
        }

        invitados.value = invitados.value.filter(i => i.id !== invitado.id && i.email !== invitado.email)
        error.value = ''
    } catch (e) {
        console.error(e)
        error.value = 'No se pudo eliminar el invitado.'
    }
}

const mostrarBloqueFinal = computed(() => {
    if (!consulta.value) return false
    return ['finalizada', 'cancelada', 'vencida', 'incompleta_paciente', 'incompleta_medico'].includes(consulta.value.estado)
})

const pacienteConectado = computed(() => !!consulta.value?.paciente_conectado)

const estadoPacienteTitulo = computed(() => {
    return pacienteConectado.value ? 'Paciente CONECTADO' : 'Paciente NO CONECTADO'
})

const estadoPacienteTexto = computed(() => {
    return pacienteConectado.value
        ? 'El paciente ingresó en la sala'
        : 'El paciente aún no ingresó en la sala'
})

const estadoPacienteClase = computed(() => {
    return pacienteConectado.value
        ? 'border-green-200 bg-green-50 text-green-700'
        : 'border-red-200 bg-red-50 text-red-700'
})

const roomLabel = computed(() => {
    return consulta.value?.room_name || '-'
})

const tiempoSala = computed(() => {
    if (!consulta.value?.inicio_programado) return '-'
    return DateFormat(consulta.value.inicio_programado, 'HH:mm')
})

const esperaActiva = computed(() => {
    if (!consulta.value) return false
    return ['inactiva', 'activa', 'en_espera'].includes(consulta.value.estado)
})

const jitsiActivo = computed(() => {
    return !!jitsiApi.value && consulta.value?.estado === 'en_consulta'
})

const mostrarPanelInicial = computed(() => {
    return !jitsiActivo.value
})

const textoBotonPrincipal = computed(() => {
    if (!consulta.value) return 'Ingresar a la Videollamada'
    return 'Ingresar a la Videollamada'
})

const botonPrincipalDeshabilitado = computed(() => {
    if (!consulta.value) return true
    if (consulta.value.estado === 'en_espera') return false
    return !pacienteConectado.value
})

const accionPrincipal = async () => {
    if (!consulta.value) return

    if (consulta.value.estado === 'en_espera') {
        await admitir()
    }
}

watch( () => consulta.value?.estado, async (nuevoEstado) => {
        if (nuevoEstado === 'en_consulta') {
            if (!jitsiApi.value) {
                await loadJitsi()
            }
        } else {
            destroyJitsi()
        }
    }
)

onMounted(async () => {
    await loadConsulta()
    await loadInvitados()

    if (consulta.value?.estado === 'en_consulta') {
        await loadJitsi()
    }

    interval = setInterval(async () => {
        await loadConsulta()
    }, 3000)
})

onBeforeUnmount(() => {
    if (interval) clearInterval(interval)
    destroyJitsi()
})
</script>

<template>
    <div class="min-h-screen bg-slate-100 p-4 md:p-6">
        <div class="mx-auto max-w-md space-y-4">
            <div class="rounded-t-xl bg-blue-800 px-4 py-3 text-center text-white shadow">
                <h1 class="text-lg font-semibold">Videoconsulta Médica</h1>
            </div>

            <div v-if="loading" class="rounded-xl bg-white p-6 shadow">
                <div class="text-slate-500">Cargando consulta...</div>
            </div>

            <div v-else-if="error && !consulta" class="rounded-xl bg-white p-6 shadow">
                <div class="text-red-600">{{ error }}</div>
            </div>

            <template v-else-if="consulta">
                <div v-if="mostrarPanelInicial" class="rounded-xl bg-blue-50 p-4 shadow">
                    <h2 class="mb-4 text-center text-2xl font-semibold text-blue-700">Sala de espera</h2>

                    <div
                        class="mb-4 rounded-lg border p-4 text-center"
                        :class="estadoPacienteClase"
                    >
                        <div class="font-semibold">{{ estadoPacienteTitulo }}</div>
                        <div class="text-sm">{{ estadoPacienteTexto }}</div>
                    </div>

                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <div class="mb-4 text-center text-2xl font-semibold text-blue-700">
                            {{ consulta.medico_nombre || 'Profesional' }}
                        </div>

                        <div class="mb-4 flex items-center justify-center gap-6 text-blue-500">
                            <div class="font-medium text-slate-700">
                                {{ consulta.inicio_programado ? DateFormat(consulta.inicio_programado, 'DD/MM/YYYY') : '-' }}
                            </div>
                            <div class="font-medium text-slate-700">
                                {{ consulta.inicio_programado ? DateFormat(consulta.inicio_programado, 'HH:mm') + ' hs.' : '-' }}
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mb-4 w-full rounded-full px-5 py-3 text-sm font-semibold transition"
                            :disabled="botonPrincipalDeshabilitado"
                            :class="botonPrincipalDeshabilitado
                                ? 'cursor-not-allowed bg-slate-300 text-slate-100'
                                : 'bg-blue-500 text-white hover:bg-blue-600'"
                            @click="accionPrincipal"
                        >
                            {{ textoBotonPrincipal }}
                        </button>

                        <div class="mb-2 text-center text-sm font-semibold text-slate-700">
                            ID Sala: {{ roomLabel }}
                        </div>

                        <div class="text-center text-sm text-slate-500">
                            <template v-if="!pacienteConectado">
                                El botón se habilitará cuando el paciente esté conectado.<br>
                                Podrá iniciar igualmente al iniciarse el turno.
                            </template>

                            <template v-else-if="consulta.estado === 'en_espera'">
                                Al ingresar usted será el moderador de la sesión.
                            </template>
                        </div>
                    </div>
                </div>

                <div v-if="mostrarPanelInicial" class="rounded-xl bg-white p-5 shadow">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Detalle de Conexión
                    </h3>

                    <ul class="space-y-2 text-sm text-slate-700">
                        <li>• Micrófono: Activo</li>
                        <li>• Cámara: Activa</li>
                        <li>• Estado: {{ consulta.estado }}</li>
                    </ul>

                    <div v-if="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        {{ error }}
                    </div>

                    <div v-if="mostrarBloqueFinal" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                        La consulta ya no se encuentra activa.
                    </div>

                    <div v-if="esperaActiva" class="mt-4">
                        <button
                            type="button"
                            class="w-full rounded-full bg-slate-600 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700"
                            @click="cancelar"
                        >
                            Cancelar consulta
                        </button>
                    </div>
                </div>

                <div
                    v-if="consulta.estado === 'en_consulta'"
                    class="overflow-hidden rounded-xl bg-white p-3 shadow"
                >
                    <div
                        ref="jitsiContainer"
                        class="h-[420px] w-full overflow-hidden rounded-xl border border-slate-200"
                    ></div>
                </div>

                <div v-if="consulta.estado === 'en_consulta'" class="rounded-xl bg-white p-4 shadow">
                    <button
                        type="button"
                        class="w-full rounded-full bg-red-600 px-5 py-3 text-sm font-semibold text-white hover:bg-red-700"
                        @click="finalizar"
                    >
                        Finalizar consulta
                    </button>
                </div>

                <div class="rounded-t-xl bg-blue-800 px-4 py-3 text-center text-white shadow">
                    <h3 class="font-semibold">Invitar a terceros</h3>
                </div>

                <div class="rounded-xl bg-white p-4 shadow">
                    <div class="mb-3 flex gap-2">
                        <input
                            v-model="invitadoEmail"
                            type="email"
                            placeholder="Email invitado"
                            class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500"
                            @keyup.enter="agregarInvitado"
                        >
                        <button
                            type="button"
                            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
                            :disabled="invitando"
                            @click="agregarInvitado"
                        >
                            {{ invitando ? 'Enviando...' : 'Agregar' }}
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="invitado in invitados"
                            :key="invitado.id || invitado.email"
                            class="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                        >
                            <div class="flex flex-col">
                                <span>{{ invitado.email }}</span>
                                <span class="text-xs text-slate-400">{{ invitado.estado || 'pendiente' }}</span>
                            </div>

                            <button
                                type="button"
                                class="text-slate-400 hover:text-red-500"
                                @click="eliminarInvitado(invitado)"
                            >
                                ×
                            </button>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-slate-500">
                        Al agregar un invitado se envía automáticamente el mail con el acceso.
                    </p>
                </div>

                <div v-if="error && consulta" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 shadow">
                    {{ error }}
                </div>
            </template>
        </div>
    </div>
</template>