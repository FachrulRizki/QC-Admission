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
  { title: 'Pelayanan / Ruangan', key: 'ruangan', sortable: true },
  { title: 'Kamar / Standar', key: 'kamar_standar', sortable: true, align: 'center' },
  { title: 'Kelas 1', key: 'kelas_1', sortable: true, align: 'center' },
  { title: 'Kelas 2', key: 'kelas_2', sortable: true, align: 'center' },
  { title: 'Kelas 3', key: 'kelas_3', sortable: true, align: 'center' },
  { title: 'Jml', key: 'jumlah', sortable: true, align: 'center' },
]
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="ri-building-line" color="info" />
        Jumlah Pasien Berdasarkan Ruangan
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
      <template #item.jumlah="{ item }">
        <VChip size="small" color="info" variant="tonal">
          {{ item.jumlah }}
        </VChip>
      </template>
      <template #no-data>
        <div class="text-center py-6 text-medium-emphasis">
          <VIcon icon="ri-database-2-line" size="32" class="mb-2" />
          <p class="mb-0">Belum ada data ruangan</p>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>
