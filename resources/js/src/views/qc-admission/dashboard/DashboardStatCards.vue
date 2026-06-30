<script setup>
const props = defineProps({
  stats: {
    type: Object,
    default: () => ({}),
  },
})

const statItems = computed(() => [
  {
    label: 'Jumlah Eksternal Pasien',
    value: props.stats.jumlahEksternalPasien ?? 0,
    icon: 'ri-group-line',
    color: '#8C57FF',
    bgColor: 'rgba(140,87,255,0.12)',
    trend: '+2.4%',
    trendUp: true,
  },
  {
    label: 'Rata-rata Durasi Tunggu',
    value: props.stats.durasiTungguEdukasi ?? '0',
    icon: 'ri-time-line',
    color: '#56CA00',
    bgColor: 'rgba(86,202,0,0.12)',
    suffix: ' mnt',
    trend: '-5.1%',
    trendUp: false,
  },
  {
    label: 'Total Quality Control',
    value: props.stats.totalQC ?? 0,
    icon: 'ri-shield-check-line',
    color: '#16B1FF',
    bgColor: 'rgba(22,177,255,0.12)',
    trend: '+12%',
    trendUp: true,
  },
  {
    label: 'Total Batal Ranap',
    value: props.stats.totalBatalRanap ?? 0,
    icon: 'ri-close-circle-line',
    color: '#FF4C51',
    bgColor: 'rgba(255,76,81,0.12)',
    trend: '-3',
    trendUp: false,
  },
])
</script>

<template>
  <VRow>
    <VCol
      v-for="item in statItems"
      :key="item.label"
      cols="12"
      sm="6"
      xl="3"
    >
      <VCard elevation="0" border class="stat-card h-100">
        <VCardText class="pa-5">
          <div class="d-flex align-start justify-space-between">
            <!-- Icon -->
            <div
              class="stat-icon-wrap rounded-lg d-flex align-center justify-center"
              :style="{ background: item.bgColor, width: '48px', height: '48px', flexShrink: 0 }"
            >
              <VIcon
                :icon="item.icon"
                size="24"
                :style="{ color: item.color }"
              />
            </div>

            <!-- Trend badge -->
            <VChip
              :color="item.trendUp ? 'success' : 'error'"
              size="x-small"
              variant="tonal"
              class="font-weight-medium"
            >
              <VIcon
                :icon="item.trendUp ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"
                size="12"
                class="me-0"
              />
              {{ item.trend }}
            </VChip>
          </div>

          <div class="mt-4">
            <p class="text-body-2 text-medium-emphasis mb-1">{{ item.label }}</p>
            <div class="d-flex align-baseline gap-1">
              <span
                class="text-h4 font-weight-bold"
                :style="{ color: item.color }"
              >
                {{ typeof item.value === 'number' ? item.value.toLocaleString('id-ID') : item.value }}
              </span>
              <span
                v-if="item.suffix"
                class="text-body-2 font-weight-medium text-medium-emphasis"
              >
                {{ item.suffix }}
              </span>
            </div>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.stat-card {
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}
.stat-card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
  transform: translateY(-2px);
}
</style>
