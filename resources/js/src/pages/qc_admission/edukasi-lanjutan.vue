<script setup>
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import EdukasiPatientCard  from '@/views/qc-admission/edukasi-lanjutan/EdukasiPatientCard.vue'
import EdukasiDetailDialog from '@/views/qc-admission/edukasi-lanjutan/EdukasiDetailDialog.vue'
import axios from 'axios'

const store   = useEdukasiLanjutanStore()
const syncing  = ref(false)
const lastSync = ref(null)

// ── Filters ───────────────────────────────────────────────────────────────────
const searchQuery  = ref('')
const filterBulan  = ref('All')
const filterStatus = ref('All')
const filterPetugas = ref('')
const filterDateFrom = ref('')
const filterDateTo   = ref('')

// ── Mock records ──────────────────────────────────────────────────────────────
const records = ref([
  { id:1, no_mr:'813500', no_reg:'REG001', nama_pasien:'ELLY MAYA, NY',     jaminan:'BPJS',     bulan:'JUNI', tanggal:'29/06/2026', edukasi_kamar:'Ruang Mawar',   note:'Keluarga hadir',  petugas:'Nurul',           keluarga_pasien:'Bambang', ttd_keluarga_pasien:'', status:'Menunggu', quality_control_id:2 },
  { id:2, no_mr:'575360', no_reg:'REG002', nama_pasien:'IDH SUBINGSEN, NY', jaminan:'BPJS',     bulan:'JUNI', tanggal:'29/06/2026', edukasi_kamar:'Ruang Anggrek', note:'Pasien mengerti', petugas:'Reskim',          keluarga_pasien:'Siti',    ttd_keluarga_pasien:'', status:'Selesai',  quality_control_id:5 },
  { id:3, no_mr:'087220', no_reg:'REG003', nama_pasien:'RUSMINI, NY',       jaminan:'Umum',     bulan:'JUNI', tanggal:'28/06/2026', edukasi_kamar:'',              note:'',                petugas:'AYU Putri Anisa', keluarga_pasien:'',        ttd_keluarga_pasien:'', status:'Menunggu', quality_control_id:8 },
  { id:4, no_mr:'816302', no_reg:'REG004', nama_pasien:'PUSPA SARI, AN',    jaminan:'BPJS',     bulan:'MEI',  tanggal:'27/05/2026', edukasi_kamar:'ICU',           note:'Kondisi kritis',  petugas:'Nurul',           keluarga_pasien:'Rini',    ttd_keluarga_pasien:'', status:'Selesai',  quality_control_id:11 },
  { id:5, no_mr:'712405', no_reg:'REG005', nama_pasien:'BUDI SANTOSO, TN',  jaminan:'Asuransi', bulan:'JUNI', tanggal:'29/06/2026', edukasi_kamar:'Ruang Dahlia',  note:'',                petugas:'Reskim',          keluarga_pasien:'',        ttd_keluarga_pasien:'', status:'Menunggu', quality_control_id:13 },
])

// ── Computed ──────────────────────────────────────────────────────────────────
const bulanList = computed(() => ['All', ...new Set(records.value.map(r => r.bulan))])

const filtered = computed(() => {
  let data = records.value
  if (filterBulan.value !== 'All')   data = data.filter(r => r.bulan === filterBulan.value)
  if (filterStatus.value !== 'All')  data = data.filter(r => r.status === filterStatus.value)
  if (filterPetugas.value.trim())    data = data.filter(r => r.petugas?.toLowerCase().includes(filterPetugas.value.toLowerCase()))
  if (filterDateFrom.value)          data = data.filter(r => r.tanggal >= filterDateFrom.value)
  if (filterDateTo.value)            data = data.filter(r => r.tanggal <= filterDateTo.value)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    data = data.filter(r =>
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.no_mr?.toLowerCase().includes(q) ||
      r.no_reg?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q)
    )
  }
  return data
})

const stats = computed(() => ({
  total:    records.value.length,
  menunggu: records.value.filter(r => r.status === 'Menunggu').length,
  selesai:  records.value.filter(r => r.status === 'Selesai').length,
}))

// ── Sync SIMRS/ERM ────────────────────────────────────────────────────────────
async function syncFromRsus() {
  syncing.value = true
  try {
    await axios.get('/api/edukasi-lanjutan/sync-rsus')
    // Reload dari API setelah sync
    await store.fetchRecords()
    if (store.records?.length) records.value = store.records
    lastSync.value = new Date()
  } catch (e) {
    console.warn('Sync RSUS gagal:', e.message)
    lastSync.value = new Date()
  } finally {
    syncing.value = false
  }
}

// ── Detail dialog ─────────────────────────────────────────────────────────────
const detailDialog    = ref(false)
const selectedPatient = ref(null)
const dialogMode      = ref('view')

function openDetail(patient) { selectedPatient.value = patient; dialogMode.value = 'view'; detailDialog.value = true }
function openEdit(patient)   { selectedPatient.value = patient; dialogMode.value = 'edit'; detailDialog.value = true }

function onSaved(data) {
  const idx = records.value.findIndex(r => r.id === data.id)
  if (idx !== -1) records.value.splice(idx, 1, { ...records.value[idx], ...data })
  detailDialog.value = false
}

function resetFilters() {
  filterBulan.value   = 'All'
  filterStatus.value  = 'All'
  searchQuery.value   = ''
  filterPetugas.value = ''
  filterDateFrom.value = ''
  filterDateTo.value   = ''
}

onMounted(async () => {
  try {
    await store.fetchRecords()
    if (store.records?.length) records.value = store.records
  } catch {}
})
</script>

<template>
  <div>
    <!-- Hero -->
    <div class="page-hero page-hero--edukasi mb-5">
      <div class="page-hero__content">
        <div class="page-hero__badge">
          <VIcon icon="ri-book-open-line" size="13" />
          Hiro · Edukasi Lanjutan
        </div>
        <h1 class="page-hero__title">Edukasi Lanjutan</h1>
        <p class="page-hero__subtitle">
          Auto-trigger dari QC ≥ 2 jam · Status bed real-time dari SIMRS/ERM
        </p>
      </div>
      <div class="d-flex gap-2 align-center" style="position:relative;z-index:2">
        <VBtn
          color="white" variant="elevated" rounded="lg" size="small"
          prepend-icon="ri-refresh-line"
          style="color:#f5576c"
          :loading="syncing"
          @click="syncFromRsus"
        >
          Sync SIMRS
        </VBtn>
        <span v-if="lastSync" class="text-caption" style="color:rgba(255,255,255,0.75)">
          <VIcon icon="ri-time-line" size="12" class="me-1" />
          {{ lastSync.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' }) }}
        </span>
      </div>
      <VIcon icon="ri-book-open-line" class="page-hero__icon" />
    </div>

    <!-- Info -->
    <VAlert type="info" variant="tonal" border="start" density="compact" class="mb-4" closable>
      <div class="text-caption">
        <strong>Cara kerja:</strong>
        Data masuk otomatis dari QC saat status <strong>"Edukasi Lanjutan"</strong> + durasi tunggu <strong>≥ 2 jam</strong>.
        Klik <strong>Sync SIMRS</strong> untuk cek status bed terbaru — pasien yang sudah dapat bed otomatis <strong>Selesai</strong>.
      </div>
    </VAlert>

    <!-- Stats — clickable filter -->
    <VRow dense class="mb-4">
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatus==='All' ? 'stat-active' : ''"
          @click="filterStatus='All'">
          <p class="text-h4 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Total</p>
        </VCard>
      </VCol>
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatus==='Menunggu' ? 'stat-active-warning' : ''"
          @click="filterStatus = filterStatus==='Menunggu' ? 'All' : 'Menunggu'">
          <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.menunggu }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Menunggu Bed</p>
        </VCard>
      </VCol>
      <VCol cols="4">
        <VCard elevation="0" border rounded="lg" class="stat-card text-center pa-3 cursor-pointer"
          :class="filterStatus==='Selesai' ? 'stat-active-success' : ''"
          @click="filterStatus = filterStatus==='Selesai' ? 'All' : 'Selesai'">
          <p class="text-h4 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ stats.selesai }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Sudah Dapat Bed</p>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filter bar -->
    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardText class="py-3">
        <VRow dense align="center">
          <VCol cols="12" sm="4">
            <VTextField
              v-model="searchQuery"
              placeholder="Cari nama / no reg / no MR / petugas..."
              prepend-inner-icon="ri-search-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VSelect
              v-model="filterBulan"
              :items="bulanList"
              label="Bulan"
              variant="outlined" density="compact" hide-details
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField
              v-model="filterPetugas"
              placeholder="Petugas"
              prepend-inner-icon="ri-nurse-line"
              variant="outlined" density="compact" hide-details clearable
            />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="filterDateFrom" label="Dari" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
          <VCol cols="6" sm="2">
            <VTextField v-model="filterDateTo" label="Sampai" type="date" variant="outlined" density="compact" hide-details />
          </VCol>
        </VRow>
        <div class="d-flex justify-end gap-2 mt-2">
          <VBtn size="small" variant="text" color="secondary" prepend-icon="ri-refresh-line" @click="resetFilters">Reset</VBtn>
          <VBtnToggle v-model="filterStatus" mandatory density="compact" rounded="lg">
            <VBtn value="All"      size="small" variant="outlined">Semua</VBtn>
            <VBtn value="Menunggu" size="small" variant="outlined" color="warning">Menunggu</VBtn>
            <VBtn value="Selesai"  size="small" variant="outlined" color="success">Selesai</VBtn>
          </VBtnToggle>
        </div>
      </VCardText>
    </VCard>

    <!-- Result count -->
    <div class="d-flex align-center gap-2 mb-3 flex-wrap">
      <VChip size="small" color="primary" variant="tonal">{{ filtered.length }} data</VChip>
      <span class="text-caption text-disabled">dari {{ records.length }} total</span>
      <VSpacer />
      <span v-if="lastSync" class="text-caption text-disabled">
        <VIcon icon="ri-refresh-line" size="12" class="me-1" />
        Sync: {{ lastSync.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' }) }}
      </span>
    </div>

    <!-- Cards grid -->
    <VRow v-if="filtered.length" dense>
      <VCol v-for="patient in filtered" :key="patient.id" cols="12" sm="6" md="4" lg="3">
        <EdukasiPatientCard :patient="patient" @view="openDetail" @edit="openEdit" />
      </VCol>
    </VRow>

    <!-- Empty state -->
    <div v-else class="text-center py-16 text-medium-emphasis">
      <VIcon icon="ri-book-open-line" size="56" class="mb-3 opacity-40" />
      <p class="text-body-1 font-weight-medium mb-1">Tidak ada data</p>
      <p class="text-body-2 mb-4">Coba ubah filter atau klik Sync SIMRS untuk data terbaru</p>
      <VBtn variant="tonal" color="primary" prepend-icon="ri-refresh-line" @click="resetFilters">Reset Filter</VBtn>
    </div>

    <!-- Detail dialog -->
    <EdukasiDetailDialog v-model="detailDialog" :patient="selectedPatient" :mode="dialogMode" @saved="onSaved" />
  </div>
</template>

<style scoped>
.stat-card { transition: box-shadow 0.2s, transform 0.15s; cursor: pointer; }
.stat-card:hover { box-shadow: 0 4px 16px rgba(var(--v-shadow-key-umbra-color), 0.1) !important; transform: translateY(-1px); }
</style>
