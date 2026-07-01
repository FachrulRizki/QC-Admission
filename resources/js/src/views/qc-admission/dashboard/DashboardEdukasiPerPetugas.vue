<script setup>
const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
})

const page    = ref(1)
const perPage = 10
const paginated  = computed(() => props.items.slice((page.value - 1) * perPage, page.value * perPage))
const pageCount  = computed(() => Math.ceil(props.items.length / perPage))
const startIdx   = computed(() => (page.value - 1) * perPage)
const endIdx     = computed(() => Math.min(page.value * perPage, props.items.length))
</script>

<template>
  <VCard class="ds-card h-100" elevation="0">
    <div class="ds-card-header">
      <span class="ds-card-title">Jumlah Edukasi <strong>Based Petugas</strong></span>
    </div>
    <div class="ds-table-wrap">
      <table class="ds-table">
        <thead>
          <tr>
            <th class="ds-th" style="width:44px">#</th>
            <th class="ds-th">Petugas</th>
            <th class="ds-th text-end">Jumlah Edukasi Pasien ▾</th>
          </tr>
        </thead>
        <tbody v-if="loading">
          <tr><td colspan="3" class="text-center py-6"><VProgressCircular indeterminate size="24" color="#00B37E" /></td></tr>
        </tbody>
        <tbody v-else-if="!paginated.length">
          <tr><td colspan="3" class="ds-td text-center py-6 text-medium-emphasis">Belum ada data</td></tr>
        </tbody>
        <tbody v-else>
          <tr v-for="(row, idx) in paginated" :key="row.petugas" class="ds-tr">
            <td class="ds-td text-caption text-medium-emphasis">{{ startIdx + idx + 1 }}</td>
            <td class="ds-td">
              <div class="d-flex align-center gap-2">
                <div class="petugas-avatar">{{ row.petugas?.charAt(0) }}</div>
                <span class="font-weight-medium">{{ row.petugas }}</span>
              </div>
            </td>
            <td class="ds-td text-end font-weight-bold" style="color:#00B37E">
              {{ row.jumlah_edukasi?.toLocaleString('id-ID') }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="ds-pagination">
      <span class="ds-pagination__info">{{ startIdx + 1 }}–{{ endIdx }}/{{ items.length }}</span>
      <button class="ds-pg-btn" :disabled="page <= 1" @click="page--">‹</button>
      <button class="ds-pg-btn" :disabled="page >= pageCount" @click="page++">›</button>
    </div>
  </VCard>
</template>

<style scoped>
.ds-table-wrap { overflow-x: auto; }
.ds-table { width: 100%; border-collapse: collapse; }
.ds-th {
  background: #F0F4F3; color: #5C6B67;
  font-size: 0.72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.04em;
  padding: 8px 16px; white-space: nowrap;
  border-bottom: 1px solid #E1E7E5;
}
.ds-td { padding: 8px 16px; font-size: 0.85rem; color: #1A1F1E; border-bottom: 1px solid #E1E7E5; }
.ds-tr:hover td { background: #D7F5EA; }
.petugas-avatar {
  width: 28px; height: 28px; border-radius: 8px;
  background: #D7F5EA; color: #00B37E;
  font-size: 0.75rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.ds-pagination {
  display: flex; align-items: center; justify-content: flex-end;
  gap: 6px; padding: 8px 16px; border-top: 1px solid #E1E7E5;
}
.ds-pagination__info { font-size: 0.75rem; color: #5C6B67; margin-right: 4px; }
.ds-pg-btn {
  width: 28px; height: 28px; border-radius: 50%;
  border: 1.5px solid #00B37E; background: transparent;
  color: #00B37E; font-size: 1rem; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: background 0.15s;
}
.ds-pg-btn:hover:not(:disabled) { background: #D7F5EA; }
.ds-pg-btn:disabled { border-color: #E1E7E5; color: #E1E7E5; cursor: default; }
</style>
