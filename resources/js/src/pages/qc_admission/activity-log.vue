<script setup>
import axios from 'axios'

const loading        = ref(false)
const records        = ref([])
const search         = ref('')
const filterModule   = ref('')
const filterAction   = ref('')
const filterUser     = ref('')
const filterDateFrom = ref('')
const filterDateTo   = ref('')
const page           = ref(1)
const perPage        = ref(30)
const total          = ref(0)
const lastPage       = ref(1)

const MODULE_OPTIONS = [
  { title: 'Semua Modul',      value: '' },
  { title: 'Quality Control',  value: 'quality-control' },
  { title: 'Edukasi Lanjutan', value: 'edukasi-lanjutan' },
  { title: 'Batal Ranap',      value: 'batal-ranap' },
  { title: 'Up Selling',       value: 'up-selling' },
  { title: 'Master Data',      value: 'master-data' },
  { title: 'User',             value: 'user' },
  { title: 'Auth',             value: 'auth' },
]

const ACTION_OPTIONS = [
  { title: 'Semua Aksi',   value: '' },
  { title: 'Login',        value: 'login' },
  { title: 'Logout',       value: 'logout' },
  { title: 'Create',       value: 'create' },
  { title: 'Update',       value: 'update' },
  { title: 'Delete',       value: 'delete' },
  { title: 'Verifikasi',   value: 'verifikasi' },
]

const headers = [
  { title: 'Waktu',       key: 'created_at',  sortable: true, width: '160px' },
  { title: 'User',        key: 'user_name',   sortable: true },
  { title: 'Role',        key: 'user_role',   sortable: true },
  { title: 'Modul',       key: 'module',      sortable: true },
  { title: 'Aksi',        key: 'action',      sortable: true, align: 'center' },
  { title: 'Keterangan',  key: 'subject',     sortable: false },
  { title: 'IP',          key: 'ip_address',  sortable: false, width: '110px' },
]

function actionColor(a) {
  return { login:'success', logout:'secondary', create:'primary', update:'warning', delete:'error', verifikasi:'info' }[a] ?? 'default'
}
function actionIcon(a) {
  return {
    login:      'ri-login-circle-line',
    logout:     'ri-logout-circle-line',
    create:     'ri-add-circle-line',
    update:     'ri-pencil-line',
    delete:     'ri-delete-bin-line',
    verifikasi: 'ri-check-double-line',
  }[a] ?? 'ri-circle-line'
}
function moduleColor(m) {
  return {
    'quality-control':  'primary',
    'edukasi-lanjutan': 'warning',
    'batal-ranap':      'error',
    'up-selling':       'success',
    'master-data':      'info',
    'auth':             'secondary',
    'user':             'deep-purple',
  }[m] ?? 'default'
}
function formatDate(d) {
  return new Date(d).toLocaleString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
function roleColor(r) {
  return { admin: 'error', qc_admission: 'primary', kasir: 'warning' }[r] ?? 'default'
}
function roleLabel(r) {
  return { admin: 'Admin', qc_admission: 'QC Admission', kasir: 'Kasir' }[r] ?? r
}

function resetFilters() {
  search.value         = ''
  filterModule.value   = ''
  filterAction.value   = ''
  filterUser.value     = ''
  filterDateFrom.value = ''
  filterDateTo.value   = ''
  page.value           = 1
  doRefresh()
}

async function doRefresh() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/activity-log', {
      params: {
        search:    search.value       || undefined,
        module:    filterModule.value || undefined,
        action:    filterAction.value || undefined,
        user:      filterUser.value   || undefined,
        date_from: filterDateFrom.value || undefined,
        date_to:   filterDateTo.value   || undefined,
        page:      page.value,
        per_page:  perPage.value,
      },
    })
    records.value = data.data           ?? []
    total.value   = data.meta?.total    ?? data.total ?? 0
    lastPage.value = data.meta?.last_page ?? 1
  } catch (e) {
    console.error('Activity log error', e)
  } finally {
    loading.value = false
  }
}

// Stats derived from current page
const stats = computed(() => ({
  total:   total.value,
  today:   records.value.filter(r => {
    const d = new Date(r.created_at)
    const n = new Date()
    return d.getDate() === n.getDate() && d.getMonth() === n.getMonth() && d.getFullYear() === n.getFullYear()
  }).length,
  logins:  records.value.filter(r => r.action === 'login').length,
  creates: records.value.filter(r => r.action === 'create').length,
}))

watch([filterModule, filterAction, filterUser, search, filterDateFrom, filterDateTo], () => {
  page.value = 1
  doRefresh()
}, { debounce: 400 })

onMounted(() => doRefresh())
</script>

<template>
  <div>
    <!-- Hero -->
    <div class="page-hero page-hero--view mb-5">
      <div class="page-hero__content">
        <div class="page-hero__badge">
          <VIcon icon="ri-history-line" size="13" />
          Admin · Log Aktivitas
        </div>
        <h1 class="page-hero__title">Log Aktivitas</h1>
        <p class="page-hero__subtitle">Rekam jejak semua aktivitas pengguna di sistem · Real-time dari database</p>
      </div>
      <div style="position:relative;z-index:2">
        <VBtn color="white" variant="elevated" rounded="lg" size="small"
          prepend-icon="ri-refresh-line" style="color:#4facfe"
          :loading="loading" @click="doRefresh">
          Refresh
        </VBtn>
      </div>
      <VIcon icon="ri-history-line" class="page-hero__icon" />
    </div>

    <!-- Stats cards -->
    <VRow dense class="mb-4">
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total Log</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ stats.today }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Hari Ini</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-info))">{{ stats.logins }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Login</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="pa-3 text-center">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.creates }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Input Data</p>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filter bar -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VRow dense align="center">
          <VCol cols="12" sm="3">
            <VTextField
              v-model="search"
              placeholder="Cari user, keterangan, modul..."
              prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol cols="12" sm="2">
            <VTextField
              v-model="filterUser"
              placeholder="Nama user..."
              prepend-inner-icon="ri-user-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VSelect
              v-model="filterModule"
              :items="MODULE_OPTIONS"
              item-title="title" item-value="value"
              variant="outlined" density="compact" hide-details
              placeholder="Modul"
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VSelect
              v-model="filterAction"
              :items="ACTION_OPTIONS"
              item-title="title" item-value="value"
              variant="outlined" density="compact" hide-details
              placeholder="Aksi"
            />
          </VCol>
          <VCol cols="6" sm="1">
            <VTextField v-model="filterDateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="6" sm="1">
            <VTextField v-model="filterDateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" prepend-icon="ri-refresh-line" @click="resetFilters">Reset</VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Count -->
    <div class="d-flex align-center gap-2 mb-3">
      <VChip size="small" color="primary" variant="tonal">{{ total }} aktivitas</VChip>
      <span class="text-caption text-disabled">total log tersimpan</span>
    </div>

    <!-- Table -->
    <VCard elevation="0" border rounded="lg">
      <VDataTable
        :headers="headers"
        :items="records"
        :loading="loading"
        density="compact"
        hover
        :items-per-page="perPage"
        hide-default-footer
      >
        <template #item.created_at="{ item }">
          <span class="text-caption text-medium-emphasis">{{ formatDate(item.created_at) }}</span>
        </template>

        <template #item.user_name="{ item }">
          <div class="d-flex align-center gap-2">
            <VAvatar :color="roleColor(item.user_role)" variant="tonal" size="26" rounded="lg">
              <span style="font-size:10px;font-weight:700">{{ item.user_name?.charAt(0)?.toUpperCase() }}</span>
            </VAvatar>
            <span class="text-body-2 font-weight-medium">{{ item.user_name || '—' }}</span>
          </div>
        </template>

        <template #item.user_role="{ item }">
          <VChip :color="roleColor(item.user_role)" size="x-small" variant="tonal">
            {{ roleLabel(item.user_role) }}
          </VChip>
        </template>

        <template #item.module="{ item }">
          <VChip :color="moduleColor(item.module)" size="x-small" variant="tonal">
            {{ item.module || '—' }}
          </VChip>
        </template>

        <template #item.action="{ item }">
          <VChip :color="actionColor(item.action)" size="small" variant="tonal">
            <VIcon :icon="actionIcon(item.action)" size="12" class="me-1" />
            {{ item.action }}
          </VChip>
        </template>

        <template #item.subject="{ item }">
          <span class="text-body-2">{{ item.subject || '—' }}</span>
        </template>

        <template #item.ip_address="{ item }">
          <span class="text-caption text-disabled font-mono">{{ item.ip_address || '—' }}</span>
        </template>

        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon icon="ri-history-line" size="40" class="mb-2 opacity-40" />
            <p class="mb-1 font-weight-medium">Belum ada log aktivitas</p>
            <p class="text-caption mb-0">Log akan muncul ketika ada aktivitas di sistem</p>
          </div>
        </template>

        <template #bottom>
          <div class="d-flex align-center justify-space-between pa-3 flex-wrap gap-2">
            <span class="text-caption text-medium-emphasis">
              Halaman {{ page }} dari {{ lastPage }} ({{ total }} total)
            </span>
            <div class="d-flex gap-2">
              <VBtn
                icon size="small" variant="tonal"
                :disabled="page <= 1 || loading"
                @click="page--; doRefresh()"
              >
                <VIcon icon="ri-arrow-left-s-line" />
              </VBtn>
              <VBtn
                icon size="small" variant="tonal"
                :disabled="page >= lastPage || loading"
                @click="page++; doRefresh()"
              >
                <VIcon icon="ri-arrow-right-s-line" />
              </VBtn>
            </div>
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>

<style scoped>
.font-mono { font-family: 'Courier New', monospace; font-size: 0.75rem; }
</style>
