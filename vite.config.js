import { resolve } from 'path'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { defineConfig, loadEnv } from 'vite'
import vuetify from 'vite-plugin-vuetify'
import svgLoader from 'vite-svg-loader'
import laravel from 'laravel-vite-plugin'

export default defineConfig(({ mode }) => {
  // Load .env variables agar bisa dipakai di config ini
  const env = loadEnv(mode, process.cwd(), '')

  // Dev server hanya aktif saat mode development
  // Set VITE_DEV_SERVER_HOST di .env lokal jika dev server diakses dari IP LAN
  const devHost = env.VITE_DEV_SERVER_HOST || 'localhost'
  const devPort = parseInt(env.VITE_DEV_SERVER_PORT || '5174', 10)
  const appUrl  = env.APP_URL || 'http://localhost'

  return {
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
    css: {
      preprocessorOptions: {
        scss: {
          // Suppress Sass deprecation warnings dari Vuetify template dan library pihak ketiga.
          // Warning ini tidak berasal dari kode kita — akan hilang saat library update ke Sass modern API.
          silenceDeprecations: [
            'legacy-js-api',
            'if-function',
            'color-functions',
            'global-builtin',
            'import',
          ],
        },
      },
    },
    // Dev server — hanya relevan saat `npm run dev`, tidak berpengaruh saat `npm run build`
    server: {
      host: '0.0.0.0',
      port: devPort,
      strictPort: true,
      // Paksa Vite menulis asset URL pakai host ini di manifest (penting saat dev dari IP LAN)
      origin: `http://${devHost}:${devPort}`,
      cors: {
        origin: [appUrl, `http://localhost:${devPort}`],
        credentials: true,
      },
      hmr: {
        host: devHost,
        port: devPort,
      },
      proxy: {
        '/api': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
          secure: false,
        },
        '/auth': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
          secure: false,
        },
      },
    },
    optimizeDeps: {
      exclude: ['vuetify'],
      entries: ['./resources/js/src/**/*.vue'],
    },
  }
})