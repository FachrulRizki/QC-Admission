import { resolve } from 'path'
import { fileURLToPath, URL } from 'node:url'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { defineConfig } from 'vite'
import vuetify from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/src/main.js'],
      refresh: true,
    }),
    vue(),
    vueJsx(),
    vuetify({
      styles: {
        configFile: 'resources/js/src/assets/styles/variables/_vuetify.scss',
      },
    }),
    Components({
      dirs: ['resources/js/src/@core/components', 'resources/js/src/components'],
      dts: false,
    }),
    AutoImport({
      imports: ['vue', 'vue-router', 'pinia'],
      vueTemplate: true,
      ignore: ['useCookies', 'useStorage'],
    }),
    svgLoader(),
  ],
  define: { 'process.env': {} },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/js/src'),
      '@core': resolve(__dirname, 'resources/js/src/@core'),
      '@layouts': resolve(__dirname, 'resources/js/src/@layouts'),
      '@images': resolve(__dirname, 'resources/js/src/assets/images/'),
      '@styles': resolve(__dirname, 'resources/js/src/assets/styles/'),
      '@configured-variables': resolve(__dirname, 'resources/js/src/assets/styles/variables/_template.scss'),
    },
  },
  optimizeDeps: {
    exclude: ['vuetify'],
    entries: ['./resources/js/src/**/*.vue'],
  },
})