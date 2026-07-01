<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router    = useRouter()
const authStore = useAuthStore()

const form               = ref({ username: '', password: '' })
const isPasswordVisible  = ref(false)
const loading            = ref(false)
const ssoLoading         = ref(false)
const errorMsg           = ref('')
const ssoEnabled         = ref(false)
const keycloakUrl        = ref('')
const keycloakRealm      = ref('master')
const keycloakClientId   = ref('qc-admission')

// Ambil config SSO dari backend (bukan VITE_ env)
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/config')
    ssoEnabled.value = data.sso_enabled === true || data.sso_enabled === 'true'
  } catch {}
})

/** Redirect ke halaman default sesuai role */
function redirectByRole(role) {
  if (role === 'kasir') {
    router.push('/view-data-input')
  } else {
    router.push('/dashboard')
  }
}

async function handleLogin() {
  errorMsg.value = ''
  if (!form.value.username || !form.value.password) {
    errorMsg.value = 'Username dan password wajib diisi.'
    return
  }
  loading.value = true
  const result  = await authStore.login(form.value)
  loading.value = false

  if (result.success) {
    redirectByRole(result.role)
  } else {
    errorMsg.value = result.message ?? 'Login gagal. Periksa kembali username dan password.'
  }
}

function loginWithSSO() {
  const redirectUri = encodeURIComponent(window.location.origin + '/sso-callback')
  const url = `${keycloakUrl.value}/realms/${keycloakRealm.value}/protocol/openid-connect/auth`
    + `?client_id=${keycloakClientId.value}`
    + `&redirect_uri=${redirectUri}`
    + `&response_type=code`
    + `&scope=openid profile email`
  window.location.href = url
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <div class="auth-content">
      <!-- Logo -->
      <div class="text-center mb-6">
        <div class="auth-logo-bg mx-auto mb-3">
          <VIcon icon="ri-shield-check-fill" size="36" color="white" />
        </div>
        <h1 class="text-h5 font-weight-bold text-uppercase tracking-wide mb-1">QC Admission</h1>
        <p class="text-body-2 text-medium-emphasis">Quality Control Admisi RSUS</p>
      </div>

      <VCard rounded="xl" elevation="4" class="auth-card">
        <VCardText class="pa-6">
          <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-5" closable @click:close="errorMsg = ''">
            {{ errorMsg }}
          </VAlert>

          <VForm @submit.prevent="handleLogin">
            <VRow dense>
              <VCol cols="12">
                <VTextField
                  v-model="form.username"
                  label="Username"
                  placeholder="Masukkan username..."
                  prepend-inner-icon="ri-user-3-line"
                  variant="outlined"
                  autofocus
                  :disabled="loading"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="form.password"
                  label="Password"
                  placeholder="············"
                  prepend-inner-icon="ri-lock-2-line"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                  variant="outlined"
                  :disabled="loading"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>
              <VCol cols="12">
                <VBtn block type="submit" size="large" color="primary" rounded="lg" :loading="loading" prepend-icon="ri-login-circle-line">
                  Masuk
                </VBtn>
              </VCol>
            </VRow>
          </VForm>

          <!-- SSO — hanya tampil jika SSO_ENABLED=true di backend -->
          <template v-if="ssoEnabled">
            <div class="d-flex align-center gap-3 my-4">
              <VDivider class="flex-grow-1" />
              <span class="text-caption text-medium-emphasis">atau</span>
              <VDivider class="flex-grow-1" />
            </div>
            <VBtn block variant="outlined" size="large" rounded="lg" :loading="ssoLoading" prepend-icon="ri-key-2-line" color="info" @click="loginWithSSO">
              Login dengan SSO Rumah Sakit
            </VBtn>
          </template>
        </VCardText>
      </VCard>

      <!-- Akun hint (dev) -->
      <VExpansionPanels variant="accordion" class="mt-4">
        <VExpansionPanel title="Info Akun">
          <VExpansionPanelText>
            <VList density="compact" class="pa-0">
              <VListItem prepend-icon="ri-shield-user-line">
                <VListItemTitle class="text-caption font-weight-bold">Admin</VListItemTitle>
                <VListItemSubtitle class="text-caption"><code>admin / Admin@1234</code> — kelola aplikasi &amp; user</VListItemSubtitle>
              </VListItem>
              <VListItem prepend-icon="ri-nurse-line">
                <VListItemTitle class="text-caption font-weight-bold">QC Admission</VListItemTitle>
                <VListItemSubtitle class="text-caption"><code>qc_admission / QcAdm@1234</code> — entry semua data</VListItemSubtitle>
              </VListItem>
              <VListItem prepend-icon="ri-money-dollar-circle-line">
                <VListItemTitle class="text-caption font-weight-bold">Kasir</VListItemTitle>
                <VListItemSubtitle class="text-caption"><code>kasir / Kasir@1234</code> — view data input</VListItemSubtitle>
              </VListItem>
            </VList>
          </VExpansionPanelText>
        </VExpansionPanel>
      </VExpansionPanels>

      <p class="text-caption text-center text-disabled mt-4 mb-0">
        &copy; {{ new Date().getFullYear() }} QC Admission — RSUS
      </p>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.auth-wrapper {
  min-block-size: 100dvh;
  background: linear-gradient(135deg,rgba(var(--v-theme-primary), 0.08) 0%,rgb(var(--v-theme-background)) 50%,rgba(var(--v-theme-primary), 0.03) 100%);
}
.auth-content { width: 100%; max-width: 420px; }
.auth-logo-bg {
  width: 72px; height: 72px; border-radius: 20px;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgba(var(--v-theme-primary), 0.7));
  display: flex; align-items: center; justify-content: center;
}
.auth-card { backdrop-filter: blur(8px); }
.tracking-wide { letter-spacing: 0.08em; }
</style>
