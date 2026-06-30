<script setup>
defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const headers = [
  { title: 'Tanggal & Jam', key: 'tanggal', sortable: true, width: '160px' },
  { title: 'NoMR', key: 'no_mr', sortable: true, width: '100px' },
  { title: 'Status', key: 'status', sortable: true, width: '130px' },
  { title: 'Jam Input', key: 'jam_input', sortable: true, width: '100px' },
  { title: 'Petugas', key: 'petugas', sortable: true },
  { title: 'Note', key: 'note', sortable: false },
  { title: 'Edukasi Kamar', key: 'edukasi_kamar', sortable: false },
  { title: 'Durasi Tunggu Edukasi (Menit)', key: 'durasi_tunggu', sortable: true, width: '100px', align: 'center' },
]

function statusColor(status) {
  const map = {
    'Edukasi': 'success',
    'Edukasi lanjutan': 'warning',
    'Masuk': 'info',
  }
  return map[status] ?? 'secondary'
}
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="ri-clipboard-line" color="success" />
        Data Entry Quality Control Admission
      </VCardTitle>
    </VCardItem>
    <VDivider />
    <VDataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      density="compact"
      hover
      :items-per-page="10"
    >
      <template #item.status="{ item }">
        <VChip
          :color="statusColor(item.status)"
          size="small"
          variant="tonal"
          label
        >
          {{ item.status }}
        </VChip>
      </template>
      <template #no-data>
        <div class="text-center py-6 text-medium-emphasis">
          <VIcon icon="ri-clipboard-line" size="32" class="mb-2" />
          <p class="mb-0">Belum ada data QC terbaru</p>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>
