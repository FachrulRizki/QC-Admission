<script setup>
import { useUpSellingStore } from '@/stores/useUpSellingStore'
import UpSellingFormDialog   from '@/views/qc-admission/up-selling/UpSellingFormDialog.vue'

const store = useUpSellingStore()

const showDialog        = ref(false)
const editItem          = ref(null)
const showDeleteConfirm = ref(false)
const deleteTarget      = ref(null)
const loading           = ref(false)
const lastRefresh       = ref(null)
const search            = ref('')
const filterDateFrom    = ref('')
const filterDateTo      = ref('')
const snackbar          = ref({ show: false, message: '', color: 'success' })

const records = computed(() => store.records ?? [])

const filtered = computed(() => {
  let d = records.value
  if (search.value.trim()) {
    const q = search.value.toLowerCase()
    d = d.filter(r =>
      r.no_reg?.toLowerCase().includes(q) ||
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q) ||
      r.note?.toLowerCase().includes(q)
    )
  }
  if (filterDateFrom.value) d = d.filter(r => r.tgl_daftar >= filterDateFrom.value || r.tanggal >= filterDateFrom.value)
  if (filterDateTo.value)   d = d.filter(r => r.tgl_daftar <= filterDateTo.value   || r.tanggal <= filterDateTo.value)
  return d
})

// Kolom tabel PERSIS AppSheet:
// tgldaftar | NoReg | NoMR | NamaPasien | ketBayar | NamaRuang | NamaBangsal | Kelas | Ket_Up_Selling | notes | nama_petugas | Aksi
const headers = [
  { title: 'tgldaftar',       key: 'tgl_daftar',        sortable: true },
  { title: 'NoReg',           key: 'no_reg',            sortable: true },
  { title: 'NoMR',            key: 'no_mr',             sortable: true },
  { title: 'NamaPasien',      key: 'nama_pasien',       sortable: true },
  { title: 'ketBayar',        key: 'jaminan',           sortable: true },
  { title: 'NamaRuang',       key: 'nama_ruang',        sortable: true },
  { title: 'NamaBangsal',     key: 'nama_bangsal',      sortable: true },
  { title: 'Kelas',           key: 'kelas',             sortable: true },
  { title: 'Ket_Up_Selling',  key: 'alasan',            sortable: true },
  { title: 'notes',           key: 'note',              sortable: true },
  { title: 'nama_petugas',    key: 'petugas',           sortable: true },
  { title: 'Aksi',            key: 'actions',           sortable: false, align: 'center', width: '80px' },
]

function openAdd()        { editItem.value = null;        showDialog.value = true }
function openEdit(item)   { editItem.value = { ...item }; showDialog.value = true }
function openDelete(item) { deleteTarget.value = item;    showDeleteConfirm.value = true }

async function confirmDelete() {
  if (!deleteTarget.value) return
  loading.value = true
  const result = await store.destroy(deleteTarget.value.id)
  snackbar.value = result?.success
    ? { show: true, message: 'Data berhasil dihapus.', color: 'success' }
    : { show: true, message: result?.message ?? 'Gagal menghapus.', color: 'error' }
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
    await store.fetchRecords({
      per_page:  200,
      search:    search.value         || undefined,
      date_from: filterDateFrom.value || undefined,
      date_to:   filterDateTo.value   || undefined,
    })
    lastRefresh.value = new Date()
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

function resetFilters() {
  search.value       = ''
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
        <VBtn color="white" variant="elevated" rounded="lg" prepend-icon="ri-add-line" style="color:#2d6a4f" @click="openAdd">
          + Input Up Selling
        </VBtn>
      </div>
      <VIcon icon="ri-arrow-up-circle-line" class="page-hero__icon" />
    </div>

    <!-- Filter -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VRow dense align="center">
          <VCol cols="12" sm="5">
            <VTextField
              v-model="search"
              placeholder="Cari No. Reg / Nama Pasien / Petugas / Notes..."
              prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="filterDateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="filterDateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="auto">
            <VBtn size="small" variant="text" color="secondary" @click="resetFilters">Reset</VBtn>
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

    <!-- Tabel persis AppSheet -->
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
        <!-- Kelas — tampilkan field kelas, fallback ke rekomendasi_kelas -->
        <template #item.kelas="{ item }">
          {{ item.kelas || item.rekomendasi_kelas || '—' }}
        </template>

        <!-- NamaRuang — fallback -->
        <template #item.nama_ruang="{ item }">
          {{ item.nama_ruang || '—' }}
        </template>

        <!-- NamaBangsal — fallback -->
        <template #item.nama_bangsal="{ item }">
          {{ item.nama_bangsal || '—' }}
        </template>

        <!-- NoMR — fallback -->
        <template #item.no_mr="{ item }">
          {{ item.no_mr || '—' }}
        </template>

        <!-- tgldaftar — tampilkan tgl_daftar -->
        <template #item.tgl_daftar="{ item }">
          {{ item.tgl_daftar || '—' }}
        </template>

        <!-- NamaPasien dengan avatar -->
        <template #item.nama_pasien="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar color="success" variant="tonal" size="28" rounded="md">
              <span style="font-size:11px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
            </VAvatar>
            <span class="text-body-2">{{ item.nama_pasien }}</span>
          </div>
        </template>

        <!-- Aksi -->
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
                <VBtn v-bind="tp" icon size="x-small" variant="text" color="error" @click="openDelete(item)">
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
            <VBtn color="success" variant="tonal" prepend-icon="ri-add-line" rounded="lg" size="small" class="mt-2" @click="openAdd">
              + Input Up Selling
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <UpSellingFormDialog v-model="showDialog" :edit-item="editItem" @saved="onSaved" />

    <!-- Delete confirm -->
    <VDialog v-model="showDeleteConfirm" max-width="360">
      <VCard rounded="xl">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="56" rounded="xl" class="mb-4">
            <VIcon icon="ri-delete-bin-2-line" size="28" />
          </VAvatar>
          <p class="text-h6 font-weight-bold mb-1">Hapus Data?</p>
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
        <VBtn variant="text" size="small" @click="snackbar.show = false">Tutup</VBtn>
      </template>
    </VSnackbar>
  </div>
</template>
