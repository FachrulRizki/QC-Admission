<script setup>
const props = defineProps({
  stats: { type: Object, default: () => ({}) },
})

const statItems = computed(() => [
  {
    label:    'Total Pasien Eksternal',
    value:    props.stats.jumlahEksternalPasien ?? 0,
    icon:     'ri-group-line',
    color:    '#8C57FF',
    bg:       'rgba(140,87,255,0.12)',
    trend:    '+2.4%',
    trendUp:  true,
    desc:     'Total kunjungan',
  },
  {
    label:    'Rata-rata Durasi Tunggu',
    value:    props.stats.durasiTungguEdukasi ?? '0',
    icon:     'ri-timer-flash-line',
    color:    '#56CA00',
    bg:       'rgba(86,202,0,0.12)',
    suffix:   ' mnt',
    trend:    '-5.1%',
    trendUp:  false,
    desc:     'Waktu tunggu edukasi',
  },
  {
    label:    'Total Quality Control',
    value:    props.stats.totalQC ?? 0,
    icon:     'ri-shield-check-line',
    color:    '#16B1FF',
    bg:       'rgba(22,177,255,0.12)',
    trend:    '+12%',
    trendUp:  true,
    desc:     'Entry QC hari ini',
  },
  {
    label:    'Total Batal Ranap',
    value:    props.stats.totalBatalRanap ?? 0,
    icon:     'ri-close-circle-line',
    color:    '#FF4C51',
    bg:       'rgba(255,76,81,0.12)',
    trend:    '-3',
    trendUp:  false,
    desc:     'Pembatalan rawat inap',
  },
])
</script>

<template>
  <VRow>
    <VCol v-for="item in statItems" :key="item.label" cols="12" sm="6" xl="3">
      <VCard elevation="0" border rounded="lg" class="stat-card h-100">
        <VCardText class="pa-5">
          <div class="d-flex align-start justify-space-between mb-4">
            <div
              class="rounded-xl d-flex align-center justify-center"
              :style="{ background: item.bg, width:'52px', height:'52px', flexShrink:0 }"
            >
              <VIcon :icon="item.icon" size="26" :style="{ color: item.color }" />
            </div>
            <VChip
              :color="item.trendUp ? 'success' : 'error'"
              size="x-small"
              variant="tonal"
              class="font-weight-bold"
            >
              <VIcon :icon="item.trendUp ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'" size="12" />
              {{ item.trend }}
            </VChip>
          </div>

          <p class="text-caption text-medium-emphasis mb-1">{{ item.label }}</p>
          <div class="d-flex align-baseline gap-1">
            <span class="text-h4 font-weight-bold" :style="{ color: item.color }">
              {{ typeof item.value === 'number' ? item.value.toLocaleString('id-ID') : item.value }}
            </span>
            <span v-if="item.suffix" class="text-body-2 text-medium-emphasis">{{ item.suffix }}</span>
          </div>
          <p class="text-caption text-disabled mt-1 mb-0">{{ item.desc }}</p>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.stat-card { transition: box-shadow 0.2s, transform 0.15s; }
.stat-card:hover {
  box-shadow: 0 6px 24px rgba(var(--v-shadow-key-umbra-color), 0.1) !important;
  transform: translateY(-2px);
}
</style>
