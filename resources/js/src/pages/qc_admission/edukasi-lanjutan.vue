<script setup>
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import EdukasiPatientCard from '@/views/qc-admission/edukasi-lanjutan/EdukasiPatientCard.vue'
import EdukasiDetailDialog from '@/views/qc-admission/edukasi-lanjutan/EdukasiDetailDialog.vue'

const store   = useEdukasiLanjutanStore()
const qcStore = useQualityControlStore()

// ── Mock records (2hr auto-populated from QC) ──────────────────────────────────
const mockRecords = ref([
  {
    id: 1,
    no_mr: '813500', no_reg: 'REG001',
    nama_pasien: 'ELLY MAYA, NY', jaminan: 'BPJS',
    bulan: 'JUNI', tanggal: '29/06/2026',
    edukasi_kamar: 'Ruang Mawar',
    note: 'Keluarga hadir',
    petugas: 'Nurul',
    keluarga_pasien: 'Bambang',
    ttd_keluarga_pasien: '',
    status: 'Menunggu',
    quality_control_id: 2,
  },
  {
    id: 2,
    no_mr: '575360', no_reg: 'REG002',
    nama_pasien: 'IDH SUBINGSEN, NY', jaminan: 'BPJS',
    bulan: 'JUNI', tanggal: '29/06/2026',
    edukasi_kamar: 'Ruang Anggrek',
    note: 'Pasien mengerti',
    petugas: 'Reskim',
    keluarga_pasien: 'Siti',
    ttd_keluarga_pasien: '',
    status: 'Selesai',
    quality_control_id: 5,
  },
  {
    id: 3,
    no_mr: '087220', no_reg: 'REG003',
    nama_pasien: 'RUSMINI, NY', jaminan: 'Umum',
    bulan: 'JUNI', tanggal: '28/06/2026',
    edukasi_kamar: '',
    note: '',
    petugas: 'AYU Putri Anisa',
    keluarga_pasien: '',
    ttd_keluarga_pasien: '',
    status: 'Menunggu',
    quality_control_id: 8,
  },
  {
    id: 4,
    no_mr: '816302', no_reg: 'REG004',
    nama_pasien: 'PUSPA SARI, AN', jaminan: 'BPJS',
    bulan: 'MEI', tanggal: '27/05/2026',
    edukasi_kamar: 'ICU',
    note: 'Kondisi kritis',
    petugas: 'Nurul',
    keluarga_pasien: 'Rini',
    ttd_keluarga_pasien: '',
    status: 'Selesai',
    quality_control_id: 11,
  },
])

// ── Auto-populate new entries from QC (2 hour logic) ──────────────────────────
// In production this would call /api/edukasi-lanjutan/pending
// For now we simulate: QC records with "Edukasi lanjutan" status older than 2 hours
const pendingFromQC = ref([])

function checkPendingQC() {
  const twoHoursAgo = Date.now() - (2 * 60 * 60 * 1000)
  const edukasiQCRecords = qcStore.records.filter(r => r.status === 'Edukasi lanjutan')

  const newPending = edukasiQCRecords.filter(qc => {
    const qcTime = new Date(qc.created_at || qc.tanggal).getTime()
    const alreadyIn = mockRecords.value.some(e => e.quality_control_id === qc.id)
    return qcTime <= twoHoursAgo && !alreadyIn
  })

  pendingFromQC.value = newPending
}

// Show banner if pending exists
const showPendingBanner = computed(() => pendingFromQC.value.length > 0)

function autoPopulateFromQC(qc) {
  const bulanNames = ['', 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI',
    'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER']
  const now = new Date()

  mockRecords.value.unshift({
    id: Date.now(),
    no_mr: qc.no_mr,
    no_reg: qc.no_reg,
    nama_pasien: qc.nama_pasien,
    jaminan: qc.jaminan,
    bulan: bulanNames[now.getMonth() + 1],
    tanggal: `${String(now.getDate()).padStart(2,'0')}/${String(now.getMonth()+1).padStart(2,'0')}/${now.getFullYear()}`,
    edukasi_kamar: qc.edukasi_kamar || '',
    note: qc.note || '',
    petugas: qc.petugas || '',
    keluarga_pasien: qc.keluarga_pasien || '',
    ttd_keluarga_pasien: '',
    status: 'Menunggu',
    quality_control_id: qc.id,
  })
  pendingFromQC.value = pendingFromQC.value.filter(p => p.id !== qc.id)
}

// ── Filters ───────────────────────────────────────────────────────────────────
const activeFilter = ref('All')
const searchQuery  = ref('')

const bulanList = computed(() => {
  const months = [...new Set(mockRecords.value.map(r => r.bulan))]
  return ['All', ...months]
})

const bulanCounts = computed(() => {
  const counts = {}
  mockRecords.value.forEach(r => { counts[r.bulan] = (counts[r.bulan] ?? 0) + 1 })
  return counts
})

const filteredRecords = computed(() => {
  let list = mockRecords.value
  if (activeFilter.value !== 'All') list = list.filter(r => r.bulan === activeFilter.value)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(r =>
      r.nama_pasien?.toLowerCase().includes(q) ||
      r.no_mr?.toLowerCase().includes(q) ||
      r.petugas?.toLowerCase().includes(q)
    )
  }
  return list
})

const statCounts = computed(() => ({
  total:    mockRecords.value.length,
  menunggu: mockRecords.value.filter(r => r.status === 'Menunggu').length,
  selesai:  mockRecords.value.filter(r => r.status === 'Selesai').length,
}))

// ── Detail dialog ──────────────────────────────────────────────────────────────
const detailDialog   = ref(false)
const selectedPatient = ref(null)
const dialogMode      = ref('view')

function openDetail(patient) {
  selectedPatient.value = patient
  dialogMode.value = 'view'
  detailDialog.value = true
}

function openEdit(patient) {
  selectedPatient.value = patient
  dialogMode.value = 'edit'
  detailDialog.value = true
}

function onSaved(data) {
  const idx = mockRecords.value.findIndex(r => r.id === data.id)
  if (idx !== -1) mockRecords.value.splice(idx, 1, { ...mockRecords.value[idx], ...data })
  detailDialog.value = false
}

onMounted(() => {
  checkPendingQC()
  // store.fetchRecords()
})
</script>

<template>
  <div class="edukasi-layout d-flex" style="min-height: calc(100dvh - 100px);">
    <!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
    <div class="edukasi-sidebar pa-3 pt-4">
      <!-- Sidebar header -->
      <div class="px-1 mb-4">
        <p class="text-caption font-weight-bold text-uppercase text-medium-emphasis mb-0">Filter Bulan</p>
      </div>

      <div
        v-for="bulan in bulanList"
        :key="bulan"
        class="sidebar-item mb-1"
        :class="{ active: activeFilter === bulan }"
        @click="activeFilter = bulan"
      >
        <div class="d-flex align-center justify-space-between">
          <span class="text-body-2">{{ bulan }}</span>
          <VChip
            v-if="bulan !== 'All'"
            size="x-small"
            :color="activeFilter === bulan ? 'primary' : 'secondary'"
            variant="tonal"
          >
            {{ bulanCounts[bulan] }}
          </VChip>
          <VChip
            v-else
            size="x-small"
            :color="activeFilter === 'All' ? 'primary' : 'secondary'"
            variant="tonal"
          >
            {{ mockRecords.length }}
          </VChip>
        </div>
      </div>
    </div>

    <!-- ── Main content ─────────────────────────────────────────────────────── -->
    <div class="flex-grow-1 pa-4 min-width-0">
      <!-- Header -->
      <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-4">
        <div>
          <h4 class="page-title">Edukasi Lanjutan</h4>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Data otomatis masuk dari QC setelah 2 jam status "Edukasi lanjutan"
          </p>
        </div>
        <VTextField
          v-model="searchQuery"
          placeholder="Cari pasien..."
          prepend-inner-icon="ri-search-line"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          style="max-width: 240px;"
        />
      </div>

      <!-- Auto-populate banner -->
      <VAlert
        v-if="showPendingBanner"
        type="warning"
        variant="tonal"
        class="mb-4"
        density="compact"
        border="start"
      >
        <div class="d-flex align-center justify-space-between flex-wrap gap-2">
          <div>
            <strong>{{ pendingFromQC.length }} pasien baru</strong> dari Quality Control menunggu di-input edukasi lanjutan.
          </div>
          <VBtn size="small" color="warning" variant="elevated" @click="pendingFromQC.forEach(qc => autoPopulateFromQC(qc))">
            <VIcon icon="ri-download-line" class="me-1" />
            Tambahkan Semua
          </VBtn>
        </div>
      </VAlert>

      <!-- Stats row -->
      <VRow dense class="mb-4">
        <VCol cols="4">
          <VCard elevation="0" border rounded="lg" class="text-center pa-3">
            <p class="text-h5 font-weight-bold text-primary mb-0">{{ statCounts.total }}</p>
            <p class="text-caption text-medium-emphasis mb-0">Total</p>
          </VCard>
        </VCol>
        <VCol cols="4">
          <VCard elevation="0" border rounded="lg" class="text-center pa-3">
            <p class="text-h5 font-weight-bold mb-0" style="color: rgb(var(--v-theme-warning))">{{ statCounts.menunggu }}</p>
            <p class="text-caption text-medium-emphasis mb-0">Menunggu</p>
          </VCard>
        </VCol>
        <VCol cols="4">
          <VCard elevation="0" border rounded="lg" class="text-center pa-3">
            <p class="text-h5 font-weight-bold mb-0" style="color: rgb(var(--v-theme-success))">{{ statCounts.selesai }}</p>
            <p class="text-caption text-medium-emphasis mb-0">Selesai</p>
          </VCard>
        </VCol>
      </VRow>

      <!-- Patient cards grid -->
      <VRow v-if="filteredRecords.length" dense>
        <VCol
          v-for="patient in filteredRecords"
          :key="patient.id"
          cols="12" sm="6" md="4" lg="3"
        >
          <EdukasiPatientCard
            :patient="patient"
            @view="openDetail"
            @edit="openEdit"
          />
        </VCol>
      </VRow>

      <!-- Empty state -->
      <div v-else class="text-center py-16 text-medium-emphasis">
        <VIcon icon="ri-book-open-line" size="56" class="mb-3 opacity-40" />
        <p class="text-body-1 font-weight-medium mb-1">Belum ada data edukasi lanjutan</p>
        <p class="text-body-2">
          Data akan otomatis muncul 2 jam setelah entry QC berstatus "Edukasi lanjutan"
        </p>
      </div>
    </div>
  </div>

  <!-- Detail / Edit Dialog -->
  <EdukasiDetailDialog
    v-model="detailDialog"
    :patient="selectedPatient"
    :mode="dialogMode"
    @saved="onSaved"
  />
</template>

<style scoped>
.edukasi-layout {
  margin: -24px;
}

.edukasi-sidebar {
  width: 160px;
  flex-shrink: 0;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

.sidebar-item {
  padding: 8px 10px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.sidebar-item:hover {
  background: rgba(var(--v-theme-primary), 0.06);
}
.sidebar-item.active {
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}

.page-title {
  font-size: 1.1rem;
  font-weight: 700;
  margin-bottom: 2px;
}
</style>
