<script setup>
const props = defineProps({
  items:   { type: Array,   default: () => [] }, // [{ petugas, Edukasi, 'Edukasi lanjutan', total }]
  loading: { type: Boolean, default: false },
})

// Ambil semua nama petugas unik untuk header kolom
const petugasList = computed(() =>
  props.items.map(r => r.petugas)
)

// Status rows tetap: Edukasi dan Edukasi lanjutan
const STATUS_ROWS = ['Edukasi', 'Edukasi lanjutan']
</script>

<template>
  <VCard class="ds-card h-100" elevation="0">
    <div class="ds-card-header">
      <span class="ds-card-title">Detail Edukasi QC Based <strong>Status/Petugas</strong></span>
    </div>
    <div class="matrix-scroll">
      <table class="ds-table">
        <thead>
          <tr>
            <th class="ds-th sticky-col">Status</th>
            <th v-for="p in petugasList" :key="p" class="ds-th text-center">
              <div class="petugas-header">{{ p }}</div>
            </th>
            <th class="ds-th text-end">Petugas / Record Count</th>
          </tr>
        </thead>
        <tbody v-if="loading">
          <tr>
            <td :colspan="petugasList.length + 2" class="text-center py-6">
              <VProgressCircular indeterminate size="24" color="var(--qc-green)" />
            </td>
          </tr>
        </tbody>
        <tbody v-else-if="!items.length">
          <tr>
            <td :colspan="3" class="text-center py-6 ds-td text-medium-emphasis">Belum ada data</td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr v-for="status in STATUS_ROWS" :key="status" class="ds-tr">
            <td class="ds-td sticky-col font-weight-medium">{{ status }}</td>
            <td v-for="row in items" :key="row.petugas" class="ds-td text-center">
              <span v-if="row[status]" class="matrix-val">{{ row[status] }}</span>
              <span v-else class="text-disabled">—</span>
            </td>
            <!-- Total per status -->
            <td class="ds-td text-end font-weight-bold">
              {{ items.reduce((s, r) => s + (r[status] ?? 0), 0) }}
            </td>
          </tr>
        </tbody>
        <tfoot v-if="items.length">
          <tr class="totals-row">
            <td class="ds-td sticky-col font-weight-bold text-caption uppercase">TOTAL</td>
            <td v-for="row in items" :key="row.petugas" class="ds-td text-center font-weight-bold">
              {{ row.total }}
            </td>
            <td class="ds-td text-end font-weight-bold text-primary">
              {{ items.reduce((s, r) => s + (r.total ?? 0), 0) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </VCard>
</template>

<style scoped>
.matrix-scroll { overflow-x: auto; max-height: 280px; overflow-y: auto; }
.ds-table { width: 100%; border-collapse: collapse; min-width: 480px; }
.ds-th {
  background: rgba(var(--v-theme-on-surface), 0.04);
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.04em; padding: 8px 12px; white-space: nowrap;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  position: sticky; top: 0; z-index: 2;
}
.sticky-col {
  position: sticky; left: 0; z-index: 3;
  background: rgba(var(--v-theme-on-surface), 0.04);
}
.ds-tr .sticky-col {
  background: rgb(var(--v-theme-surface));
}
.ds-td {
  padding: 8px 12px; font-size: 0.85rem;
  color: rgba(var(--v-theme-on-surface), 0.87);
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  white-space: nowrap;
}
.ds-tr:hover td { background: rgba(var(--v-theme-primary), 0.06); }
.ds-tr:hover .sticky-col { background: rgba(var(--v-theme-primary), 0.06); }
.petugas-header { max-width: 100px; white-space: normal; font-size: 0.72rem; line-height: 1.3; text-align: center; }
.matrix-val { font-weight: 700; color: rgb(var(--v-theme-primary)); }
.uppercase { text-transform: uppercase; }
.totals-row td {
  background: rgba(var(--v-theme-on-surface), 0.04) !important;
  border-top: 2px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>

