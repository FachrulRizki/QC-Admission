<script setup>
import VueApexCharts from 'vue3-apexcharts'
import { useTheme }  from 'vuetify'

const props = defineProps({
  items: { type: Array, default: () => [] },
})

const theme = useTheme()

// Baca warna dari Vuetify theme secara reaktif
const primaryColor  = computed(() => `#${theme.current.value.colors.primary.replace('#', '')}`)
const isDark        = computed(() => theme.current.value.dark)
const textColor     = computed(() => isDark.value ? 'rgba(255,255,255,0.65)' : '#5C6B67')
const labelColor    = computed(() => isDark.value ? 'rgba(255,255,255,0.87)' : '#1A1F1E')
const gridColor     = computed(() => isDark.value ? 'rgba(255,255,255,0.08)' : '#E1E7E5')
const bgColor       = computed(() => isDark.value ? 'transparent' : 'transparent')

const chartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'Inter, Segoe UI, sans-serif',
    background: 'transparent',
    foreColor: textColor.value,
  },
  plotOptions: {
    bar: {
      borderRadius: 6,
      borderRadiusApplication: 'end',
      columnWidth: '55%',
      dataLabels: { position: 'top' },
    },
  },
  dataLabels: {
    enabled: true,
    offsetY: -20,
    style: {
      fontSize: '11px',
      colors: [labelColor.value],
      fontWeight: 700,
    },
    formatter: (val) => val > 0 ? val.toLocaleString('id-ID') : '',
  },
  xaxis: {
    categories: props.items.map(i => {
      const k = i.kamar ?? ''
      return k.length > 18 ? k.slice(0, 18) + '…' : k
    }),
    labels: {
      style: { fontSize: '11px', colors: textColor.value },
      rotate: -30,
      rotateAlways: props.items.length > 5,
    },
    axisBorder: { color: gridColor.value },
    axisTicks:  { color: gridColor.value },
  },
  yaxis: {
    labels: { style: { fontSize: '11px', colors: textColor.value } },
    title: { text: 'Jumlah Pasien', style: { fontSize: '11px', color: textColor.value } },
  },
  colors: [theme.current.value.colors.primary],
  grid: {
    borderColor: gridColor.value,
    strokeDashArray: 4,
    yaxis: { lines: { show: true } },
    xaxis: { lines: { show: false } },
  },
  fill: {
    type: 'gradient',
    gradient: {
      shade: isDark.value ? 'dark' : 'light',
      type: 'vertical',
      gradientToColors: [theme.current.value.colors['primary-darken-1'] ?? theme.current.value.colors.primary],
      stops: [0, 100],
    },
  },
  tooltip: {
    theme: isDark.value ? 'dark' : 'light',
    y: { formatter: (val) => val.toLocaleString('id-ID') + ' pasien' },
  },
  legend: {
    show: true,
    labels: { colors: textColor.value },
    markers: { fillColors: [theme.current.value.colors.primary] },
  },
}))

const series = computed(() => [{
  name: 'Record Count',
  data: props.items.map(i => i.count ?? 0),
}])
</script>

<template>
  <VCard class="ds-card h-100" elevation="0">
    <div class="ds-card-header">
      <span class="ds-card-title">
        Bar Chart <strong>Jumlah Pasien Diedukasi</strong> Based Kebutuhan Kamar Pasien
      </span>
    </div>
    <VCardText class="pa-4">
      <div v-if="!items.length" class="d-flex align-center justify-center" style="height:220px">
        <div class="text-center text-medium-emphasis">
          <VIcon icon="ri-bar-chart-box-line" size="36" class="mb-2 opacity-40" />
          <p class="text-caption mb-0">Belum ada data</p>
        </div>
      </div>
      <VueApexCharts
        v-else
        type="bar"
        height="230"
        :options="chartOptions"
        :series="series"
      />
    </VCardText>
  </VCard>
</template>
