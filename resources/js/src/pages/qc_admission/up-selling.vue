<script setup>
import { useUpSellingStore } from '@/stores/useUpSellingStore'
import UpSellingFormDialog from '@/views/qc-admission/up-selling/UpSellingFormDialog.vue'

const store = useUpSellingStore()

const showDialog        = ref(false)
const editItem          = ref(null)
const showDeleteConfirm = ref(false)
const deleteTarget      = ref(null)
const loading           = ref(false)
const lastRefresh       = ref(null)
const search            = ref('')
const filterStatus      = ref(null)
const filterDateFrom    = ref('')
const filterDateTo      = ref('')
const snackbar          = ref({ show: false, message: '', color: 'success' })

const STATUS_OPTIONS = [
  { title: 'Semua Status',    value: null },
  { title: 'Berhasil',        value: 'Berhasil' },
  { title: 'Tidak Berhasil',  value: 'Tidak Berhasil' },
  { title: 'Pending',         value: 'Pending' },
]

const records  = computed(() => store.records ?? [])

const filtered = computed(() => {
  let d = records.value
  if (filterStatus.value) d = d.filter(r => r.status === filterStatus.value)
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r =>
      r.no_reg?.toLowerCase().includes(q) ||
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q)
    )
  }
  if (filterDateFrom.value) d = d.filter(r => r.tanggal >= filterDateFrom.value)
  if (filterDateTo.value)   d = d.filter(r => r.tanggal <= filterDateTo.value)
  return d
})

const stats = computed(() => ({
  total:          records.value.length,
  berhasil:       records.value.filter(r => r.status === 'Berhasil').length,
  tidakBerhasil:  records.value.filter(r => r.status === 'Tidak Berhasil').length,
  pending:        records.value.filter(r => r.status === 'Pending').length,
  successRate:    records.value.length
    ? Math.round((records.value.filter(r => r.status === 'Berhasil').length / records.value.length) * 100)
    : 0,
}))

const headers = [
  { title: 'Tanggal',         key: 'tanggal',           sortable: true },
  { title: 'No. Reg',         key: 'no_reg',            sortable: true },
  { title: 'Nama Pasien',     key: 'nama_pasien',       sortable: true },
  { title: 'Jaminan',         key: 'jaminan',           sortable: true },
  { title: 'Rek. Kelas',      key: 'rekomendasi_kelas', sortable: true, align: 'center' },
  { title: 'Kelas Diambil',   key: 'kelas_diambil',     sortable: true, align: 'center' },
  { title: 'Keterangan',      key: 'alasan',            sortable: true },
  { title: 'Petugas',         key: 'petugas',           sortable: true },
  { title: 'Status',          key: 'status',            sortable: true, align: 'center' },
  { title: 'Aksi',            key: 'actions',           sortable: false, align: 'center', width: '80px' },
]

function statusColor(s) {
  return { Berhasil: 'success', 'Tidak Berhasil': 'error', Pending: 'warning' }[s] ?? 'secondary'
}
function statusIcon(s) {
  return { Berhasil: 'ri-check-line', 'Tidak Berhasil': 'ri-close-line', Pending: 'ri-time-line' }[s] ?? 'ri-circle-line'
}

function openAdd()        { editItem.value = null;        showDialog.value = true }
function openEdit(item)   { editItem.value = { ...item }; showDialog.value = true }
function openDelete(item) { deleteTarget.value = item;    showDeleteConfirm.value = true }

async function confirmDelete() {
  if (!deleteTarget.value) return
  loading.value = true
  const result = await store.destroy(deleteTarget.value.id)
  if (result?.success) {
    snackbar.value = { show: true, message: 'Data berhasil dihapus.', color: 'success' }
  } else {
    snackbar.value = { show: true, message: result?.message ?? 'Gagal menghapus.', color: 'error' }
  }
  loading.value = false
  showDeleteConfirm.value = false
  deleteTarget.value = null
}

async function onSaved() {
  showDialog.value = false
  await doRefresh()
  snackbar.value = { show: true, message: 'Data berhasil disimpan.', color: 'success' }
}

async function doRefresh() {
  loading.value = true
  try {
    await store.fetchRecords({ per_page: 200,
      search:    search.value       || undefined,
      status:    filterStatus.value || undefined,
      date_from: filterDateFrom.value || undefined,
      date_to:   filterDateTo.value   || undefined,
    })
    lastRefresh.value = new Date()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  search.value       = ''
  filterStatus.value = null
  filterDateFrom.value = ''
  filterDateTo.value   = ''
}

onMounted(() => doRefresh())
</script>

<template>
  <div>
    <!-- Hero -->
    <div class="page-hero page-hero--upselling mb-5">
      <div class="page-hero__content">
        <div class="page-hero__badge">
          <VIcon icon="ri-arrow-up-circle-line" size="13" />
          QC Admission · Up Selling
        </div>
        <h1 class="page-hero__title">Up Selling</h1>
        <p class="page-hero__subtitle">Penawaran upgrade kelas kamar pasien rawat inap</p>
      </div>
      <div class="d-flex gap-2 align-center" style="position:relative;z-index:2">
        <VBtn icon variant="text" color="white" size="small" :loading="loading" @click="doRefresh">
          <VIcon icon="ri-refresh-line" />
        </VBtn>
        <VBtn color="white" variant="elevated" rounded="lg" prepend-icon="ri-add-line" style="color:#56ab2f" @click="openAdd">
          Input Up Selling
        </VBtn>
      </div>
      <VIcon icon="ri-arrow-up-circle-line" class="page-hero__icon" />
    </div>

    <!-- Stats -->
    <VRow dense class="mb-4">
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatus === null ? 'stat-active' : ''"
          @click="filterStatus = null">
          <p class="text-h4 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatus === 'Berhasil' ? 'stat-active-success' : ''"
          @click="filterStatus = filterStatus === 'Berhasil' ? null : 'Berhasil'">
          <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ stats.berhasil }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Berhasil</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatus === 'Tidak Berhasil' ? 'stat-active-error' : ''"
          @click="filterStatus = filterStatus === 'Tidak Berhasil' ? null : 'Tidak Berhasil'">
          <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-error))">{{ stats.tidakBerhasil }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Tidak Berhasil</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="stat-card pa-3">
          <div class="d-flex align-center justify-space-between mb-2">
            <div>
              <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.successRate }}%</p>
              <p class="text-caption text-medium-emphasis mb-0">Success Rate</p>
            </div>
            <VIcon icon="ri-percent-line" color="warning" size="24" />
          </div>
          <VProgressLinear :model-value="stats.successRate" color="success" rounded height="4" bg-color="grey" bg-opacity="0.15" />
        </VCard>
      </VCol>
    </VRow>

    <!-- Filter -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VRow dense align="center">
          <VCol cols="12" sm="4">
            <VTextField
              v-model="search"
              placeholder="Cari No. Reg / Nama Pasien / Petugas..."
              prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol cols="6" sm="3">
            <VSelect
              v-model="filterStatus"
              :items="STATUS_OPTIONS"
              item-title="title" item-value="value"
              variant="outlined" density="compact" hide-details
              placeholder="Status"
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="filterDateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="filterDateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" @click="resetFilters; doRefresh()">Reset</VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Count + last refresh -->
    <div class="d-flex align-center justify-space-between mb-3 flex-wrap gap-2">
      <div class="d-flex align-center gap-2">
        <VChip size="small" color="primary" variant="tonal">{{ filtered.length }} data</VChip>
        <span class="text-caption text-disabled">dari {{ records.length }} total</span>
      </div>
      <span v-if="lastRefresh" class="text-caption text-disabled">
        <VIcon icon="ri-time-line" size="13" class="me-1" />
        {{ lastRefresh.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' }) }}
      </span>
    </div>

    <!-- Table -->
    <VCard elevation="0" border rounded="lg">
      <VDataTable
        :headers="headers"
        :items="filtered"
        :loading="loading"
        density="comfortable"
        hover
        :items-per-page="15"
        class="upselling-table"
      >
        <template #item.nama_pasien="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar color="success" variant="tonal" size="28" rounded="lg">
              <span style="font-size:11px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
            </VAvatar>
            <span class="text-body-2 font-weight-medium">{{ item.nama_pasien }}</span>
          </div>
        </template>

        <template #item.rekomendasi_kelas="{ item }">
          <VChip color="secondary" size="x-small" variant="tonal">{{ item.rekomendasi_kelas || '—' }}</VChip>
        </template>
        <template #item.kelas_diambil="{ item }">
          <VChip color="primary" size="x-small" variant="tonal">{{ item.kelas_diambil || '—' }}</VChip>
        </template>

        <template #item.status="{ item }">
          <VChip :color="statusColor(item.status)" size="small" variant="tonal" :prepend-icon="statusIcon(item.status)">
            {{ item.status || 'Pending' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center">
            <VTooltip text="Edit">
              <template #activator="{ props: tp }">
                <VBtn v-bind="tp" icon size="x-small" variant="text" color="primary" @click="openEdit(item)">
                  <VIcon icon="ri-pencil-line" size="15" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip text="Hapus">
              <template #activator="{ props: tp }">
                <VBtn v-bind="tp" icon size="x-small" variant="text" color="error"
                  @click="openDelete(item)">
                  <VIcon icon="ri-delete-bin-line" size="15" />
                </VBtn>
              </template>
            </VTooltip>
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-12 text-medium-emphasis">
            <VIcon icon="ri-arrow-up-circle-line" size="48" class="mb-3 opacity-40" />
            <p class="text-body-1 font-weight-medium mb-1">Belum ada data up selling</p>
            <p class="text-caption mb-4">Klik "Input Up Selling" untuk menambah data baru</p>
            <VBtn color="success" variant="tonal" prepend-icon="ri-add-line" rounded="lg" @click="openAdd">Input Up Selling</VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <UpSellingFormDialog v-model="showDialog" :edit-item="editItem" @saved="onSaved" />

    <!-- Delete confirm -->
    <VDialog v-model="showDeleteConfirm" max-width="380">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="60" rounded="xl" class="mb-4">
            <VIcon icon="ri-delete-bin-2-line" size="30" />
          </VAvatar>
          <h6 class="text-h6 font-weight-bold mb-2">Hapus Data?</h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Data <strong>{{ deleteTarget?.nama_pasien }}</strong> akan dihapus permanen.
          </p>
        </VCardText>
        <VCardActions class="px-6 pb-5 pt-0 d-flex gap-2">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDeleteConfirm = false">Batal</VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" :loading="loading" @click="confirmDelete">Hapus</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000" location="bottom right" rounded="lg">
      {{ snackbar.message }}
      <template #actions>
        <VBtn variant="text" @click="snackbar.show = false">Tutup</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>
