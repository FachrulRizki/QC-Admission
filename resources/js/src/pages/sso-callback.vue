<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import { useRouter }    from 'vue-router'
import axios from 'axios'

const router    = useRouter()
const authStore = useAuthStore()

const status  = ref('loading') 
const message = ref('')

onMounted(async () => {
  const params = new URLSearchParams(window.location.search)
  const code   = params.get('code')
  const error  = params.get('error')

  if (error) {
    status.value  = 'error'
    message.value = `SSO error: ${params.get('error_description') ?? error}`
    setTimeout(() => router.push('/login'), 3000)
    return
  }

  if (!code) {
    status.value  = 'error'
    message.value = 'Authorization code tidak ditemukan.'
    setTimeout(() => router.push('/login'), 2000)
    return
  }

  try {
    const { data } = await axios.post('/api/auth/sso/callback', {
      code,
      redirect_uri: window.location.origin + window.location.pathname,
    })

    const { user, token } = data
    authStore._setSession(user, token)

    status.value  = 'success'
    message.value = `Selamat datang, ${user.name}`

    setTimeout(() => {
      router.push(user.role === 'kasir' ? '/view-data-input' : '/dashboard')
    }, 800)
  } catch (err) {
    status.value  = 'error'
    message.value = err.response?.data?.message ?? 'Login SSO gagal. Silakan coba lagi.'
    setTimeout(() => router.push('/login'), 3000)
  }
})
</script>

<template>
  <div class="sso-callback">
    <VCard rounded="xl" elevation="8" class="sso-card">
      <!-- Loading -->
      <template v-if="status === 'loading'">
        <VProgressCircular indeterminate color="primary" size="52" width="4" class="mb-5" />
        <p class="text-subtitle-1 font-weight-semibold mb-1">Memproses Login SSO</p>
        <p class="text-caption text-medium-emphasis">Sedang memvalidasi sesi Anda...</p>
      </template>

      <!-- Success -->
      <template v-else-if="status === 'success'">
        <div class="sso-icon sso-icon--success mb-5">
          <VIcon icon="ri-checkbox-circle-fill" size="40" color="success" />
        </div>
        <p class="text-subtitle-1 font-weight-semibold mb-1">Login Berhasil</p>
        <p class="text-caption text-medium-emphasis">{{ message }}</p>
        <p class="text-caption text-disabled mt-2">Mengalihkan ke dashboard...</p>
      </template>

      <!-- Error -->
      <template v-else>
        <div class="sso-icon sso-icon--error mb-5">
          <VIcon icon="ri-close-circle-fill" size="40" color="error" />
        </div>
        <p class="text-subtitle-1 font-weight-semibold mb-1">Login SSO Gagal</p>
        <p class="text-caption text-medium-emphasis">{{ message }}</p>
        <p class="text-caption text-disabled mt-2">Kembali ke halaman login...</p>
      </template>
    </VCard>
  </div>
</template>

<style scoped>
.sso-callback {
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0369A1 0%, #0EA5E9 60%, #38BDF8 100%);
}
.sso-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 40px;
  min-width: 320px;
  text-align: center;
}
.sso-icon {
  width: 80px; height: 80px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}
.sso-icon--success { background: rgba(var(--v-theme-success), 0.1); }
.sso-icon--error   { background: rgba(var(--v-theme-error), 0.1); }
</style>
