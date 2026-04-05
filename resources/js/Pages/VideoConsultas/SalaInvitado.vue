<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { api } from '@/lib/api'

const props = defineProps({
    accessToken: {
        type: String,
        required: true
    }
})

const loading = ref(true)
const error = ref('')
const waiting = ref(true)

const jitsiContainer = ref(null)

const jitsiData = ref({
    domain: null,
    room: null,
    jwt: null,
})

let jitsiApi = null
let interval = null

const loadJitsiScript = (domain) => {
    return new Promise((resolve, reject) => {
        if (window.JitsiMeetExternalAPI) {
            return resolve()
        }

        const script = document.createElement('script')
        script.src = `https://${domain}/external_api.js`
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
            console.error(e)
        }
        jitsiApi = null
    }

    if (jitsiContainer.value) {
        jitsiContainer.value.innerHTML = ''
    }
}

const mountJitsi = async () => {
    if (!jitsiData.value.domain) return

    await loadJitsiScript(jitsiData.value.domain)

    jitsiApi = new window.JitsiMeetExternalAPI(jitsiData.value.domain, {
        parentNode: jitsiContainer.value,
        roomName: jitsiData.value.room,
        jwt: jitsiData.value.jwt,
        width: '100%',
        height: '100%',
        configOverwrite: {
            prejoinPageEnabled: false,
            startWithAudioMuted: true,
            startWithVideoMuted: true,
        },
        interfaceConfigOverwrite: {
            TOOLBAR_BUTTONS: [
                'microphone',
                'camera',
                'fullscreen',
                'chat',
                'hangup'
            ],
        },
    })

    waiting.value = false
}

const tryJoin = async () => {
    try {
        const { data } = await api.get(`/api/video-consultas/invitado/${props.accessToken}/join`)

        if (data?.ok) {
            jitsiData.value = data.data
            await mountJitsi()
            error.value = ''
            loading.value = false
        }
    } catch (e) {
        loading.value = false
        waiting.value = true
        error.value = '' // no mostramos error, seguimos esperando
    }
}

onMounted(async () => {
    await tryJoin()

    interval = setInterval(async () => {
        if (!jitsiApi) {
            await tryJoin()
        }
    }, 3000)
})

onBeforeUnmount(() => {
    if (interval) clearInterval(interval)
    destroyJitsi()
})
</script>

<template>
    <div class="min-h-screen bg-slate-100 flex items-center justify-center p-4">
        <div class="w-full max-w-md space-y-4">

            <!-- Loading -->
            <div v-if="loading" class="bg-white p-6 rounded-xl shadow text-center text-slate-500">
                Conectando...
            </div>

            <!-- Waiting -->
            <div v-else-if="waiting" class="bg-white p-6 rounded-xl shadow text-center">
                <h2 class="text-lg font-semibold text-blue-700 mb-2">
                    Sala de espera
                </h2>

                <p class="text-slate-600 text-sm">
                    El profesional aún no inició la videollamada.
                </p>

                <p class="text-xs text-slate-400 mt-3">
                    Esta pantalla se actualizará automáticamente.
                </p>
            </div>

            <!-- Jitsi -->
            <div v-else class="bg-white p-2 rounded-xl shadow">
                <div
                    ref="jitsiContainer"
                    class="w-full h-[500px] rounded-xl overflow-hidden border"
                ></div>
            </div>

        </div>
    </div>
</template>