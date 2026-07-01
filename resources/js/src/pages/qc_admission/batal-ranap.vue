<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import BatalRanapFormDialog   from '@/views/qc-admission/batal-ranap/BatalRanapFormDialog.vue'
import BatalRanapDetailDialog from '@/views/qc-admission/batal-ranap/BatalRanapDetailDialog.vue'

const store = useBatalRanapStore()

const loading           = ref(false)
const lastRefresh       = ref(null)
const showFormDialog    = ref(false)
const editItem          = ref(null)
const showDeleteConfirm = ref(false)
const deleteTarget      = ref(null)
const showDetailDialog  = ref(false)
const selectedDetail    = ref(null)
const snackbar          = ref({ show: false, message: '', color: 'success' })

// Filters
const search         = ref('')
const filterStatusOk = ref(null)
const filterDateFrom = ref('')
const filterDateTo   = ref('')

const STATUS_OPTIONS = [
  { title: 'Semua Status',     value: null },
  { title: 'Bedah',            value: 'Bedah' },
  { title: 'Non Bedah',        value: 'Non Bedah' },
  { title: 'Belum Diverifikasi', value: '' },
]

const records  = computed(() => store.records ?? [])

const filtered = computed(() => {
  let d = records.value
  if (filterStatusOk.value === '') {
    d = d.filter(r => !r.status_ok)
  } else if (filterStatusOk.value) {
    d = d.filter(r => r.status_ok === filterStatusOk.value)
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r =>
      r.no_reg?.toLowerCase().includes(q) ||
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q) ||
      r.keterangan_batal?.toLowerCase().includes(q)
    )
  }
  if (filterDateFrom.value) d = d.filter(r => r.tanggal >= filterDateFrom.value)
  if (filterDateTo.value)   d = d.filter(r => r.tanggal <= filterDateTo.value)
  return d
})

const stats = computed(() => ({
  total:        records.value.length,
  belum:        records.value.filter(r => !r.status_ok).length,
  bedah:        records.value.filter(r => r.status_ok === 'Bedah').length,
  nonBedah:     records.value.filter(r => r.status_ok === 'Non Bedah').length,
}))

const headers = [
  { title: 'Tanggal',          key: 'tanggal',          sortable: true },
  { title: 'No. Reg',          key: 'no_reg',           sortable: true },
  { title: 'Nama Pasien',      key: 'nama_pasien',      sortable: true },
  { title: 'Keterangan Batal', key: 'keterangan_batal', sortable: true },
  { title: 'Ruangan',          key: 'ruangan',          sortable: true },
  { title: 'Status OK',        key: 'status_ok',        sortable: true, align: 'center' },
  { title: 'Closing',          key: 'status_closing',   sortable: true, align: 'center' },
  { title: 'Petugas',          key: 'petugas',          sortable: true },
  { title: 'Aksi',             key: 'actions',          sortable: false, align: 'center', width: '70px' },
]

function statusColor(s) {
  return { Bedah: 'success', 'Non Bedah': 'info' }[s] ?? 'secondary'
}
function closingColor(s) {
  return s === 'Siap Closing' ? 'success' : s === 'Belum Siap Closing' ? 'error' : 'secondary'
}

function notify(msg, color = 'success') {
  snackbar.value = { show: true, message: msg, color }
}

function openInput()         { editItem.value = null;        showFormDialog.value = true }
function openEdit(item)      { editItem.value = { ...item }; showFormDialog.value = true }
function openDetail(item)    { selectedDetail.value = item;  showDetailDialog.value = true }
function openDelete(item)    { deleteTarget.value = item;    showDeleteConfirm.value = true }

async function confirmDelete() {
  if (!deleteTarget.value) return
  loading.value = true
  const result = await store.destroy(deleteTarget.value.id)
  loading.value = false
  showDeleteConfirm.value = false
  deleteTarget.value = null
  result?.success ? notify('Data berhasil dihapus.') : notify(result?.message ?? 'Gagal menghapus.', 'error')
}

async function onSaved() {
  showFormDialog.value = false
  await doRefresh()
  notify('Data berhasil disimpan.')
}

async function onVerified() {
  await doRefresh()
  notify('Verifikasi berhasil disimpan.')
}

async function doRefresh() {
  loading.value = true
  try {
    await store.fetchRecords({
      per_page: 200,
      search:    search.value         || undefined,
      status_ok: filterStatusOk.value || undefined,
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
  search.value         = ''
  filterStatusOk.value = null
  filterDateFrom.value = ''
  filterDateTo.value   = ''
}

onMounted(() => doRefresh())
</script>

<template>
  <div>
    <!-- Hero -->
    <div class="page-hero page-hero--batal mb-5">
      <div class="page-hero__content">
        <div class="page-hero__badge">
          <VIcon icon="ri-close-circle-line" size="13" />
          QC Admission · Batal Ranap
        </div>
        <h1 class="page-hero__title">Batal Ranap</h1>
        <p class="page-hero__subtitle">Kelola dan verifikasi data pasien batal rawat inap</p>
      </div>
      <div class="d-flex gap-2 align-center" style="position:relative;z-index:2">
        <VBtn icon variant="text" color="white" size="small" :loading="loading" @click="doRefresh">
          <VIcon icon="ri-refresh-line" />
        </VBtn>
        <VBtn color="white" variant="elevated" rounded="lg" prepend-icon="ri-add-line" style="color:#ee5a24" @click="openInput">
          Input Batal Ranap
        </VBtn>
      </div>
      <VIcon icon="ri-close-circle-line" class="page-hero__icon" />
    </div>

    <!-- Info -->
    <VAlert type="info" variant="tonal" border="start" density="compact" class="mb-4" closable>
      <div class="text-caption">
        <strong>Integrasi Bed Management IGD:</strong>
        Ketika status diset ke <strong>"Bedah"</strong>, sistem otomatis update status bed di aplikasi IGD.
        Gunakan tombol verifikasi (✓) di kolom Aksi untuk mengubah status.
      </div>
    </VAlert>

    <!-- Stats -->
    <VRow dense class="mb-4">
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatusOk === null ? 'stat-active' : ''"
          @click="filterStatusOk = null">
          <p class="text-h4 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatusOk === '' ? 'stat-active-warning' : ''"
          @click="filterStatusOk = filterStatusOk === '' ? null : ''">
          <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.belum }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            <VIcon icon="ri-time-line" size="12" class="me-1" />Belum Diverifikasi
          </p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatusOk === 'Bedah' ? 'stat-active-success' : ''"
          @click="filterStatusOk = filterStatusOk === 'Bedah' ? null : 'Bedah'">
          <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ stats.bedah }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Bedah</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatusOk === 'Non Bedah' ? 'stat-active-info' : ''"
          @click="filterStatusOk = filterStatusOk === 'Non Bedah' ? null : 'Non Bedah'">
          <p class="text-h4 font-weight-bold text-info mb-0">{{ stats.nonBedah }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Non Bedah</p>
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
          <VCol cols="6" sm="2">
            <VSelect
              v-model="filterStatusOk"
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
            <VBtn size="small" variant="text" color="secondary" prepend-icon="ri-refresh-line" @click="resetFilters; doRefresh()">Reset</VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Count -->
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

    <!-- Table — klik baris buka modal detail -->
    <VCard elevation="0" border rounded="lg">
      <VDataTable
        :headers="headers"
        :items="filtered"
        :loading="loading"
        density="comfortable"
        hover
        :items-per-page="15"
        class="batal-table"
        @click:row="(_, { item }) => openDetail(item)"
      >
        <template #item.nama_pasien="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar color="error" variant="tonal" size="28" rounded="lg">
              <span style="font-size:11px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
            </VAvatar>
            <span class="text-body-2 font-weight-medium">{{ item.nama_pasien }}</span>
          </div>
        </template>

        <template #item.status_ok="{ item }">
          <VChip :color="statusColor(item.status_ok)" size="small" variant="tonal" label>
            {{ item.status_ok || '—' }}
          </VChip>
        </template>

        <template #item.status_closing="{ item }">
          <VChip
            v-if="item.status_closing"
            :color="closingColor(item.status_closing)"
            size="small" variant="tonal" label
          >
            {{ item.status_closing }}
          </VChip>
          <span v-else class="text-caption text-disabled">—</span>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center" @click.stop>
            <VTooltip text="Edit">
              <template #activator="{ props: tp }">
                <VBtn v-bind="tp" icon size="x-small" variant="text" color="primary" @click="openEdit(item)">
                  <VIcon icon="ri-pencil-line" size="15" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip text="Hapus">
              <template #activator="{ props: tp }">
                <VBtn v-bind="tp" icon size="x-small" variant="text" color="error" @click="openDelete(item)">
                  <VIcon icon="ri-delete-bin-line" size="15" />
                </VBtn>
              </template>
            </VTooltip>
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-12 text-medium-emphasis">
            <VIcon icon="ri-close-circle-line" size="48" class="mb-3 opacity-40" />
            <p class="text-body-1 font-weight-medium mb-1">Belum ada data batal ranap</p>
            <p class="text-caption mb-4">Klik "Input Batal Ranap" untuk menambah data baru</p>
            <VBtn color="error" variant="tonal" prepend-icon="ri-add-line" rounded="lg" @click="openInput">Input Batal Ranap</VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Form Dialog (input/edit) -->
    <BatalRanapFormDialog v-model="showFormDialog" :edit-item="editItem" @saved="onSaved" />

    <!-- Detail & Verifikasi Dialog -->
    <BatalRanapDetailDialog
      v-model="showDetailDialog"
      :item="selectedDetail"
      @verified="onVerified"
      @edit="openEdit"
    />

    <!-- Delete confirm -->
    <VDialog v-model="showDeleteConfirm" max-width="380">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="60" rounded="xl" class="mb-4">
            <VIcon icon="ri-delete-bin-2-line" size="30" />
          </VAvatar>
          <h6 class="text-h6 font-weight-bold mb-2">Hapus Data?</h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Data <strong>{{ deleteTarget?.nama_pasien || deleteTarget?.no_reg }}</strong> akan dihapus permanen.
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

<style scoped>
.stat-card { transition: box-shadow 0.2s, transform 0.15s; cursor: pointer; }
.stat-card:hover { box-shadow: 0 4px 16px rgba(var(--v-shadow-key-umbra-color), 0.1) !important; transform: translateY(-1px); }
</style>
