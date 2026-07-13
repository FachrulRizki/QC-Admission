<script setup>
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

const page       = usePage()
const ssoEnabled = computed(() => page.props.ssoEnabled ?? true)
const ssoLoading = ref(false)

// Flash error dari redirect (mis. setelah SSO callback gagal)
const errorMsg = ref(page.props.flash?.error ?? '')

// Form login lokal
const localForm    = reactive({ username: '', password: '' })
const localLoading = ref(false)
const showPassword = ref(false)

const features = [
  { icon: 'ri-shield-check-line',    text: 'Monitoring Quality Control Admisi' },
  { icon: 'ri-book-open-line',       text: 'Edukasi Lanjutan & TTD Keluarga' },
  { icon: 'ri-close-circle-line',    text: 'Manajemen Batal Rawat Inap' },
  { icon: 'ri-arrow-up-circle-line', text: 'Up Selling Kelas Kamar' },
  { icon: 'ri-history-line',         text: 'Log Aktivitas Real-time' },
]

function loginWithSSO() {
  ssoLoading.value = true
  window.location.href = '/auth/keycloak/redirect'
}

async function loginLocal() {
  if (!localForm.username || !localForm.password) {
    errorMsg.value = 'Username dan password harus diisi.'
    return
  }

  localLoading.value = true
  errorMsg.value     = ''

  try {
    // Ambil CSRF cookie dulu
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

    await axios.post('/auth/local/login', {
      username: localForm.username,
      password: localForm.password,
    }, {
      headers: { 'X-CSRF-TOKEN': csrfToken },
      withCredentials: true,
    })

    // Redirect sesuai role — server sudah set session, reload untuk Inertia pick-up
    window.location.href = '/dashboard'
  } catch (err) {
    errorMsg.value = err.response?.data?.message ?? 'Login gagal. Periksa username dan password.'
  } finally {
    localLoading.value = false
  }
}
</script>

<template>
  <div class="login-wrap">
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

          <!-- Header -->
          <div class="mb-8">
            <h2 class="login-form__title">
              {{ ssoEnabled ? 'Login SSO' : 'Login Lokal' }}
            </h2>
            <p class="login-form__sub">
              {{ ssoEnabled
                ? 'Gunakan akun jaringan rumah sakit (Keycloak SSO)'
                : 'Mode testing — login dengan akun lokal' }}
            </p>
          </div>

          <!-- Network badge -->
          <div class="login-net-badge mb-6">
            <div :class="['login-net-badge__dot', ssoEnabled ? 'dot--online' : 'dot--local']" />
            <span class="text-caption font-weight-semibold">
              {{ ssoEnabled ? 'Login SSO Rumah Sakit' : 'Mode Lokal (Testing)' }}
            </span>
          </div>

          <!-- Error -->
          <VAlert
            v-if="errorMsg"
            type="error" variant="tonal" density="compact"
            class="mb-5" rounded="lg" closable
            @click:close="errorMsg = ''"
          >
            <VIcon icon="ri-error-warning-line" class="me-1" size="16" />{{ errorMsg }}
          </VAlert>

          <!-- ── SSO Login ── -->
          <template v-if="ssoEnabled">
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
          </template>

          <!-- ── Local Login ── -->
          <template v-else>
            <div class="login-local-card mb-5">
              <!-- Info banner testing -->
              <VAlert
                type="warning" variant="tonal" density="compact"
                rounded="lg" class="mb-5" icon="ri-flask-line"
              >
                <span class="text-caption">Mode testing aktif — SSO dinonaktifkan</span>
              </VAlert>

              <form @submit.prevent="loginLocal">
                <div class="mb-4">
                  <label class="login-field__label">Username / Email</label>
                  <VTextField
                    v-model="localForm.username"
                    placeholder="Masukkan username atau email"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    hide-details
                    autocomplete="username"
                    prepend-inner-icon="ri-user-line"
                    class="login-input"
                  />
                </div>

                <div class="mb-6">
                  <label class="login-field__label">Password</label>
                  <VTextField
                    v-model="localForm.password"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="Masukkan password"
                    variant="outlined"
                    density="comfortable"
                    rounded="lg"
                    hide-details
                    autocomplete="current-password"
                    prepend-inner-icon="ri-lock-line"
                    :append-inner-icon="showPassword ? 'ri-eye-off-line' : 'ri-eye-line'"
                    class="login-input"
                    @click:append-inner="showPassword = !showPassword"
                  />
                </div>

                <VBtn
                  block size="large" color="primary" rounded="xl"
                  :loading="localLoading"
                  type="submit"
                  prepend-icon="ri-login-circle-line"
                  class="login-btn"
                >
                  Masuk
                </VBtn>
              </form>
            </div>
          </template>

          <p class="text-caption text-center text-disabled mt-8 mb-0">
            &copy; {{ new Date().getFullYear() }} QC Admission — RSUS
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.login-wrap {
  min-height: 100dvh;
  background: linear-gradient(135deg, #0369A1 0%, #0EA5E9 60%, #38BDF8 100%);
  display: flex;
  align-items: stretch;
  position: relative;
  overflow: hidden;
}
.login-bg-shape {
  position: absolute;
  border-radius: 50%;
  opacity: 0.12;
  pointer-events: none;
  background: rgba(255,255,255,0.8);
  filter: blur(70px);
}
.login-bg-shape--1 { width: 500px; height: 500px; top: -200px; right: -100px; }
.login-bg-shape--2 { width: 300px; height: 300px; bottom: -100px; left: -50px; opacity: 0.07; }
.login-bg-shape--3 { width: 200px; height: 200px; top: 40%; left: 30%; opacity: 0.06; }

.login-container {
  display: flex;
  width: 100%;
  max-width: 1000px;
  margin: auto;
  border-radius: 24px;
  overflow: hidden;
  box-shadow: 0 32px 80px rgba(0,0,0,0.5);
  @media (max-width: 959px) { border-radius: 0; max-width: 100%; box-shadow: none; }
}

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
  &__title { font-size: 2rem; font-weight: 800; color: #fff; margin-bottom: 8px; letter-spacing: -0.02em; }
  &__sub   { font-size: 1rem; color: rgba(255,255,255,0.65); margin-bottom: 0; }
  &__feat  { display: flex; align-items: center; gap: 14px; }
  &__feat-icon {
    width: 36px; height: 36px; flex-shrink: 0;
    background: rgba(255,255,255,0.12);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.85);
    border: 1px solid rgba(255,255,255,0.1);
  }
  &__feat span { font-size: 0.875rem; color: rgba(255,255,255,0.75); }
}

.login-form-panel {
  flex: 0 0 420px;
  background: rgb(var(--v-theme-surface));
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  @media (max-width: 959px) { flex: 1; padding: 32px 20px; }
}
.login-form-inner { width: 100%; max-width: 380px; }

.login-form__title {
  font-size: 1.625rem; font-weight: 800;
  color: rgb(var(--v-theme-on-surface));
  margin-bottom: 6px; letter-spacing: -0.02em;
}
.login-form__sub { font-size: 0.9rem; color: rgba(var(--v-theme-on-surface), 0.55); margin-bottom: 0; }

.login-net-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  border-radius: 20px;
  background: rgba(var(--v-theme-on-surface), 0.05);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));

  &__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .dot--online { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.25); animation: pulse 2s infinite; }
  .dot--local  { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.25); }
}
@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 3px rgba(34,197,94,0.25); }
  50%       { box-shadow: 0 0 0 6px rgba(34,197,94,0.1); }
}

.login-sso-card {
  text-align: center;
  padding: 28px 20px;
  border-radius: 16px;
  background: rgba(var(--v-theme-info), 0.05);
  border: 1px solid rgba(var(--v-theme-info), 0.2);
}

.login-local-card {
  padding: 24px 20px;
  border-radius: 16px;
  background: rgba(var(--v-theme-warning), 0.04);
  border: 1px solid rgba(var(--v-theme-warning), 0.2);
}

.login-field__label {
  display: block;
  font-size: 0.8125rem; font-weight: 600;
  color: rgba(var(--v-theme-on-surface), 0.8);
  margin-bottom: 6px;
}
.login-input :deep(.v-field) { border-radius: 12px !important; }

.login-btn {
  height: 52px !important;
  font-size: 0.9375rem !important;
  font-weight: 600 !important;
  letter-spacing: 0.01em;
}

.login-logo-sm {
  width: 42px; height: 42px;
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)), rgba(var(--v-theme-primary), 0.7));
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
</style>
