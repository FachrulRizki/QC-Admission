import { createApp, h }         from 'vue'
import { createInertiaApp }     from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { registerPlugins }      from '@core/utils/plugins'
import { setupAxiosDefaults }   from '@/stores/useAuthStore'
import VueApexCharts            from 'vue3-apexcharts'
import DefaultLayout            from '@/layouts/default.vue'
import BlankLayout              from '@/layouts/blank.vue'
import RootApp                  from '@/App.vue'

import '@core/scss/template/index.scss'
import '@layouts/styles/index.scss'

setupAxiosDefaults()

const BLANK_PAGES = ['login', 'akses-ditolak', 'error/404']

createInertiaApp({
  resolve: async (name) => {
    const module = await resolvePageComponent(
      `./pages/${name}.vue`,
      import.meta.glob('./pages/**/*.vue'),
    )
    module.default.layout ??= BLANK_PAGES.includes(name) ? BlankLayout : DefaultLayout
    return module
  },

  // Inertia App dirender sebagai slot di dalam RootApp (VApp wrapper dari Vuetify)
  setup({ el, App, props, plugin }) {
    const vueApp = createApp({
      render: () => h(RootApp, null, {
        default: () => h(App, props),
      }),
    })

    vueApp.use(plugin)
    registerPlugins(vueApp)
    vueApp.component('VueApexCharts', VueApexCharts)

    vueApp.directive('click-outside', {
      beforeMount(el, binding) {
        el._clickOutsideHandler = (e) => { if (!el.contains(e.target)) binding.value(e) }
        document.addEventListener('mousedown', el._clickOutsideHandler)
      },
      unmounted(el) {
        document.removeEventListener('mousedown', el._clickOutsideHandler)
      },
    })

    vueApp.mount(el)
  },

  progress: { color: '#0EA5E9' },
})
