<script setup>
import axios from 'axios'
import PageHero from '@/components/PageHero.vue'

const loading  = ref(false)
const users    = ref([])
const search   = ref('')
const snackbar = ref({ show: false, message: '', color: 'success' })
const cacheRefreshing = ref(false)

// URL Keycloak Admin Console — diambil dari meta tag yang di-inject Laravel (opsional)
// Fallback ke env jika tersedia di window
const keycloakAdminUrl = computed(() => {
  const base  = window.__KEYCLOAK_BASE_URL__ ?? ''
  const realm = window.__KEYCLOAK_REALM__    ?? ''
  if (base && realm) return `${base}/admin/${realm}/console/#/users`
  return null
})

const headers = [
  { title: 'Nama',     key: 'name',       sortable: true },
  { title: 'Username', key: 'username',   sortable: true },
  { title: 'Email',    key: 'email',      sortable: true },
  { title: 'Role',     key: 'role',       sortable: true, align: 'center' },
  { title: 'Status',   key: 'enabled',    sortable: true, align: 'center' },
  { title: 'Dibuat',   key: 'created_at', sortable: true },
]

const filteredUsers = computed(() => {
  if (!search.value.trim()) return users.value
  const q = search.value.toLowerCase()
  return users.value.filter(u =>
    u.name?.toLowerCase().includes(q)     ||
    u.username?.toLowerCase().includes(q) ||
    u.email?.toLowerCase().includes(q)    ||
    u.role?.toLowerCase().includes(q)
  )
})

const stats = computed(() => ({
  total:        users.value.length,
  admin:        users.value.filter(u => u.role === 'admin').length,
  qc_admission: users.value.filter(u => u.role === 'qc_admission').length,
  kasir:        users.value.filter(u => u.role === 'kasir').length,
  enabled:      users.value.filter(u => u.enabled !== false).length,
}))

function roleColor(r) {
  return { admin: 'error', qc_admission: 'primary', kasir: 'warning' }[r] ?? 'secondary'
}
function roleIcon(r) {
  return { admin: 'ri-shield-star-line', qc_admission: 'ri-nurse-line', kasir: 'ri-money-cny-box-line' }[r] ?? 'ri-user-line'
}
function roleLabel(r) {
  return { admin: 'Administrator', qc_admission: 'QC Admission', kasir: 'Kasir' }[r] ?? (r ?? '—')
}
function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
function notify(message, color = 'success') {
  snackbar.value = { show: true, message, color }
}

async function loadUsers() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/users')
    users.value = Array.isArray(data) ? data : (data.data ?? [])
    if (data.source === 'error') {
      notify(data.message ?? 'Gagal memuat data dari Keycloak.', 'warning')
    }
  } catch (e) {
    const msg = e.response?.data?.message ?? 'Gagal memuat data user.'
    notify(msg, 'error')
  } finally {
    loading.value = false
  }
}

async function refreshCache() {
  cacheRefreshing.value = true
  try {
    await axios.post('/api/users/refresh-cache')
    notify('Cache direset. Memuat ulang data dari Keycloak...')
    await loadUsers()
  } catch {
    notify('Gagal mereset cache.', 'error')
  } finally {
    cacheRefreshing.value = false
  }
}

onMounted(() => loadUsers())
</script>

<template>
  <div>
    <!-- Hero -->
    <PageHero
      icon="ri-team-line"
      badge="Admin · Manajemen User"
      title="Manajemen User"
      subtitle="Daftar pengguna dikelola melalui Keycloak SSO · Hanya tampilan"
      color-from="#0EA5E9"
      color-to="#0369A1"
      text-color="dark"
      :pills="[
        { icon: 'ri-team-line',    text: `${stats.total} user` },
        { icon: 'ri-shield-user-line', text: `${stats.admin} admin` },
      ]"
    >
      <template #actions>
        <VBtn
          color="white"
          variant="elevated"
          rounded="lg"
          prepend-icon="ri-refresh-line"
          style="color:#0369A1"
          :loading="cacheRefreshing"
          @click="refreshCache"
        >
          Refresh
        </VBtn>
      </template>
    </PageHero>

    <!-- Info banner -->
    <VAlert
      type="info"
      variant="tonal"
      rounded="lg"
      class="mb-4"
      icon="ri-information-line"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div>
          <strong>Manajemen user dikelola melalui Keycloak Admin Console.</strong>
          <span class="ms-1 text-body-2">Untuk menambah, mengubah role, atau menonaktifkan user, silakan masuk ke Keycloak.</span>
        </div>
        <VBtn
          v-if="keycloakAdminUrl"
          :href="keycloakAdminUrl"
          target="_blank"
          rel="noopener noreferrer"
          color="info"
          variant="tonal"
          size="small"
          rounded="lg"
          append-icon="ri-external-link-line"
        >
          Buka Keycloak
        </VBtn>
      </div>
    </VAlert>

    <!-- Stats -->
    <VRow dense class="mb-4">
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total User</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-error))">{{ stats.admin }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-shield-star-line" size="12" class="me-1" />Administrator
          </p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold text-primary mb-0">{{ stats.qc_admission }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-nurse-line" size="12" class="me-1" />QC Admission
          </p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.kasir }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-money-cny-box-line" size="12" class="me-1" />Kasir
          </p>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filter -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VTextField
          v-model="search"
          placeholder="Cari nama, username, email, role..."
          prepend-inner-icon="ri-search-line"
          variant="outlined" density="compact" hide-details clearable
        />
      </VCardText>
    </VCard>

    <!-- Table -->
    <VCard elevation="0" border rounded="lg">
      <VDataTable
        :headers="headers"
        :items="filteredUsers"
        :loading="loading"
        density="comfortable"
        hover
        :items-per-page="15"
      >
        <!-- Nama + email -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-3 py-1">
            <VAvatar :color="roleColor(item.role)" variant="tonal" size="36" rounded="lg">
              <VIcon :icon="roleIcon(item.role)" size="18" />
            </VAvatar>
            <div>
              <p class="text-body-2 font-weight-semibold mb-0">{{ item.name }}</p>
              <p class="text-caption text-medium-emphasis mb-0">{{ item.email }}</p>
            </div>
          </div>
        </template>

        <!-- Role -->
        <template #item.role="{ item }">
          <VChip
            :color="roleColor(item.role)"
            size="small"
            variant="tonal"
            :prepend-icon="roleIcon(item.role)"
          >
            {{ roleLabel(item.role) }}
          </VChip>
        </template>

        <!-- Status enabled -->
        <template #item.enabled="{ item }">
          <VChip
            :color="item.enabled !== false ? 'success' : 'error'"
            size="x-small"
            variant="tonal"
            :prepend-icon="item.enabled !== false ? 'ri-checkbox-circle-line' : 'ri-close-circle-line'"
          >
            {{ item.enabled !== false ? 'Aktif' : 'Nonaktif' }}
          </VChip>
        </template>

        <!-- Tanggal -->
        <template #item.created_at="{ item }">
          <span class="text-caption text-medium-emphasis">{{ formatDate(item.created_at) }}</span>
        </template>

        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon icon="ri-team-line" size="40" class="mb-2 opacity-40" />
            <p class="mb-1 font-weight-medium">Belum ada user</p>
            <p class="text-caption mb-0">Data diambil dari Keycloak. Pastikan service account memiliki role <code>view-users</code>.</p>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Snackbar -->
    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="4000" location="bottom right" rounded="lg">
      {{ snackbar.message }}
      <template #actions>
        <VBtn variant="text" @click="snackbar.show = false">Tutup</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>
