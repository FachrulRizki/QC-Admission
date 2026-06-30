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
  { title: 'No', key: 'no', width: '60px', sortable: false },
  { title: 'Petugas', key: 'petugas', sortable: true },
  { title: 'Non-Prioritas', key: 'non_prioritas', sortable: true, align: 'center' },
  { title: 'Edukasi', key: 'edukasi', sortable: true, align: 'center' },
  { title: 'Edukasi Lanjutan', key: 'edukasi_lanjutan', sortable: true, align: 'center' },
  { title: 'Masuk', key: 'masuk', sortable: true, align: 'center' },
  { title: 'Total', key: 'total', sortable: true, align: 'center' },
]
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="ri-team-line" color="primary" />
        Jumlah Pasien Berdasarkan Petugas
      </VCardTitle>
    </VCardItem>
    <VDivider />
    <VDataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      density="compact"
      hover
    >
      <template #item.no="{ index }">
        {{ index + 1 }}
      </template>
      <template #item.total="{ item }">
        <VChip
          size="small"
          color="primary"
          variant="tonal"
        >
          {{ item.total }}
        </VChip>
      </template>
      <template #no-data>
        <div class="text-center py-6 text-medium-emphasis">
          <VIcon icon="ri-database-2-line" size="32" class="mb-2" />
          <p class="mb-0">Belum ada data petugas</p>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>
