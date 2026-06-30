<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import QCFormDialog from '@/views/qc-admission/quality-control/QCFormDialog.vue'
import QCDataTable  from '@/views/qc-admission/quality-control/QCDataTable.vue'

const store = useQualityControlStore()

// ── state ──────────────────────────────────────────────────────────────────────
const showDialog        = ref(false)
const editItem          = ref(null)
const showDeleteConfirm = ref(false)
const deleteTarget      = ref(null)

// ── mock data ──────────────────────────────────────────────────────────────────
const mockRecords = ref([
  {
    id: 1, tanggal: '29/06/2026, 19.41.58', jam_input: '19.41.58',
    no_mr: '813500', no_reg: 'REG001', nama_pasien: 'ELLY MAYA, NY',
    jaminan: 'BPJS', status_ket: 'Belum Dapat Kamar', edukasi_kamar: '',
    durasi_tunggu: '10:17:19', note: 'Pasien mengerti', petugas: 'Nurul',
    status: 'Edukasi', keluarga_pasien: 'Bambang', ttd_keluarga_pasien: '',
  },
  {
    id: 2, tanggal: '29/06/2026, 18.05.22', jam_input: '18.05.22',
    no_mr: '575360', no_reg: 'REG002', nama_pasien: 'IDH SUBINGSEN, NY',
    jaminan: 'BPJS', status_ket: 'Antri Kamar', edukasi_kamar: 'Ruang Mawar',
    durasi_tunggu: '01:32:10', note: 'Keluarga hadir', petugas: 'Reskim',
    status: 'Edukasi lanjutan', keluarga_pasien: 'Siti', ttd_keluarga_pasien: '',
  },
  {
    id: 3, tanggal: '29/06/2026, 17.44.00', jam_input: '17.44.00',
    no_mr: '087220', no_reg: 'REG003', nama_pasien: 'RUSMINI, NY',
    jaminan: 'Umum', status_ket: 'Sudah Dapat Kamar', edukasi_kamar: 'Ruang Anggrek',
    durasi_tunggu: '00:48:22', note: 'Dirujuk', petugas: 'Nurul',
    status: 'Masuk', keluarga_pasien: 'Andi', ttd_keluarga_pasien: '',
  },
  {
    id: 4, tanggal: '28/06/2026, 14.10.00', jam_input: '14.10.00',
    no_mr: '816302', no_reg: 'REG004', nama_pasien: 'PUSPA SARI, AN',
    jaminan: 'BPJS', status_ket: 'Antri Kamar', edukasi_kamar: 'ICU',
    durasi_tunggu: '03:20:10', note: 'Keluarga hadir', petugas: 'AYU Putri Anisa',
    status: 'Edukasi lanjutan', keluarga_pasien: 'Rini', ttd_keluarga_pasien: '',
  },
])

// ── stats ──────────────────────────────────────────────────────────────────────
const stats = computed(() => ({
  total:           mockRecords.value.length,
  edukasi:         mockRecords.value.filter(r => r.status === 'Edukasi').length,
  edukasiLanjutan: mockRecords.value.filter(r => r.status === 'Edukasi lanjutan').length,
  masuk:           mockRecords.value.filter(r => r.status === 'Masuk').length,
}))

const statCards = computed(() => [
  { label: 'Total Hari Ini', value: stats.value.total,           color: 'primary', icon: 'ri-shield-check-line' },
  { label: 'Edukasi',        value: stats.value.edukasi,         color: 'success', icon: 'ri-book-2-line' },
  { label: 'Edukasi Lanjutan', value: stats.value.edukasiLanjutan, color: 'warning', icon: 'ri-book-open-line' },
  { label: 'Masuk',          value: stats.value.masuk,           color: 'info',    icon: 'ri-hospital-line' },
])

// ── handlers ──────────────────────────────────────────────────────────────────
function openAdd()         { editItem.value = null;        showDialog.value = true }
function openEdit(item)    { editItem.value = { ...item }; showDialog.value = true }
function openDelete(item)  { deleteTarget.value = item;    showDeleteConfirm.value = true }

function confirmDelete() {
  mockRecords.value = mockRecords.value.filter(r => r.id !== deleteTarget.value.id)
  showDeleteConfirm.value = false
  deleteTarget.value = null
}

function onSaved(data) {
  if (editItem.value) {
    const idx = mockRecords.value.findIndex(r => r.id === editItem.value.id)
    if (idx !== -1) mockRecords.value.splice(idx, 1, { ...editItem.value, ...data })
  } else {
    mockRecords.value.unshift({ id: Date.now(), ...data })
  }
  showDialog.value = false
}

onMounted(() => { /* store.fetchRecords() */ })
</script>

<template>
  <div>
    <!-- ── Page header ──────────────────────────────────────────────────────── -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-5">
      <div>
        <h4 class="page-title">Quality Control</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Manajemen data quality control admisi rawat inap</p>
      </div>
      <VBtn color="primary" rounded="lg" prepend-icon="ri-add-line" @click="openAdd">
        Input QC
      </VBtn>
    </div>

    <!-- ── Stat cards ──────────────────────────────────────────────────────── -->
    <VRow dense class="mb-5">
      <VCol v-for="card in statCards" :key="card.label" cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="stat-card pa-4">
          <div class="d-flex align-center gap-3">
            <VAvatar :color="card.color" variant="tonal" size="44" rounded="lg">
              <VIcon :icon="card.icon" size="22" />
            </VAvatar>
            <div>
              <p class="text-h5 font-weight-bold mb-0" :style="`color: rgb(var(--v-theme-${card.color}))`">
                {{ card.value }}
              </p>
              <p class="text-caption text-medium-emphasis mb-0">{{ card.label }}</p>
            </div>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- ── Data table ──────────────────────────────────────────────────────── -->
    <QCDataTable
      :items="mockRecords"
      :loading="store.loading"
      @edit="openEdit"
      @delete="openDelete"
    />

    <!-- ── Form dialog ─────────────────────────────────────────────────────── -->
    <QCFormDialog
      v-model="showDialog"
      :edit-item="editItem"
      @saved="onSaved"
    />

    <!-- ── Delete confirm ─────────────────────────────────────────────────── -->
    <VDialog v-model="showDeleteConfirm" max-width="380">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="60" rounded="xl" class="mb-4">
            <VIcon icon="ri-delete-bin-2-line" size="30" />
          </VAvatar>
          <h6 class="text-h6 font-weight-bold mb-2">Hapus Data?</h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Data <strong>{{ deleteTarget?.nama_pasien }}</strong>
            (No. MR: <strong>{{ deleteTarget?.no_mr }}</strong>) akan dihapus permanen.
          </p>
        </VCardText>
        <VCardActions class="px-6 pb-5 d-flex gap-2 pt-0">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDeleteConfirm = false">
            Batal
          </VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" prepend-icon="ri-delete-bin-line" @click="confirmDelete">
            Hapus
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.page-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 2px; }
.stat-card { transition: box-shadow 0.2s; }
.stat-card:hover { box-shadow: 0 4px 16px rgba(var(--v-shadow-key-umbra-color), 0.1) !important; }
</style>
