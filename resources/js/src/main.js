import { createApp } from 'vue'
import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import { setupAxiosInterceptors, useAuthStore } from '@/stores/useAuthStore'

// Styles
import '@core/scss/template/index.scss'
import '@layouts/styles/index.scss'

// Konfigurasi axios sebelum apapun
setupAxiosInterceptors()

const app = createApp(App)

// Register plugins (Vuetify, Pinia, Router)
registerPlugins(app)

// Restore session dari localStorage sebelum mount
// sehingga router guard dan UserProfile sudah punya data user
const authStore = useAuthStore()
authStore.restoreSession().finally(() => {
  app.mount('#app')
})
