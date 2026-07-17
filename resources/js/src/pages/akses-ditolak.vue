<script setup>
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const rejected = computed(() => page.props.ssoRejectedUser ?? null)
const clientId = computed(() => rejected.value?.client_id ?? 'qc-admission')
const userName = computed(() => rejected.value?.name ?? rejected.value?.username ?? 'Akun Anda')
const userEmail = computed(() => rejected.value?.email ?? '')
const userHandle = computed(() => rejected.value?.username ?? '')

function tryAgain() {
  window.location.href = '/auth/keycloak/redirect'
}

function backToLogin() {
  window.location.href = '/login'
}
</script>

<template>
  <div class="no-access-wrap">
    <!-- Background shapes -->
    <div class="shape shape--1" />
    <div class="shape shape--2" />

    <div class="no-access-card">
      <!-- Icon -->
      <div class="no-access-icon mb-6">
        <VIcon icon="ri-shield-user-line" size="44" color="warning" />
      </div>

      <!-- Header -->
      <h2 class="text-h5 font-weight-bold mb-1">Akun Belum Terdaftar</h2>
      <p class="text-body-2 text-medium-emphasis mb-6">
        Autentikasi SSO berhasil, namun akun ini belum memiliki akses ke aplikasi
        <strong>QC Admission</strong>.
      </p>

      <!-- User info card -->
      <div v-if="rejected" class="user-card mb-6">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" size="44" rounded="xl">
            <VIcon icon="ri-user-3-line" size="22" />
          </VAvatar>
          <div class="text-start">
            <p class="text-subtitle-2 font-weight-bold mb-0">{{ userName }}</p>
            <p class="text-caption text-medium-emphasis mb-0">
              {{ userEmail || userHandle }}
            </p>
          </div>
          <VSpacer />
          <VChip size="small" color="warning" variant="tonal" prepend-icon="ri-error-warning-line">
            Belum punya role
          </VChip>
        </div>
      </div>

      <!-- Info steps -->
      <div class="info-box mb-6">
        <p class="text-caption font-weight-semibold text-medium-emphasis mb-3 text-uppercase">
          Cara mendapatkan akses
        </p>
        <div class="d-flex align-start gap-3 mb-3">
          <div class="step-num">1</div>
          <p class="text-body-2 mb-0">
            Hubungi administrator sistem atau IT Support
          </p>
        </div>
        <div class="d-flex align-start gap-3 mb-3">
          <div class="step-num">2</div>
          <p class="text-body-2 mb-0">
            Minta assign role <code>admin</code>, <code>qc_admission</code>, atau <code>kasir</code>
            di Keycloak untuk client <code>{{ clientId }}</code>
          </p>
        </div>
        <div class="d-flex align-start gap-3">
          <div class="step-num">3</div>
          <p class="text-body-2 mb-0">
            Setelah role di-assign, klik <strong>"Coba Login Lagi"</strong> di bawah
          </p>
        </div>
      </div>

      <!-- Actions -->
      <div class="d-flex flex-column gap-3">
        <VBtn block size="large" color="primary" rounded="xl" prepend-icon="ri-refresh-line" @click="tryAgain">
          Coba Login dengan Akun Lain
        </VBtn>
        <VBtn block size="large" variant="tonal" color="default" rounded="xl" prepend-icon="ri-arrow-left-line"
          @click="backToLogin">
          Kembali ke Halaman Login
        </VBtn>
      </div>

      <p class="text-caption text-disabled text-center mt-6 mb-0">
        &copy; {{ new Date().getFullYear() }} QC Admission — RSUS
      </p>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.no-access-wrap {
  min-height: 100dvh;
  background: linear-gradient(135deg, #0369A1 0%, #0EA5E9 60%, #38BDF8 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  position: relative;
  overflow: hidden;
}

.shape {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
  background: rgba(255, 255, 255, 0.15);
  filter: blur(60px);
}

.shape--1 {
  width: 400px;
  height: 400px;
  top: -120px;
  right: -80px;
}

.shape--2 {
  width: 300px;
  height: 300px;
  bottom: -80px;
  left: -60px;
}

.no-access-card {
  background: rgb(var(--v-theme-surface));
  border-radius: 24px;
  padding: 40px 36px;
  width: 100%;
  max-width: 460px;
  box-shadow: 0 24px 64px rgba(0, 0, 0, 0.4);
  text-align: center;
  position: relative;
  z-index: 1;

  @media (max-width: 480px) {
    padding: 28px 20px;
    border-radius: 16px;
  }
}

.no-access-icon {
  width: 80px;
  height: 80px;
  background: rgba(var(--v-theme-warning), 0.1);
  border: 2px solid rgba(var(--v-theme-warning), 0.3);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
}

.user-card {
  background: rgba(var(--v-theme-on-surface), 0.04);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 14px;
  padding: 16px;
}

.info-box {
  background: rgba(var(--v-theme-info), 0.05);
  border: 1px solid rgba(var(--v-theme-info), 0.2);
  border-radius: 14px;
  padding: 16px;
  text-align: left;
}

.step-num {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: rgba(var(--v-theme-primary), 0.15);
  color: rgb(var(--v-theme-primary));
  font-size: 0.75rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 1px;
}

code {
  background: rgba(var(--v-theme-on-surface), 0.08);
  padding: 1px 5px;
  border-radius: 4px;
  font-size: 0.8em;
  font-family: monospace;
}
</style>
