<script setup>
const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
})
const emit = defineEmits(['edit', 'delete'])

const search = ref('')

const headers = [
  { title: 'Tanggal',       key: 'tanggal',       sortable: true  },
  { title: 'No. MR',        key: 'no_mr',          sortable: true,  width: '90px' },
  { title: 'Nama Pasien',   key: 'nama_pasien',    sortable: true  },
  { title: 'Jaminan',       key: 'jaminan',        sortable: true,  width: '90px' },
  { title: 'Status',        key: 'status',         sortable: true,  width: '150px' },
  { title: 'Petugas',       key: 'petugas',        sortable: true  },
  { title: 'Durasi Tunggu', key: 'durasi_tunggu',  sortable: true,  align: 'center', width: '130px' },
  { title: 'Aksi',          key: 'actions',        sortable: false, align: 'center', width: '80px' },
]

const statusMap = {
  'Edukasi':         { color: 'success', icon: 'ri-book-2-line' },
  'Edukasi lanjutan':{ color: 'warning', icon: 'ri-book-open-line' },
  'Masuk':           { color: 'info',    icon: 'ri-hospital-line' },
}
function getStatus(s) { return statusMap[s] ?? { color: 'secondary', icon: 'ri-question-line' } }
</script>

<template>
  <VCard elevation="0" border>
    <!-- Table toolbar -->
    <div class="d-flex align-center justify-space-between px-4 py-3 flex-wrap gap-3">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="36" rounded="lg">
          <VIcon icon="ri-shield-check-line" size="18" />
        </VAvatar>
        <div>
          <p class="text-subtitle-2 font-weight-bold mb-0">Data Quality Control</p>
          <p class="text-caption text-medium-emphasis mb-0">{{ items.length }} record</p>
        </div>
      </div>
      <VTextField
        v-model="search"
        placeholder="Cari pasien, petugas..."
        prepend-inner-icon="ri-search-line"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        style="max-width: 260px;"
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
    >
      <!-- Status chip with icon -->
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

      <!-- Durasi Tunggu — highlight long waits -->
      <template #item.durasi_tunggu="{ item }">
        <VChip
          :color="item.durasi_tunggu > '02:00:00' ? 'error' : 'default'"
          size="small"
          variant="tonal"
          prepend-icon="ri-timer-line"
        >
          {{ item.durasi_tunggu || '—' }}
        </VChip>
      </template>

      <!-- Actions -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center">
          <VTooltip text="Edit data">
            <template #activator="{ props: tp }">
              <VBtn v-bind="tp" icon size="x-small" variant="text" color="primary" @click="emit('edit', item)">
                <VIcon icon="ri-pencil-line" size="15" />
              </VBtn>
            </template>
          </VTooltip>
          <VTooltip text="Hapus data">
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
          <p class="text-body-2 text-medium-emphasis mb-0">
            Klik <strong>Input QC</strong> untuk menambah data Quality Control.
          </p>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>

<style scoped>
.qc-table :deep(.v-data-table__thead th) {
  background: rgba(var(--v-theme-primary), 0.04) !important;
  font-weight: 700 !important;
  font-size: 0.72rem !important;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  white-space: nowrap;
  color: rgba(var(--v-theme-on-surface), 0.75) !important;
}
</style>
