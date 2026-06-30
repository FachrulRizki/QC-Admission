import { createApp } from 'vue'
import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import { setupAxiosInterceptors, useAuthStore } from '@/stores/useAuthStore'

// Styles
import '@core/scss/template/index.scss'
import '@layouts/styles/index.scss'

// 1. Setup axios (Bearer token, 401 interceptor)
setupAxiosInterceptors()

const app = createApp(App)

// 2. Register plugins (Vuetify, Pinia, Router)
registerPlugins(app)

// 3. Restore session dari localStorage — SINKRON, tidak blocking
//    Menu langsung tampil, fetchMe jalan di background
const authStore = useAuthStore()
authStore.restoreSession()   // tidak perlu .finally — tidak blocking mount

// 4. Mount langsung — tidak menunggu server
app.mount('#app')
