<script setup>
defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})
const emit = defineEmits(['verif', 'delete'])

const search = ref('')
const headers = [
  { title: 'Tanggal', key: 'tanggal', sortable: true },
  { title: 'NoReg', key: 'no_reg', sortable: true },
  { title: 'Keterangan Batal', key: 'keterangan_batal', sortable: true },
  { title: 'Status OK', key: 'status_ok', sortable: true, align: 'center' },
  { title: 'Petugas', key: 'petugas', sortable: true },
  { title: 'Aksi', key: 'actions', sortable: false, align: 'center', width: '90px' },
]

function statusColor(status) {
  return { OK: 'success', Pending: 'warning', Ditolak: 'error' }[status] ?? 'secondary'
}
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="ri-checkbox-circle-line" color="warning" />
        Verifikasi Ps Batal Ranap
      </VCardTitle>
      <template #append>
        <VTextField
          v-model="search"
          placeholder="Cari..."
          prepend-inner-icon="ri-search-line"
          variant="outlined"
          density="compact"
          hide-details
          style="min-width: 200px;"
        />
      </template>
    </VCardItem>
    <VDivider />
    <VDataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      :search="search"
      density="compact"
      hover
    >
      <template #item.status_ok="{ item }">
        <VChip :color="statusColor(item.status_ok)" size="small" variant="tonal" label>
          {{ item.status_ok ?? 'Pending' }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center">
          <VBtn icon size="x-small" variant="text" color="success" title="Verifikasi" @click="emit('verif', item)">
            <VIcon icon="ri-check-double-line" />
          </VBtn>
          <VBtn icon size="x-small" variant="text" color="error" title="Hapus" @click="emit('delete', item)">
            <VIcon icon="ri-delete-bin-line" />
          </VBtn>
        </div>
      </template>
      <template #no-data>
        <div class="text-center py-8 text-medium-emphasis">
          <VIcon icon="ri-close-circle-line" size="36" class="mb-2" />
          <p class="mb-0">Belum ada data batal ranap</p>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>
