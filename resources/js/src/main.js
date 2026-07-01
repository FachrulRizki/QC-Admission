import { createApp } from 'vue'
import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import { setupAxiosInterceptors, useAuthStore } from '@/stores/useAuthStore'
import VueApexCharts from 'vue3-apexcharts'

// Styles
import '@core/scss/template/index.scss'
import '@layouts/styles/index.scss'

// 1. Setup axios (Bearer token, 401 interceptor)
setupAxiosInterceptors()

const app = createApp(App)

// 2. Register plugins (Vuetify, Pinia, Router)
registerPlugins(app)

// 3. Register ApexCharts globally
app.component('VueApexCharts', VueApexCharts)

// 4. Register v-click-outside directive
app.directive('click-outside', {
  beforeMount(el, binding) {
    el._clickOutsideHandler = (event) => {
      if (!el.contains(event.target)) {
        binding.value(event)
      }
    }
    document.addEventListener('mousedown', el._clickOutsideHandler)
  },
  unmounted(el) {
    document.removeEventListener('mousedown', el._clickOutsideHandler)
  },
})

// 4. Restore session dari localStorage — SINKRON, tidak blocking
const authStore = useAuthStore()
authStore.restoreSession()

// 5. Mount langsung
app.mount('#app')
