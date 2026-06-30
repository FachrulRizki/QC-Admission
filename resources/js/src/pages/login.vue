<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import { useRouter } from 'vue-router'

const router    = useRouter()
const authStore = useAuthStore()

const form = ref({ username: '', password: '' })
const isPasswordVisible = ref(false)
const loading    = ref(false)
const ssoLoading = ref(false)
const errorMsg   = ref('')

// Keycloak SSO config (from .env via VITE_ prefix)
const keycloakUrl      = import.meta.env.VITE_KEYCLOAK_URL ?? ''
const keycloakRealm    = import.meta.env.VITE_KEYCLOAK_REALM ?? 'master'
const keycloakClientId = import.meta.env.VITE_KEYCLOAK_CLIENT_ID ?? 'qc-admission'
const ssoEnabled       = !!keycloakUrl

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
    router.push('/dashboard')
  } else {
    errorMsg.value = result.message ?? 'Login gagal. Periksa kembali username dan password.'
  }
}

function loginWithSSO() {
  if (!keycloakUrl) return

  // Redirect to Keycloak authorization endpoint
  const redirectUri  = encodeURIComponent(window.location.origin + '/sso-callback')
  const loginUrl     = `${keycloakUrl}/realms/${keycloakRealm}/protocol/openid-connect/auth`
    + `?client_id=${keycloakClientId}`
    + `&redirect_uri=${redirectUri}`
    + `&response_type=code`
    + `&scope=openid profile email`

  window.location.href = loginUrl
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <div class="auth-content">
      <!-- Logo area -->
      <div class="text-center mb-6">
        <div class="auth-logo-bg mx-auto mb-3">
          <VIcon icon="ri-shield-check-fill" size="36" color="white" />
        </div>
        <h1 class="text-h5 font-weight-bold text-uppercase tracking-wide mb-1">
          QC Admission
        </h1>
        <p class="text-body-2 text-medium-emphasis">Quality Control Admisi RSUS</p>
      </div>

      <VCard rounded="xl" elevation="4" class="auth-card">
        <VCardText class="pa-6">
          <!-- Error alert -->
          <VAlert
            v-if="errorMsg"
            type="error"
            variant="tonal"
            density="compact"
            class="mb-5"
            closable
            @click:close="errorMsg = ''"
          >
            {{ errorMsg }}
          </VAlert>

          <VForm @submit.prevent="handleLogin">
            <VRow dense>
              <!-- Username -->
              <VCol cols="12">
                <VTextField
                  v-model="form.username"
                  label="Username atau Email"
                  placeholder="Masukkan username..."
                  prepend-inner-icon="ri-user-3-line"
                  variant="outlined"
                  autofocus
                  :disabled="loading"
                />
              </VCol>

              <!-- Password -->
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

              <!-- Login button -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                  size="large"
                  color="primary"
                  rounded="lg"
                  :loading="loading"
                  prepend-icon="ri-login-circle-line"
                >
                  Masuk
                </VBtn>
              </VCol>
            </VRow>
          </VForm>

          <!-- SSO divider (show only if configured) -->
          <template v-if="ssoEnabled">
            <div class="d-flex align-center gap-3 my-4">
              <VDivider class="flex-grow-1" />
              <span class="text-caption text-medium-emphasis">atau</span>
              <VDivider class="flex-grow-1" />
            </div>

            <VBtn
              block
              variant="outlined"
              size="large"
              rounded="lg"
              :loading="ssoLoading"
              prepend-icon="ri-key-2-line"
              color="info"
              @click="loginWithSSO"
            >
              Login dengan SSO / Keycloak
            </VBtn>
          </template>
        </VCardText>
      </VCard>

      <!-- Quick accounts hint (dev only) -->
      <VExpansionPanels v-if="false" variant="accordion" class="mt-4">
        <VExpansionPanel title="Akun Demo">
          <VExpansionPanelText>
            <p class="text-caption mb-1">Admin: <code>admin / Admin@1234</code></p>
            <p class="text-caption mb-1">Petugas: <code>nurul / Petugas@1234</code></p>
            <p class="text-caption mb-0">Supervisor: <code>supervisor / Super@1234</code></p>
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
  background: linear-gradient(
    135deg,
    rgba(var(--v-theme-primary), 0.08) 0%,
    rgb(var(--v-theme-background)) 50%,
    rgba(var(--v-theme-primary), 0.03) 100%
  );
}

.auth-content {
  width: 100%;
  max-width: 420px;
}

.auth-logo-bg {
  width: 72px;
  height: 72px;
  border-radius: 20px;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgba(var(--v-theme-primary), 0.7));
  display: flex;
  align-items: center;
  justify-content: center;
}

.auth-card {
  backdrop-filter: blur(8px);
}

.tracking-wide { letter-spacing: 0.08em; }
</style>
