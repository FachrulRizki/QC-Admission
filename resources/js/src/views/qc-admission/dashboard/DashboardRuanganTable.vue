<script setup>
const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
})

const headers = [
  { title: 'Ruangan',    key: 'ruangan',        sortable: true  },
  { title: 'Standar',   key: 'kamar_standar',   sortable: true, align: 'center' },
  { title: 'Kelas 1',   key: 'kelas_1',         sortable: true, align: 'center' },
  { title: 'Kelas 2',   key: 'kelas_2',         sortable: true, align: 'center' },
  { title: 'Kelas 3',   key: 'kelas_3',         sortable: true, align: 'center' },
  { title: 'Total',     key: 'jumlah',          sortable: true, align: 'center' },
]

const total = computed(() => ({
  kamar_standar: props.items.reduce((s, r) => s + (r.kamar_standar ?? 0), 0),
  kelas_1:       props.items.reduce((s, r) => s + (r.kelas_1 ?? 0), 0),
  kelas_2:       props.items.reduce((s, r) => s + (r.kelas_2 ?? 0), 0),
  kelas_3:       props.items.reduce((s, r) => s + (r.kelas_3 ?? 0), 0),
  jumlah:        props.items.reduce((s, r) => s + (r.jumlah  ?? 0), 0),
}))
</script>

<template>
  <VCard elevation="0" border rounded="lg" class="h-100">
    <div class="d-flex align-center gap-2 px-5 py-4">
      <VAvatar color="warning" variant="tonal" size="36" rounded="lg">
        <VIcon icon="ri-hospital-line" size="18" />
      </VAvatar>
      <div>
        <p class="text-subtitle-2 font-weight-bold mb-0">Distribusi per Ruangan</p>
        <p class="text-caption text-medium-emphasis mb-0">Pasien masuk per kelas kamar</p>
      </div>
    </div>
    <VDivider />

    <VDataTable
      :headers="headers"
      :items="items"
      :loading="loading"
      density="compact"
      hover
      hide-default-footer
      :items-per-page="-1"
    >
      <template #item.ruangan="{ item }">
        <span class="text-body-2 font-weight-medium">{{ item.ruangan }}</span>
      </template>

      <template #item.jumlah="{ item }">
        <VChip size="small" color="primary" variant="elevated" class="font-weight-bold">
          {{ item.jumlah }}
        </VChip>
      </template>

      <!-- totals row -->
      <template #body.append>
        <tr class="totals-row">
          <td class="text-caption font-weight-bold px-4 py-2">TOTAL</td>
          <td class="text-center"><strong>{{ total.kamar_standar }}</strong></td>
          <td class="text-center"><strong>{{ total.kelas_1 }}</strong></td>
          <td class="text-center"><strong>{{ total.kelas_2 }}</strong></td>
          <td class="text-center"><strong>{{ total.kelas_3 }}</strong></td>
          <td class="text-center">
            <VChip size="small" color="primary" variant="elevated" class="font-weight-bold">{{ total.jumlah }}</VChip>
          </td>
        </tr>
      </template>

      <template #no-data>
        <div class="text-center py-8 text-medium-emphasis">
          <VIcon icon="ri-hospital-line" size="36" class="mb-2 opacity-40" />
          <p class="mb-0">Belum ada data ruangan</p>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>

<style scoped>
.totals-row { background: rgba(var(--v-theme-primary), 0.04); }
.totals-row td { border-top: 2px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
</style>
