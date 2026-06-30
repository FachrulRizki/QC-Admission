<script setup>
/**
 * Batal Ranap View — halaman khusus kasir.
 * Hanya bisa melihat data, tidak ada tombol input/edit/hapus.
 */
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'

const store      = useBatalRanapStore()
const search     = ref('')
const dateFrom   = ref('')
const dateTo     = ref('')
const loading    = ref(false)

// Gunakan data dari store, fallback ke mock
const records = computed(() => store.records?.length ? store.records : mockRecords.value)

const mockRecords = ref([
  { id:1, tanggal:'29/06/2026, 19.43.45', no_reg:'REG001BR', nama_pasien:'ELLY MAYA, NY',    keterangan_batal:'Kamar Penuh',   status_ok:'Pending', diagnosa:'Hipertensi', petugas:'Nurul',  ruangan:'Ruang Mawar' },
  { id:2, tanggal:'28/06/2026, 10.20.00', no_reg:'REG003BR', nama_pasien:'RUSMINI, NY',       keterangan_batal:'Pasien Menolak',status_ok:'Bedah',   diagnosa:'Diabetes',   petugas:'Reskim', ruangan:'Ruang Anggrek' },
  { id:3, tanggal:'27/06/2026, 09.15.00', no_reg:'REG005BR', nama_pasien:'BUDI SANTOSO, TN',  keterangan_batal:'DPJP Tidak Setuju',status_ok:'Pending', diagnosa:'Stroke',  petugas:'Nurul',  ruangan:'ICU' },
])

const headers = [
  { title: 'Tanggal',          key: 'tanggal',          sortable: true },
  { title: 'No. Reg',          key: 'no_reg',           sortable: true },
  { title: 'Nama Pasien',      key: 'nama_pasien',      sortable: true },
  { title: 'Keterangan Batal', key: 'keterangan_batal', sortable: true },
  { title: 'Ruangan',          key: 'ruangan',          sortable: true },
  { title: 'Status',           key: 'status_ok',        sortable: true, align: 'center' },
  { title: 'Diagnosa',         key: 'diagnosa',         sortable: true },
  { title: 'Petugas',          key: 'petugas',          sortable: true },
]

function statusColor(s) {
  return { Bedah: 'success', 'Non Bedah': 'info', Pending: 'warning', Ditolak: 'error' }[s] ?? 'secondary'
}

const filtered = computed(() => {
  let d = records.value
  const q = search.value.toLowerCase().trim()
  if (q) d = d.filter(r => Object.values(r).some(v => String(v).toLowerCase().includes(q)))
  return d
})

const stats = computed(() => ({
  total:   records.value.length,
  pending: records.value.filter(r => r.status_ok === 'Pending').length,
  bedah:   records.value.filter(r => r.status_ok === 'Bedah').length,
}))

onMounted(async () => {
  loading.value = true
  try { await store.fetchRecords?.() } catch {}
  loading.value = false
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-5">
      <div>
        <h4 class="page-title">Batal Ranap</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Data pasien pembatalan rawat inap</p>
      </div>
      <VChip color="warning" variant="tonal" prepend-icon="ri-eye-line" size="small">View Only</VChip>
    </div>

    <!-- Stats -->
    <VRow dense class="mb-4">
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="text-center pa-3">
          <p class="text-h5 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total</p>
        </VCard>
      </VCol>
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="text-center pa-3">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.pending }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Pending</p>
        </VCard>
      </VCol>
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="text-center pa-3">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ stats.bedah }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Bedah</p>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filter -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VRow dense align="center">
          <VCol cols="12" sm="6">
            <VTextField v-model="search" placeholder="Cari nama pasien, no reg, petugas..." prepend-inner-icon="ri-search-line" variant="outlined" density="compact" hide-details clearable />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="dateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="auto">
            <VBtn icon variant="text" size="small" color="secondary" @click="search='';dateFrom='';dateTo=''">
              <VIcon icon="ri-refresh-line" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Table -->
    <VCard elevation="0" border rounded="lg">
      <VDataTable
        :headers="headers"
        :items="filtered"
        density="compact"
        hover
        :loading="loading"
        :items-per-page="15"
      >
        <template #item.status_ok="{ item }">
          <VChip :color="statusColor(item.status_ok)" size="small" variant="tonal" label>
            {{ item.status_ok || 'Pending' }}
          </VChip>
        </template>
        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon icon="ri-inbox-line" size="40" class="mb-2 opacity-40" />
            <p class="mb-0">Tidak ada data</p>
          </div>
        </template>
      </VDataTable>
    </VCard>
  </div>
</template>

<style scoped>
.page-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 2px; }
</style>
