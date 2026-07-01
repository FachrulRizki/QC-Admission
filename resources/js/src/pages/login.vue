<script setup>
import { useAuthStore } from '@/stores/useAuthStore'
import { useRouter }    from 'vue-router'
import axios from 'axios'

const router    = useRouter()
const authStore = useAuthStore()

const form              = ref({ username: '', password: '' })
const isPasswordVisible = ref(false)
const loading           = ref(false)
const ssoLoading        = ref(false)
const errorMsg          = ref('')

// Config SSO dari backend
const ssoEnabled       = ref(false)
const keycloakUrl      = ref('')
const keycloakRealm    = ref('master')
const keycloakClientId = ref('qc-admission')

// Features list untuk brand panel
const features = [
  { icon: 'ri-shield-check-line',    text: 'Monitoring Quality Control Admisi' },
  { icon: 'ri-book-open-line',       text: 'Edukasi Lanjutan & TTD Keluarga' },
  { icon: 'ri-close-circle-line',    text: 'Manajemen Batal Rawat Inap' },
  { icon: 'ri-arrow-up-circle-line', text: 'Up Selling Kelas Kamar' },
  { icon: 'ri-history-line',         text: 'Log Aktivitas Real-time' },
]

// Mode: 'auto' (detect jaringan) | 'local' | 'sso'
const loginMode = ref('auto')
const detecting = ref(true)

onMounted(async () => {
  try {
    const { data } = await axios.get('/api/config')
    ssoEnabled.value = data.sso_enabled === true || data.sso_enabled === 'true'
    if (ssoEnabled.value) {
      // Jika SSO aktif → default mode sso, bisa switch ke local
      loginMode.value = 'sso'
    } else {
      loginMode.value = 'local'
    }
  } catch {
    loginMode.value = 'local'
  } finally {
    detecting.value = false
  }
})

function redirectByRole(role) {
  router.push(role === 'kasir' ? '/view-data-input' : '/dashboard')
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
    errorMsg.value = result.message ?? 'Login gagal. Periksa username dan password.'
  }
}

function loginWithSSO() {
  ssoLoading.value = true
  const redirectUri = encodeURIComponent(window.location.origin + '/sso-callback')
  const url = keycloakUrl.value
    + '/realms/' + keycloakRealm.value
    + '/protocol/openid-connect/auth'
    + '?client_id=' + keycloakClientId.value
    + '&redirect_uri=' + redirectUri
    + '&response_type=code'
    + '&scope=openid profile email'
  window.location.href = url
}
</script>

<template>
  <div class="login-wrap">
    <!-- Background shapes -->
    <div class="login-bg-shape login-bg-shape--1" />
    <div class="login-bg-shape login-bg-shape--2" />
    <div class="login-bg-shape login-bg-shape--3" />

    <div class="login-container">
      <!-- Left panel — branding -->
      <div class="login-brand d-none d-md-flex">
        <div class="login-brand__inner">
          <div class="login-brand__logo mb-6">
            <VIcon icon="ri-shield-check-fill" size="52" color="white" />
          </div>
          <h1 class="login-brand__title">QC Admission</h1>
          <p class="login-brand__sub">Quality Control Admisi Rumah Sakit</p>

          <div class="login-brand__features mt-10">
            <div v-for="feat in features" :key="feat.text" class="login-brand__feat mb-4">
              <div class="login-brand__feat-icon">
                <VIcon :icon="feat.icon" size="18" />
              </div>
              <span>{{ feat.text }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right panel — form -->
      <div class="login-form-panel">
        <div class="login-form-inner">
          <!-- Mobile logo -->
          <div class="d-flex d-md-none align-center gap-3 mb-8">
            <div class="login-logo-sm">
              <VIcon icon="ri-shield-check-fill" size="22" color="white" />
            </div>
            <div>
              <p class="text-subtitle-1 font-weight-bold mb-0">QC Admission</p>
              <p class="text-caption text-medium-emphasis mb-0">Quality Control Admisi RSUS</p>
            </div>
          </div>

          <!-- Detecting state -->
          <template v-if="detecting">
            <div class="text-center py-10">
              <VProgressCircular indeterminate color="primary" size="44" width="3" class="mb-4" />
              <p class="text-body-2 text-medium-emphasis mb-0">Mendeteksi jaringan...</p>
            </div>
          </template>

          <template v-else>
            <!-- Header -->
            <div class="mb-8">
              <h2 class="login-form__title">
                {{ loginMode === 'sso' ? 'Login SSO' : 'Selamat Datang' }}
              </h2>
              <p class="login-form__sub">
                {{ loginMode === 'sso'
                  ? 'Gunakan akun jaringan rumah sakit (SSO/LDAP)'
                  : 'Masuk dengan akun lokal QC Admission' }}
              </p>
            </div>

            <!-- Network badge -->
            <div class="login-net-badge mb-6">
              <div class="login-net-badge__dot" :class="ssoEnabled ? 'dot--online' : 'dot--local'" />
              <span class="text-caption font-weight-semibold">
                {{ ssoEnabled ? 'Jaringan RS terdeteksi — SSO tersedia' : 'Mode Offline / Lokal' }}
              </span>
            </div>

            <!-- Error -->
            <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-5" rounded="lg" closable @click:close="errorMsg = ''">
              <VIcon icon="ri-error-warning-line" class="me-1" size="16" />{{ errorMsg }}
            </VAlert>

            <!-- SSO mode -->
            <template v-if="loginMode === 'sso'">
              <div class="login-sso-card mb-5">
                <VAvatar color="info" variant="tonal" size="52" rounded="xl" class="mb-4">
                  <VIcon icon="ri-key-2-line" size="26" />
                </VAvatar>
                <h3 class="text-subtitle-1 font-weight-bold mb-1">Login dengan SSO</h3>
                <p class="text-caption text-medium-emphasis mb-5">
                  Anda akan diarahkan ke halaman login SSO rumah sakit
                </p>
                <VBtn
                  block size="large" color="info" rounded="xl"
                  :loading="ssoLoading"
                  prepend-icon="ri-shield-keyhole-line"
                  class="login-btn"
                  @click="loginWithSSO"
                >
                  Masuk dengan SSO Rumah Sakit
                </VBtn>
              </div>

              <div class="d-flex align-center gap-3 mb-5">
                <VDivider />
                <span class="text-caption text-medium-emphasis text-no-wrap">atau gunakan akun lokal</span>
                <VDivider />
              </div>

              <VBtn block variant="outlined" size="large" rounded="xl" prepend-icon="ri-user-3-line" @click="loginMode = 'local'">
                Login Lokal
              </VBtn>
            </template>

            <!-- Local login mode -->
            <template v-else>
              <VForm @submit.prevent="handleLogin">
                <!-- Username -->
                <div class="login-field mb-4">
                  <label class="login-field__label">Username</label>
                  <VTextField
                    v-model="form.username"
                    placeholder="Masukkan username..."
                    prepend-inner-icon="ri-user-3-line"
                    variant="outlined"
                    rounded="lg"
                    autofocus
                    hide-details
                    :disabled="loading"
                    class="login-input"
                  />
                </div>

                <!-- Password -->
                <div class="login-field mb-6">
                  <label class="login-field__label">Password</label>
                  <VTextField
                    v-model="form.password"
                    placeholder="············"
                    prepend-inner-icon="ri-lock-2-line"
                    :type="isPasswordVisible ? 'text' : 'password'"
                    :append-inner-icon="isPasswordVisible ? 'ri-eye-off-line' : 'ri-eye-line'"
                    variant="outlined"
                    rounded="lg"
                    hide-details
                    :disabled="loading"
                    class="login-input"
                    @click:append-inner="isPasswordVisible = !isPasswordVisible"
                    @keyup.enter="handleLogin"
                  />
                </div>

                <!-- Login button -->
                <VBtn
                  block type="submit" size="large"
                  color="primary" rounded="xl"
                  :loading="loading"
                  prepend-icon="ri-login-circle-line"
                  class="login-btn mb-4"
                >
                  Masuk
                </VBtn>
              </VForm>

              <!-- Switch to SSO if available -->
              <template v-if="ssoEnabled">
                <div class="d-flex align-center gap-3 mb-4">
                  <VDivider />
                  <span class="text-caption text-medium-emphasis text-no-wrap">atau</span>
                  <VDivider />
                </div>
                <VBtn
                  block variant="outlined" size="large" rounded="xl"
                  prepend-icon="ri-shield-keyhole-line" color="info"
                  @click="loginMode = 'sso'"
                >
                  Login dengan SSO Rumah Sakit
                </VBtn>
              </template>
            </template>

            <!-- Footer -->
            <p class="text-caption text-center text-disabled mt-8 mb-0">
              &copy; {{ new Date().getFullYear() }} QC Admission — RSUS
            </p>
          </template>

        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
// ── Wrap ──────────────────────────────────────────────────────────────────────
.login-wrap {
  min-height: 100dvh;
  background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
  display: flex;
  align-items: stretch;
  position: relative;
  overflow: hidden;
}

// ── Background shapes ────────────────────────────────────────────────────────
.login-bg-shape {
  position: absolute;
  border-radius: 50%;
  opacity: 0.08;
  pointer-events: none;
  background: rgba(255,255,255,0.6);
  filter: blur(60px);
}
.login-bg-shape--1 { width: 500px; height: 500px; top: -200px; right: -100px; }
.login-bg-shape--2 { width: 300px; height: 300px; bottom: -100px; left: -50px; opacity: 0.05; }
.login-bg-shape--3 { width: 200px; height: 200px; top: 40%; left: 30%; opacity: 0.04; }

// ── Container ─────────────────────────────────────────────────────────────────
.login-container {
  display: flex;
  width: 100%;
  max-width: 1000px;
  margin: auto;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 32px 80px rgba(0,0,0,0.5);

  @media (max-width: 959px) {
    border-radius: 0;
    max-width: 100%;
    box-shadow: none;
  }
}

// ── Brand panel (left) ────────────────────────────────────────────────────────
.login-brand {
  flex: 1;
  background: linear-gradient(160deg, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0.04) 100%);
  backdrop-filter: blur(20px);
  border-right: 1px solid rgba(255,255,255,0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48px 40px;

  &__inner { max-width: 320px; }

  &__logo {
    width: 80px; height: 80px;
    background: linear-gradient(135deg, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
    border-radius: 22px;
    border: 1px solid rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    backdrop-filter: blur(8px);
  }

  &__title {
    font-size: 2rem; font-weight: 800;
    color: #fff; margin-bottom: 8px;
    letter-spacing: -0.02em;
  }

  &__sub {
    font-size: 1rem;
    color: rgba(255,255,255,0.65);
    margin-bottom: 0;
  }

  &__feat {
    display: flex; align-items: center; gap: 14px;
  }

  &__feat-icon {
    width: 36px; height: 36px; flex-shrink: 0;
    background: rgba(255,255,255,0.12);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.85);
    border: 1px solid rgba(255,255,255,0.1);
  }

  &__feat span {
    font-size: 0.875rem;
    color: rgba(255,255,255,0.75);
  }
}

// ── Form panel (right) ────────────────────────────────────────────────────────
.login-form-panel {
  flex: 0 0 420px;
  background: rgb(var(--v-theme-surface));
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;

  @media (max-width: 959px) {
    flex: 1;
    padding: 32px 20px;
  }
}

.login-form-inner {
  width: 100%;
  max-width: 380px;
}

// ── Typography ────────────────────────────────────────────────────────────────
.login-form__title {
  font-size: 1.625rem;
  font-weight: 800;
  color: rgb(var(--v-theme-on-surface));
  margin-bottom: 6px;
  letter-spacing: -0.02em;
}
.login-form__sub {
  font-size: 0.9rem;
  color: rgba(var(--v-theme-on-surface), 0.55);
  margin-bottom: 0;
}

// ── Network badge ─────────────────────────────────────────────────────────────
.login-net-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  border-radius: 20px;
  background: rgba(var(--v-theme-on-surface), 0.05);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));

  &__dot {
    width: 8px; height: 8px; border-radius: 50%;
    flex-shrink: 0;
  }
  .dot--online { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.25); animation: pulse 2s infinite; }
  .dot--local  { background: #94a3b8; }
}

@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 3px rgba(34,197,94,0.25); }
  50%       { box-shadow: 0 0 0 6px rgba(34,197,94,0.1); }
}

// ── SSO card ──────────────────────────────────────────────────────────────────
.login-sso-card {
  text-align: center;
  padding: 28px 20px;
  border-radius: 16px;
  background: rgba(var(--v-theme-info), 0.05);
  border: 1px solid rgba(var(--v-theme-info), 0.2);
}

// ── Field ─────────────────────────────────────────────────────────────────────
.login-field__label {
  display: block;
  font-size: 0.8125rem;
  font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.8);
  margin-bottom: 6px;
}

.login-input :deep(.v-field) {
  border-radius: 12px !important;
}

// ── Button ────────────────────────────────────────────────────────────────────
.login-btn {
  height: 52px !important;
  font-size: 0.9375rem !important;
  font-weight: 600 !important;
  letter-spacing: 0.01em;
}

// ── Logo sm (mobile) ──────────────────────────────────────────────────────────
.login-logo-sm {
  width: 42px; height: 42px;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgba(var(--v-theme-primary), 0.7));
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
</style>
