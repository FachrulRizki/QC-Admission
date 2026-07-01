<script setup>
const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
})
const emit = defineEmits(['edit', 'delete'])

const search = ref('')

// ── Detail dialog ─────────────────────────────────────────────────────────────
const detailDialog = ref(false)
const selectedItem  = ref(null)
function openDetail(item) { selectedItem.value = item; detailDialog.value = true }

// ── Live countdown per row ────────────────────────────────────────────────────
// Hitung sisa waktu menuju 2 jam sejak created_at
const now = ref(Date.now())
let tickTimer = null
onMounted(() => { tickTimer = setInterval(() => { now.value = Date.now() }, 1000) })
onUnmounted(() => clearInterval(tickTimer))

function getElapsedSeconds(item) {
  if (!item.created_at) return 0
  return Math.floor((now.value - new Date(item.created_at).getTime()) / 1000)
}

function getCountdown(item) {
  const elapsed = getElapsedSeconds(item)
  const remaining = Math.max(0, 7200 - elapsed)
  if (remaining === 0) return null // sudah lewat
  const h = String(Math.floor(remaining / 3600)).padStart(2, '0')
  const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0')
  const s = String(remaining % 60).padStart(2, '0')
  return `${h}:${m}:${s}`
}

function isAlreadyLanjutan(item) {
  // Sudah ada di Edukasi Lanjutan (ditandai dari backend — field has_edukasi_lanjutan)
  // atau elapsed >= 2 jam
  return item.has_edukasi_lanjutan || getElapsedSeconds(item) >= 7200
}

const headers = [
  { title: 'Tanggal',     key: 'tanggal',      sortable: true },
  { title: 'No. MR',      key: 'no_mr',         sortable: true, width: '90px' },
  { title: 'Nama Pasien', key: 'nama_pasien',   sortable: true },
  { title: 'Jaminan',     key: 'jaminan',       sortable: true, width: '90px' },
  { title: 'Petugas',     key: 'petugas',       sortable: true },
  { title: 'Status Edu.',  key: 'edukasi_status', sortable: false, align: 'center', width: '180px' },
  { title: 'Aksi',        key: 'actions',       sortable: false, align: 'center', width: '90px' },
]
</script>

<template>
  <VCard elevation="0" border rounded="lg">
    <!-- Toolbar -->
    <div class="d-flex align-center justify-space-between px-4 py-3 flex-wrap gap-3">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="36" rounded="lg">
          <VIcon icon="ri-shield-check-line" size="18" />
        </VAvatar>
        <div>
          <p class="text-subtitle-2 font-weight-bold mb-0">Data Quality Control</p>
          <p class="text-caption text-medium-emphasis mb-0">
            {{ items.length }} record · klik baris untuk detail · auto pindah ke Edukasi Lanjutan setelah 2 jam
          </p>
        </div>
      </div>
      <VTextField
        v-model="search"
        placeholder="Cari pasien, petugas, No MR..."
        prepend-inner-icon="ri-search-line"
        variant="outlined" density="compact"
        hide-details clearable
        style="max-width:260px"
      />
    </div>
    <VDivider />

    <VDataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      :search="search"
      density="comfortable"
      hover
      :items-per-page="10"
      class="qc-table"
      @click:row="(_, { item }) => openDetail(item)"
    >

      <!-- Nama pasien with avatar -->
      <template #item.nama_pasien="{ item }">
        <div class="d-flex align-center gap-2 py-1">
          <VAvatar color="primary" variant="tonal" size="28" rounded="md">
            <span style="font-size:11px;font-weight:700">{{ item.nama_pasien?.charAt(0) ?? '?' }}</span>
          </VAvatar>
          <span class="text-body-2 font-weight-medium">{{ item.nama_pasien }}</span>
        </div>
      </template>

      <!-- Status Edukasi + countdown -->
      <template #item.edukasi_status="{ item }">
        <div class="d-flex flex-column align-center gap-1 py-1">
          <template v-if="isAlreadyLanjutan(item)">
            <VChip color="warning" size="small" variant="tonal" prepend-icon="ri-arrow-right-circle-line">
              Edukasi Lanjutan
            </VChip>
          </template>
          <template v-else>
            <VChip color="success" size="small" variant="tonal" prepend-icon="ri-book-line">
              Edukasi
            </VChip>
            <!-- Countdown ke Edukasi Lanjutan -->
            <div v-if="getCountdown(item)" class="d-flex align-center gap-1">
              <VIcon icon="ri-timer-line" size="11" color="secondary" />
              <span class="text-caption text-disabled font-mono">{{ getCountdown(item) }}</span>
              <span class="text-caption text-disabled">lagi</span>
            </div>
          </template>
        </div>
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center" @click.stop>
          <VTooltip text="Edit">
            <template #activator="{ props: tp }">
              <VBtn v-bind="tp" icon size="x-small" variant="text" color="primary" @click="emit('edit', item)">
                <VIcon icon="ri-pencil-line" size="15" />
              </VBtn>
            </template>
          </VTooltip>
          <VTooltip text="Hapus">
            <template #activator="{ props: tp }">
              <VBtn v-bind="tp" icon size="x-small" variant="text" color="error" @click="emit('delete', item)">
                <VIcon icon="ri-delete-bin-line" size="15" />
              </VBtn>
            </template>
          </VTooltip>
        </div>
      </template>

      <template #loading>
        <div class="text-center py-8">
          <VProgressCircular indeterminate color="primary" size="32" />
          <p class="text-body-2 text-medium-emphasis mt-2 mb-0">Memuat data...</p>
        </div>
      </template>

      <template #no-data>
        <div class="text-center py-12">
          <VIcon icon="ri-inbox-line" size="48" color="secondary" class="opacity-40 mb-3" />
          <p class="text-body-1 font-weight-medium mb-1">Belum ada data QC</p>
          <p class="text-body-2 text-medium-emphasis mb-0">Klik <strong>Input QC</strong> untuk menambah.</p>
        </div>
      </template>

    </VDataTable>
  </VCard>

  <!-- ── Detail Dialog ──────────────────────────────────────────────────────── -->
  <VDialog v-model="detailDialog" max-width="500" scrollable>
    <VCard v-if="selectedItem" rounded="xl">
      <div class="detail-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="primary" variant="tonal" size="46" rounded="lg">
          <span style="font-size:16px;font-weight:700">{{ selectedItem.nama_pasien?.charAt(0) ?? '?' }}</span>
        </VAvatar>
        <div class="flex-grow-1 min-width-0">
          <p class="text-h6 font-weight-bold mb-0 text-truncate">{{ selectedItem.nama_pasien }}</p>
          <p class="text-caption text-medium-emphasis mb-0">
            No. MR: <strong>{{ selectedItem.no_mr }}</strong> · No. Reg: <strong>{{ selectedItem.no_reg }}</strong>
          </p>
        </div>
        <VBtn icon variant="text" size="small" @click="detailDialog = false">
          <VIcon icon="ri-close-line" />
        </VBtn>
      </div>

      <VDivider />

      <!-- Status edukasi + countdown -->
      <div class="px-5 py-3 d-flex align-center gap-3 flex-wrap" style="background:rgba(var(--v-theme-on-surface),0.02)">
        <template v-if="isAlreadyLanjutan(selectedItem)">
          <VChip color="warning" variant="tonal" size="small" prepend-icon="ri-arrow-right-circle-line">
            Sudah masuk Edukasi Lanjutan
          </VChip>
        </template>
        <template v-else>
          <VChip color="success" variant="tonal" size="small" prepend-icon="ri-book-line">Edukasi</VChip>
          <div v-if="getCountdown(selectedItem)" class="d-flex align-center gap-1">
            <VIcon icon="ri-timer-flash-line" size="14" color="warning" />
            <span class="text-caption font-weight-semibold text-warning font-mono">{{ getCountdown(selectedItem) }}</span>
            <span class="text-caption text-medium-emphasis">lagi masuk Edukasi Lanjutan</span>
          </div>
        </template>
      </div>

      <VDivider />

      <VCardText class="pa-5">
        <VRow dense>
          <VCol cols="6">
            <p class="info-label">Tanggal Input</p>
            <p class="info-value">{{ selectedItem.tanggal }}</p>
          </VCol>
          <VCol cols="6">
            <p class="info-label">Jam Input</p>
            <p class="info-value">{{ selectedItem.jam_input }}</p>
          </VCol>
          <VCol cols="6">
            <p class="info-label">Jaminan</p>
            <p class="info-value">{{ selectedItem.jaminan || '—' }}</p>
          </VCol>
          <VCol cols="6">
            <p class="info-label">Tgl. Daftar Pasien</p>
            <p class="info-value">{{ selectedItem.tgl_daftar || '—' }}</p>
          </VCol>
          <VCol cols="12">
            <p class="info-label">Edukasi Kamar</p>
            <p class="info-value">{{ selectedItem.edukasi_kamar || '—' }}</p>
          </VCol>
          <VCol cols="6">
            <p class="info-label">Note / Kamar</p>
            <p class="info-value">{{ selectedItem.note || '—' }}</p>
          </VCol>
          <VCol cols="6">
            <p class="info-label">Petugas</p>
            <p class="info-value d-flex align-center gap-1">
              <VIcon icon="ri-user-3-line" size="14" />{{ selectedItem.petugas || '—' }}
            </p>
          </VCol>
          <VCol cols="12">
            <p class="info-label">Status Pasien</p>
            <p class="info-value">{{ selectedItem.status_ket || '—' }}</p>
          </VCol>
          <VCol cols="6">
            <p class="info-label">Keluarga Pasien</p>
            <p class="info-value">{{ selectedItem.keluarga_pasien || '—' }}</p>
          </VCol>
        </VRow>

        <!-- TTD -->
        <div class="mt-3 pa-3 rounded-lg" style="background:rgba(var(--v-theme-on-surface),0.03);border:1px dashed rgba(var(--v-border-color),var(--v-border-opacity))">
          <p class="info-label mb-2">Tanda Tangan Keluarga</p>
          <img v-if="selectedItem.ttd_keluarga_pasien" :src="selectedItem.ttd_keluarga_pasien"
            alt="TTD" style="max-height:100px;width:100%;object-fit:contain;background:#fff;border-radius:6px" />
          <p v-else class="text-caption text-medium-emphasis text-center py-2 mb-0">Belum ada tanda tangan</p>
        </div>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" class="flex-grow-1" @click="detailDialog = false">Tutup</VBtn>
        <VBtn color="primary" class="flex-grow-1" prepend-icon="ri-pencil-line"
          @click="emit('edit', selectedItem); detailDialog = false">
          Edit
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.qc-table :deep(tr) { cursor: pointer; }
.qc-table :deep(tr:hover td) { background: rgba(var(--v-theme-primary), 0.025); }
.qc-table :deep(.v-data-table__thead th) {
  font-weight: 700 !important;
  font-size: 0.72rem !important;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: rgba(var(--v-theme-on-surface), 0.025) !important;
}
.detail-header { background: rgba(var(--v-theme-primary), 0.04); }
.info-label {
  font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em;
  color: rgba(var(--v-theme-on-surface), 0.45); margin-bottom: 2px;
}
.info-value { font-size: 0.875rem; font-weight: 500; margin-bottom: 8px; }
.font-mono { font-family: 'JetBrains Mono', 'Fira Code', monospace !important; }
</style>
