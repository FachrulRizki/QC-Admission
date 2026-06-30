<script setup>
const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
})

const search = ref('')

const headers = [
  { title: '#',                key: 'no',               width: '48px', sortable: false },
  { title: 'Petugas',         key: 'petugas',           sortable: true },
  { title: 'Edukasi',         key: 'edukasi',           sortable: true, align: 'center' },
  { title: 'Edukasi Lanjutan',key: 'edukasi_lanjutan',  sortable: true, align: 'center' },
  { title: 'Non-Prioritas',   key: 'non_prioritas',     sortable: true, align: 'center' },
  { title: 'Total',           key: 'total',             sortable: true, align: 'center' },
]

const filtered = computed(() => {
  if (!search.value.trim()) return props.items
  const q = search.value.toLowerCase()
  return props.items.filter(r => r.petugas?.toLowerCase().includes(q))
})

// Totals row
const totals = computed(() => ({
  edukasi:          filtered.value.reduce((s, r) => s + (r.edukasi ?? 0), 0),
  edukasi_lanjutan: filtered.value.reduce((s, r) => s + (r.edukasi_lanjutan ?? 0), 0),
  non_prioritas:    filtered.value.reduce((s, r) => s + (r.non_prioritas ?? 0), 0),
  total:            filtered.value.reduce((s, r) => s + (r.total ?? 0), 0),
}))
</script>

<template>
  <VCard elevation="0" border rounded="lg">
    <div class="d-flex align-center justify-space-between px-5 py-4 flex-wrap gap-3">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="36" rounded="lg">
          <VIcon icon="ri-team-line" size="18" />
        </VAvatar>
        <div>
          <p class="text-subtitle-2 font-weight-bold mb-0">Kinerja Petugas</p>
          <p class="text-caption text-medium-emphasis mb-0">Pasien per petugas hari ini</p>
        </div>
      </div>
      <VTextField
        v-model="search"
        placeholder="Cari petugas..."
        prepend-inner-icon="ri-search-line"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        style="max-width:180px"
      />
    </div>
    <VDivider />

    <VDataTable
      :headers="headers"
      :items="filtered"
      :loading="loading"
      density="compact"
      hover
      hide-default-footer
      :items-per-page="-1"
    >
      <template #item.no="{ index }">
        <span class="text-caption text-disabled">{{ index + 1 }}</span>
      </template>

      <template #item.petugas="{ item }">
        <div class="d-flex align-center gap-2 py-1">
          <VAvatar color="primary" variant="tonal" size="28" rounded="lg">
            <span class="text-caption font-weight-bold">{{ item.petugas?.charAt(0) }}</span>
          </VAvatar>
          <span class="text-body-2 font-weight-medium">{{ item.petugas }}</span>
        </div>
      </template>

      <template #item.edukasi="{ item }">
        <VChip v-if="item.edukasi" size="small" color="success" variant="tonal">{{ item.edukasi }}</VChip>
        <span v-else class="text-disabled">—</span>
      </template>

      <template #item.edukasi_lanjutan="{ item }">
        <VChip v-if="item.edukasi_lanjutan" size="small" color="warning" variant="tonal">{{ item.edukasi_lanjutan }}</VChip>
        <span v-else class="text-disabled">—</span>
      </template>

      <template #item.non_prioritas="{ item }">
        <VChip v-if="item.non_prioritas" size="small" color="secondary" variant="tonal">{{ item.non_prioritas }}</VChip>
        <span v-else class="text-disabled">—</span>
      </template>

      <template #item.total="{ item }">
        <VChip size="small" color="primary" variant="elevated" class="font-weight-bold">{{ item.total }}</VChip>
      </template>

      <!-- Totals row -->
      <template #body.append>
        <tr class="totals-row">
          <td colspan="2" class="text-caption font-weight-bold px-4 py-2">TOTAL</td>
          <td class="text-center"><strong>{{ totals.edukasi }}</strong></td>
          <td class="text-center"><strong>{{ totals.edukasi_lanjutan }}</strong></td>
          <td class="text-center"><strong>{{ totals.non_prioritas }}</strong></td>
          <td class="text-center">
            <VChip size="small" color="primary" variant="elevated" class="font-weight-bold">{{ totals.total }}</VChip>
          </td>
        </tr>
      </template>

      <template #no-data>
        <div class="text-center py-8 text-medium-emphasis">
          <VIcon icon="ri-group-line" size="36" class="mb-2 opacity-40" />
          <p class="mb-0">Belum ada data petugas</p>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>

<style scoped>
.totals-row { background: rgba(var(--v-theme-primary), 0.04); }
.totals-row td { border-top: 2px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
</style>
