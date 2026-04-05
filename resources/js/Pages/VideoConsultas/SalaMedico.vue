<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue'
import { api } from '@/lib/api'

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

let interval = null
let jitsiApi = null

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

const loadJitsiScript = () => {
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
        script.src = 'https://meet.jit.si/external_api.js'
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
        await loadJitsiScript()

        jitsiApi = new window.JitsiMeetExternalAPI(jitsiData.value.domain, {
            parentNode: jitsiContainer.value,
            roomName: jitsiData.value.room,
            jwt: jitsiData.value.jwt,
            width: '100%',
            height: '100%',
            configOverwrite: {
                disableDeepLinking: true,
                prejoinPageEnabled: false,
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

const mostrarBloqueFinal = computed(() => {
    if (!consulta.value) return false
    return ['finalizada', 'cancelada', 'vencida', 'incompleta_paciente', 'incompleta_medico'].includes(consulta.value.estado)
})

watch(
    () => consulta.value?.estado,
    async (nuevoEstado) => {
        if (nuevoEstado === 'en_consulta') {
            if (!jitsiApi) {
                await loadJitsi()
            }
        } else {
            destroyJitsi()
        }
    }
)

onMounted(async () => {
    await loadConsulta()

    if (consulta.value?.estado === 'en_consulta') {
        await loadJitsi()
    }

    interval = setInterval(loadConsulta, 3000)
})

onBeforeUnmount(() => {
    if (interval) clearInterval(interval)
    destroyJitsi()
})
</script>

<template>
    <div class="h-screen flex bg-gray-100">
        <div class="w-1/3 bg-white p-6 border-r flex flex-col gap-4">
            <h2 class="text-xl font-bold">Panel Médico</h2>

            <div v-if="loading">Cargando...</div>

            <template v-else-if="consulta">
                <div>
                    <strong>Estado:</strong>
                    <span class="ml-2 capitalize">{{ consulta.estado }}</span>
                </div>

                <div>
                    <strong>Paciente conectado:</strong>
                    <span class="ml-2">
                        {{ consulta.paciente_conectado ? 'Sí' : 'No' }}
                    </span>
                </div>

                <div v-if="error" class="rounded bg-red-50 border border-red-200 text-red-700 p-3 text-sm">
                    {{ error }}
                </div>

                <div v-if="consulta.estado === 'inactiva'">
                    <p class="text-gray-500">La consulta todavía no está habilitada.</p>
                </div>

                <div v-if="consulta.estado === 'activa'">
                    <p class="text-gray-500">Esperando paciente...</p>
                </div>

                <div v-if="consulta.estado === 'en_espera'">
                    <button
                        @click="admitir"
                        class="bg-green-600 text-white px-4 py-2 rounded"
                    >
                        Admitir paciente
                    </button>
                </div>

                <div v-if="consulta.estado === 'en_consulta'">
                    <button
                        @click="finalizar"
                        class="bg-red-600 text-white px-4 py-2 rounded"
                    >
                        Finalizar consulta
                    </button>
                </div>

                <div v-if="['inactiva', 'activa', 'en_espera'].includes(consulta.estado)">
                    <button
                        @click="cancelar"
                        class="bg-slate-600 text-white px-4 py-2 rounded"
                    >
                        Cancelar consulta
                    </button>
                </div>

                <div v-if="mostrarBloqueFinal">
                    <p class="text-gray-500">La consulta ya no se encuentra activa.</p>
                </div>
            </template>
        </div>

        <div class="flex-1 bg-black">
            <div
                v-if="consulta?.estado === 'en_consulta'"
                ref="jitsiContainer"
                class="w-full h-full"
            ></div>

            <div v-else class="h-full flex items-center justify-center text-white">
                <p>La videollamada aún no está activa</p>
            </div>
        </div>
    </div>
</template>