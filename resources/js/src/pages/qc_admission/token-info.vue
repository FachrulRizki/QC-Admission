<script setup>
import axios from 'axios'
import PageHero from '@/components/PageHero.vue'

const tokenData    = ref(null)
const loading      = ref(false)
const copied       = ref(false)
const showRaw      = ref(false)
const snackbar     = ref({ show: false, message: '', color: 'success' })

const tokenShort = computed(() => {
  const t = tokenData.value?.access_token
  if (!t) return ''
  return t.slice(0, 40) + '...' + t.slice(-20)
})

const expiresIn = computed(() => {
  const sec = tokenData.value?.expires_in
  if (!sec) return '—'
  const m = Math.floor(sec / 60)
  const s = sec % 60
  return `${m}m ${s}s`
})

const expiresAt = computed(() => {
  const d = tokenData.value?.expires_at
  if (!d) return '—'
  return new Date(d).toLocaleString('id-ID')
})

const isExpired = computed(() => {
  const sec = tokenData.value?.expires_in
  return sec !== null && sec !== undefined && sec <= 0
})

async function loadToken() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/auth/token')
    tokenData.value = data
  } catch (e) {
    snackbar.value = {
      show: true,
      message: e.response?.data?.message ?? 'Gagal mengambil token.',
      color: 'error',
    }
  } finally {
    loading.value = false
  }
}

async function copyToken() {
  const t = tokenData.value?.access_token
  if (!t) return
  copyToClipboard(t, 'Token berhasil dicopy!')
}

async function copyBearer() {
  const t = tokenData.value?.access_token
  if (!t) return
  copyToClipboard('Bearer ' + t, 'Bearer token berhasil dicopy!')
}

function copyToClipboard(text, successMsg) {
  // Coba Clipboard API dulu (butuh HTTPS)
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      snackbar.value = { show: true, message: successMsg, color: 'success' }
      copied.value = true
      setTimeout(() => (copied.value = false), 2500)
    }).catch(() => fallbackCopy(text, successMsg))
    return
  }
  // Fallback: textarea + execCommand (works on HTTP)
  fallbackCopy(text, successMsg)
}

function fallbackCopy(text, successMsg) {
  const ta = document.createElement('textarea')
  ta.value = text
  ta.style.position = 'fixed'
  ta.style.left = '-9999px'
  ta.style.top  = '-9999px'
  document.body.appendChild(ta)
  ta.focus()
  ta.select()
  try {
    const ok = document.execCommand('copy')
    snackbar.value = {
      show: true,
      message: ok ? successMsg : 'Gagal copy — salin manual dari kotak teks.',
      color: ok ? 'success' : 'warning',
    }
    if (ok) {
      copied.value = true
      setTimeout(() => (copied.value = false), 2500)
    }
  } catch {
    snackbar.value = { show: true, message: 'Gagal copy — salin manual.', color: 'warning' }
  } finally {
    document.body.removeChild(ta)
  }
}

onMounted(() => loadToken())
</script>

<template>
  <div>
    <PageHero
      icon="ri-key-2-line"
      badge="SSO · Keycloak Token"
      title="Access Token"
      subtitle="Bearer token Keycloak untuk integrasi API — gunakan untuk testing tim Bed IGD"
      color-from="#7C3AED"
      color-to="#4F46E5"
      :pills="[
        { icon: 'ri-shield-keyhole-line', text: 'SSO Keycloak' },
        { icon: 'ri-time-line',           text: expiresIn },
      ]"
    >
      <template #actions>
        <VBtn
          color="white"
          variant="elevated"
          rounded="lg"
          prepend-icon="ri-refresh-line"
          style="color:#4F46E5"
          :loading="loading"
          @click="loadToken"
        >
          Refresh Token
        </VBtn>
      </template>
    </PageHero>

    <!-- Alert expired -->
    <VAlert
      v-if="isExpired"
      type="warning"
      variant="tonal"
      border="start"
      class="mb-4"
      icon="ri-error-warning-line"
    >
      Token sudah expired. Klik <strong>Refresh Token</strong> untuk mendapatkan token baru.
    </VAlert>

    <!-- Info penggunaan -->
    <VAlert
      type="info"
      variant="tonal"
      border="start"
      density="compact"
      class="mb-4"
      closable
    >
      <div class="text-caption">
        Kirimkan token ini ke tim Bed IGD sebagai <strong>Authorization: Bearer &lt;token&gt;</strong> di HTTP header.
        Token valid selama sesi Keycloak aktif dan akan otomatis di-refresh saat mendekati expiry.
      </div>
    </VAlert>

    <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-4" rounded />

    <template v-if="tokenData">
      <!-- User info -->
      <VCard elevation="0" border rounded="xl" class="mb-4">
        <VCardTitle class="pa-4 pb-2">
          <div class="d-flex align-center gap-2">
            <VIcon icon="ri-user-3-line" color="primary" size="20" />
            <span class="text-body-1 font-weight-semibold">Informasi User</span>
          </div>
        </VCardTitle>
        <VCardText class="pa-4 pt-0">
          <VRow dense>
            <VCol cols="12" sm="4">
              <p class="text-caption text-medium-emphasis mb-1">Nama</p>
              <p class="text-body-2 font-weight-medium mb-0">{{ tokenData.user?.name ?? '—' }}</p>
            </VCol>
            <VCol cols="12" sm="4">
              <p class="text-caption text-medium-emphasis mb-1">Username</p>
              <p class="text-body-2 font-weight-medium mb-0">{{ tokenData.user?.username ?? '—' }}</p>
            </VCol>
            <VCol cols="12" sm="4">
              <p class="text-caption text-medium-emphasis mb-1">Role</p>
              <div class="d-flex flex-wrap gap-1">
                <VChip
                  v-for="r in (tokenData.user?.roles ?? [])"
                  :key="r"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                >{{ r }}</VChip>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Token expires -->
      <VCard elevation="0" border rounded="xl" class="mb-4">
        <VCardTitle class="pa-4 pb-2">
          <div class="d-flex align-center gap-2">
            <VIcon icon="ri-time-line" :color="isExpired ? 'error' : 'success'" size="20" />
            <span class="text-body-1 font-weight-semibold">Status Token</span>
          </div>
        </VCardTitle>
        <VCardText class="pa-4 pt-0">
          <VRow dense>
            <VCol cols="12" sm="4">
              <p class="text-caption text-medium-emphasis mb-1">Berlaku Hingga</p>
              <p class="text-body-2 font-weight-medium mb-0">{{ expiresAt }}</p>
            </VCol>
            <VCol cols="12" sm="4">
              <p class="text-caption text-medium-emphasis mb-1">Sisa Waktu</p>
              <p class="text-body-2 font-weight-medium mb-0" :class="isExpired ? 'text-error' : 'text-success'">
                {{ expiresIn }}
              </p>
            </VCol>
            <VCol cols="12" sm="4">
              <p class="text-caption text-medium-emphasis mb-1">Token Type</p>
              <VChip size="x-small" color="info" variant="tonal">Bearer</VChip>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Token value -->
      <VCard elevation="0" border rounded="xl" class="mb-4">
        <VCardTitle class="pa-4 pb-2">
          <div class="d-flex align-center justify-space-between flex-wrap gap-2">
            <div class="d-flex align-center gap-2">
              <VIcon icon="ri-key-2-line" color="warning" size="20" />
              <span class="text-body-1 font-weight-semibold">Access Token</span>
            </div>
            <div class="d-flex gap-2">
              <VBtn
                size="small"
                variant="tonal"
                color="secondary"
                rounded="lg"
                :prepend-icon="showRaw ? 'ri-eye-off-line' : 'ri-eye-line'"
                @click="showRaw = !showRaw"
              >
                {{ showRaw ? 'Sembunyikan' : 'Tampilkan' }}
              </VBtn>
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                rounded="lg"
                prepend-icon="ri-file-copy-line"
                @click="copyToken"
              >
                Copy Token
              </VBtn>
              <VBtn
                size="small"
                variant="elevated"
                color="primary"
                rounded="lg"
                prepend-icon="ri-shield-keyhole-line"
                @click="copyBearer"
              >
                Copy Bearer
              </VBtn>
            </div>
          </div>
        </VCardTitle>
        <VCardText class="pa-4 pt-0">
          <!-- Preview singkat selalu tampil -->
          <div
            class="pa-3 rounded-lg mb-3"
            style="background:rgba(var(--v-theme-surface-variant),0.5); font-family:monospace; font-size:0.75rem; word-break:break-all; color:rgb(var(--v-theme-on-surface))"
          >
            <span class="text-medium-emphasis">Authorization: Bearer </span>
            <span v-if="!showRaw" class="text-warning">{{ tokenShort }}</span>
            <span v-else class="text-warning" style="white-space:pre-wrap">{{ tokenData.access_token }}</span>
          </div>

          <!-- Cara penggunaan -->
          <VExpansionPanels variant="accordion" elevation="0">
            <VExpansionPanel title="Cara Penggunaan (HTTP Header)" rounded="lg">
              <VExpansionPanelText>
                <p class="text-caption text-medium-emphasis mb-2">Tambahkan header berikut ke setiap request API:</p>
                <div
                  class="pa-3 rounded-lg"
                  style="background:rgba(var(--v-theme-surface-variant),0.5); font-family:monospace; font-size:0.75rem"
                >
                  <div>GET https://[bed-igd-api]/endpoint</div>
                  <div class="text-primary">Authorization: Bearer {{ tokenShort }}</div>
                  <div>Content-Type: application/json</div>
                </div>
                <p class="text-caption text-medium-emphasis mt-2 mb-0">
                  ⚠️ Token ini expire. Hubungi developer QC Admission untuk implementasi auto-refresh jika dibutuhkan.
                </p>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>
        </VCardText>
      </VCard>
    </template>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="lg">
      {{ snackbar.message }}
      <template #actions>
        <VBtn variant="text" @click="snackbar.show = false">Tutup</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>
