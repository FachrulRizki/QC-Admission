<script setup>
const props = defineProps({
  items: { type: Array, default: () => [] }, // [{ status, count }]
})

const total = computed(() => props.items.reduce((s, i) => s + (i.count ?? 0), 0))

const rows = computed(() => props.items.map(i => ({
  ...i,
  pct:   total.value > 0 ? Math.round((i.count / total.value) * 100) : 0,
  width: total.value > 0 ? Math.max(20, Math.round((i.count / total.value) * 100)) : 20,
})))

const colorMap = {
  'Edukasi':          { bar: 'var(--qc-green)', text: 'var(--qc-green-dark)' },
  'Edukasi lanjutan': { bar: 'var(--qc-green)', text: '#004D35' },
}
</script>

<template>
  <VCard class="ds-card h-100" elevation="0">
    <div class="ds-card-header">
      <span class="ds-card-title">Jumlah Edukasi <strong>Based Status</strong></span>
    </div>
    <VCardText class="pa-4">
      <div v-if="!rows.length" class="d-flex align-center justify-center" style="height:220px">
        <div class="text-center text-medium-emphasis">
          <VIcon icon="ri-filter-3-line" size="36" class="mb-2 opacity-40" />
          <p class="text-caption mb-0">Belum ada data</p>
        </div>
      </div>
      <div v-else class="funnel-wrap">
        <div v-for="(row, idx) in rows" :key="idx" class="funnel-row">
          <!-- Label kiri -->
          <div class="funnel-label">
            <span class="funnel-label__status">{{ row.status }}</span>
            <span class="funnel-label__count">{{ row.count.toLocaleString('id-ID') }}</span>
          </div>
          <!-- Bar -->
          <div class="funnel-bar-track">
            <div
              class="funnel-bar"
              :style="{
                width: row.width + '%',
                background: colorMap[row.status]?.bar ?? 'var(--qc-green)',
              }"
            >
              <span class="funnel-bar__pct">{{ row.pct }}%</span>
            </div>
          </div>
        </div>

        <!-- Total -->
        <div class="funnel-total mt-3 pt-3">
          <span class="funnel-label__status font-weight-bold">Total</span>
          <span class="funnel-label__count font-weight-bold text-primary">{{ total.toLocaleString('id-ID') }}</span>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.funnel-wrap { padding: 8px 0; }

.funnel-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.funnel-label {
  min-width: 160px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex-shrink: 0;
}
.funnel-label__status { font-size: 0.8rem; font-weight: 600; color: #1A1F1E; }
.funnel-label__count  { font-size: 0.75rem; color: #5C6B67; }

.funnel-bar-track {
  flex: 1;
  background: #F0F4F3;
  border-radius: 6px;
  height: 34px;
  overflow: hidden;
}
.funnel-bar {
  height: 100%;
  border-radius: 6px;
  display: flex;
  align-items: center;
  padding: 0 10px;
  transition: width 0.5s ease;
  min-width: 40px;
}
.funnel-bar__pct { font-size: 0.75rem; font-weight: 700; color: #fff; white-space: nowrap; }

.funnel-total {
  display: flex;
  justify-content: space-between;
  border-top: 1px solid #E1E7E5;
}
</style>

