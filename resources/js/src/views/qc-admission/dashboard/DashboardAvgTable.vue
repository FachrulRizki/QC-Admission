<script setup>
const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
})

const headers = [
  { title: '#',               key: 'no',               width: '44px', sortable: false },
  { title: 'Petugas',         key: 'petugas',          sortable: true },
  { title: 'Durasi Tunggu Edukasi (Menit)', key: 'avg_durasi_menit', sortable: true, align: 'end' },
]

const page     = ref(1)
const perPage  = 10

const paginated = computed(() => {
  const start = (page.value - 1) * perPage
  return props.items.slice(start, start + perPage)
})
const pageCount = computed(() => Math.ceil(props.items.length / perPage))
</script>

<template>
  <VCard class="ds-card h-100" elevation="0">
    <div class="ds-card-header ds-card-header--gradient">
      <span class="ds-card-title--white">Avg Durasi <strong>Edukasi Pasien</strong></span>
    </div>
    <div class="ds-table-wrap">
      <table class="ds-table">
        <thead>
          <tr>
            <th class="ds-th" style="width:44px">#</th>
            <th class="ds-th">Petugas</th>
            <th class="ds-th text-end">Durasi Tunggu Edukasi (Menit) ▾</th>
          </tr>
        </thead>
        <tbody v-if="loading">
          <tr>
            <td colspan="3" class="text-center py-6 text-medium-emphasis">
              <VProgressCircular indeterminate size="24" color="var(--qc-green)" />
            </td>
          </tr>
        </tbody>
        <tbody v-else-if="!paginated.length">
          <tr>
            <td colspan="3" class="text-center py-6 text-medium-emphasis ds-td">
              Belum ada data
            </td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr
            v-for="(row, idx) in paginated"
            :key="row.petugas"
            class="ds-tr"
          >
            <td class="ds-td text-medium-emphasis text-caption">{{ (page - 1) * 10 + idx + 1 }}</td>
            <td class="ds-td font-weight-medium">{{ row.petugas }}</td>
            <td class="ds-td text-end font-weight-bold">{{ row.avg_durasi_menit }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <div class="ds-pagination">
      <span class="ds-pagination__info">
        {{ (page - 1) * 10 + 1 }}–{{ Math.min(page * 10, items.length) }}/{{ items.length }}
      </span>
      <button class="ds-pg-btn" :disabled="page <= 1" @click="page--">‹</button>
      <button class="ds-pg-btn" :disabled="page >= pageCount" @click="page++">›</button>
    </div>
  </VCard>
</template>

<style scoped>
.ds-table-wrap { overflow-x: auto; }
.ds-table { width: 100%; border-collapse: collapse; }
.ds-th {
  background: #F0F4F3;
  color: #5C6B67;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 8px 16px;
  white-space: nowrap;
  border-bottom: 1px solid #E1E7E5;
}
.ds-td {
  padding: 8px 16px;
  font-size: 0.85rem;
  color: #1A1F1E;
  border-bottom: 1px solid #E1E7E5;
}
.ds-tr:hover td { background: var(--qc-green-light); }
.ds-pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  padding: 8px 16px;
  border-top: 1px solid #E1E7E5;
}
.ds-pagination__info { font-size: 0.75rem; color: #5C6B67; margin-right: 4px; }
.ds-pg-btn {
  width: 28px; height: 28px;
  border-radius: 50%;
  border: 1.5px solid var(--qc-green);
  background: transparent;
  color: var(--qc-green);
  font-size: 1rem;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.15s;
}
.ds-pg-btn:hover:not(:disabled) { background: var(--qc-green-light); }
.ds-pg-btn:disabled { border-color: #E1E7E5; color: #E1E7E5; cursor: default; }
</style>

