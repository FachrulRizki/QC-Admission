<script setup>
const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
})
const emit = defineEmits(['edit', 'delete'])

const search = ref('')

// Detail dialog state
const detailDialog = ref(false)
const selectedItem  = ref(null)

function openDetail(item) {
  selectedItem.value = item
  detailDialog.value = true
}

const headers = [
  { title: 'Tanggal',       key: 'tanggal',      sortable: true  },
  { title: 'No. MR',        key: 'no_mr',         sortable: true,  width: '90px' },
  { title: 'Nama Pasien',   key: 'nama_pasien',   sortable: true  },
  { title: 'Jaminan',       key: 'jaminan',       sortable: true,  width: '90px' },
  { title: 'Status',        key: 'status',        sortable: true,  width: '160px' },
  { title: 'Petugas',       key: 'petugas',       sortable: true  },
  { title: 'Durasi Tunggu', key: 'durasi_tunggu', sortable: true,  align: 'center', width: '130px' },
  { title: 'Aksi',          key: 'actions',       sortable: false, align: 'center', width: '90px' },
]

const statusMap = {
  'Edukasi':          { color: 'success', icon: 'ri-book-2-line' },
  'Edukasi lanjutan': { color: 'warning', icon: 'ri-book-open-line' },
  'Masuk':            { color: 'info',    icon: 'ri-hospital-line' },
}
function getStatus(s) { return statusMap[s] ?? { color: 'secondary', icon: 'ri-question-line' } }

function isDurasiLong(d) {
  if (!d) return false
  return d > '02:00:00'
}
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
          <p class="text-caption text-medium-emphasis mb-0">{{ items.length }} record — klik baris untuk detail</p>
        </div>
      </div>
      <VTextField
        v-model="search"
        placeholder="Cari pasien, petugas, NoMR..."
        prepend-inner-icon="ri-search-line"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        style="max-width: 280px;"
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
      <!-- Status -->
      <template #item.status="{ item }">
        <VChip
          :color="getStatus(item.status).color"
          size="small"
          variant="tonal"
          label
        >
          <VIcon :icon="getStatus(item.status).icon" size="13" class="me-1" />
          {{ item.status }}
        </VChip>
      </template>

      <!-- Durasi Tunggu -->
      <template #item.durasi_tunggu="{ item }">
        <VChip
          :color="isDurasiLong(item.durasi_tunggu) ? 'error' : 'default'"
          size="small"
          variant="tonal"
        >
          <VIcon icon="ri-timer-line" size="12" class="me-1" />
          {{ item.durasi_tunggu || '—' }}
        </VChip>
      </template>

      <!-- Actions (stop row-click propagation) -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center" @click.stop>
          <VTooltip text="Edit data">
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

      <!-- Loading -->
      <template #loading>
        <div class="text-center py-8">
          <VProgressCircular indeterminate color="primary" size="36" />
          <p class="text-body-2 text-medium-emphasis mt-3 mb-0">Memuat data...</p>
        </div>
      </template>

      <!-- Empty state -->
      <template #no-data>
        <div class="text-center py-12">
          <VIcon icon="ri-inbox-line" size="52" color="secondary" class="opacity-40 mb-3" />
          <p class="text-body-1 font-weight-medium mb-1">Belum ada data</p>
          <p class="text-body-2 text-medium-emphasis mb-0">Klik <strong>Input QC</strong> untuk menambah data.</p>
        </div>
      </template>
    </VDataTable>
  </VCard>

  <!-- ── Detail Dialog ──────────────────────────────────────────────────────── -->
  <VDialog v-model="detailDialog" max-width="520" scrollable>
    <VCard v-if="selectedItem" rounded="lg">
      <!-- Header -->
      <div class="detail-header px-5 pt-5 pb-3">
        <div class="d-flex align-center gap-3 mb-3">
          <VAvatar
            :color="getStatus(selectedItem.status).color"
            variant="tonal"
            size="48"
            rounded="lg"
          >
            <VIcon :icon="getStatus(selectedItem.status).icon" size="24" />
          </VAvatar>
          <div class="flex-grow-1">
            <p class="text-h6 font-weight-bold mb-0">{{ selectedItem.nama_pasien }}</p>
            <p class="text-caption text-medium-emphasis mb-0">
              No. MR: <strong>{{ selectedItem.no_mr }}</strong>
              · No. Reg: <strong>{{ selectedItem.no_reg }}</strong>
            </p>
          </div>
          <VBtn icon variant="text" size="small" @click="detailDialog = false">
            <VIcon icon="ri-close-line" />
          </VBtn>
        </div>
        <VChip :color="getStatus(selectedItem.status).color" variant="tonal" size="small" label>
          <VIcon :icon="getStatus(selectedItem.status).icon" size="13" class="me-1" />
          {{ selectedItem.status }}
        </VChip>
      </div>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Info grid -->
        <VRow dense class="mb-4">
          <VCol cols="6">
            <div class="info-field">
              <p class="info-label">Tanggal Input</p>
              <p class="info-value">{{ selectedItem.tanggal }}</p>
            </div>
          </VCol>
          <VCol cols="6">
            <div class="info-field">
              <p class="info-label">Jaminan</p>
              <p class="info-value">{{ selectedItem.jaminan || '—' }}</p>
            </div>
          </VCol>
          <VCol cols="6">
            <div class="info-field">
              <p class="info-label">Edukasi Kamar</p>
              <p class="info-value">{{ selectedItem.edukasi_kamar || '—' }}</p>
            </div>
          </VCol>
          <VCol cols="6">
            <div class="info-field">
              <p class="info-label">Durasi Tunggu</p>
              <p class="info-value">
                <VChip
                  :color="isDurasiLong(selectedItem.durasi_tunggu) ? 'error' : 'success'"
                  size="x-small"
                  variant="tonal"
                >
                  <VIcon icon="ri-timer-line" size="11" class="me-1" />
                  {{ selectedItem.durasi_tunggu || '—' }}
                </VChip>
              </p>
            </div>
          </VCol>
          <VCol cols="6">
            <div class="info-field">
              <p class="info-label">Note</p>
              <p class="info-value">{{ selectedItem.note || '—' }}</p>
            </div>
          </VCol>
          <VCol cols="6">
            <div class="info-field">
              <p class="info-label">Petugas</p>
              <p class="info-value d-flex align-center gap-1">
                <VIcon icon="ri-user-3-line" size="14" />
                {{ selectedItem.petugas || '—' }}
              </p>
            </div>
          </VCol>
          <VCol cols="6">
            <div class="info-field">
              <p class="info-label">Status Keterangan</p>
              <p class="info-value">{{ selectedItem.status_ket || '—' }}</p>
            </div>
          </VCol>
          <VCol cols="6">
            <div class="info-field">
              <p class="info-label">Keluarga Pasien</p>
              <p class="info-value">{{ selectedItem.keluarga_pasien || '—' }}</p>
            </div>
          </VCol>
        </VRow>

        <!-- TTD Keluarga Pasien -->
        <div class="ttd-section pa-3 rounded-lg">
          <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">
            <VIcon icon="ri-pen-nib-line" size="14" class="me-1" />
            Tanda Tangan Keluarga Pasien
          </p>
          <div v-if="selectedItem.ttd_keluarga_pasien" class="ttd-preview rounded-lg overflow-hidden">
            <img
              :src="selectedItem.ttd_keluarga_pasien"
              alt="TTD Keluarga"
              style="width:100%; max-height:120px; object-fit:contain; background:#fff;"
            />
          </div>
          <div v-else class="ttd-empty text-center py-4 text-medium-emphasis">
            <VIcon icon="ri-pen-nib-line" size="28" class="mb-1" />
            <p class="text-caption mb-0">Belum ada tanda tangan</p>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" class="flex-grow-1" @click="detailDialog = false">Tutup</VBtn>
        <VBtn
          color="primary"
          class="flex-grow-1"
          prepend-icon="ri-pencil-line"
          @click="emit('edit', selectedItem); detailDialog = false"
        >
          Edit
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.qc-table :deep(.v-data-table__thead th) {
  background: rgb(var(--v-theme-table-header-color)) !important;
  font-weight: 700 !important;
  font-size: 0.72rem !important;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  white-space: nowrap;
  color: rgba(var(--v-theme-on-surface), 0.75) !important;
}

.qc-table :deep(tr) { cursor: pointer; }
.qc-table :deep(tr:hover td) { background: rgba(var(--v-theme-primary), 0.03); }

.detail-header { background: rgba(var(--v-theme-primary), 0.03); }

.info-field { padding: 6px 0; }
.info-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  margin-bottom: 2px;
}
.info-value { font-size: 0.875rem; font-weight: 500; margin-bottom: 0; }

.ttd-section { background: rgba(var(--v-theme-on-surface), 0.03); }
.ttd-preview { border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.ttd-empty {
  border: 1.5px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
</style>
