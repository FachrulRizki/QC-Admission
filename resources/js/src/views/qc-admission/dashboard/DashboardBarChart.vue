<script setup>
import VueApexCharts from 'vue3-apexcharts'

const props = defineProps({
  items: { type: Array, default: () => [] },
})

const chartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'Inter, Segoe UI, sans-serif',
    background: 'transparent',
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
    style: { fontSize: '11px', colors: ['#1A1F1E'], fontWeight: 700 },
    formatter: (val) => val > 0 ? val.toLocaleString('id-ID') : '',
  },
  xaxis: {
    categories: props.items.map(i => {
      const k = i.kamar ?? ''
      // Shorten long labels
      return k.length > 18 ? k.slice(0, 18) + '…' : k
    }),
    labels: {
      style: { fontSize: '11px', colors: '#5C6B67' },
      rotate: -30,
      rotateAlways: props.items.length > 5,
    },
    axisBorder: { color: '#E1E7E5' },
    axisTicks: { color: '#E1E7E5' },
  },
  yaxis: {
    labels: { style: { fontSize: '11px', colors: '#5C6B67' } },
    title: { text: 'Jumlah Pasien', style: { fontSize: '11px', color: '#5C6B67' } },
  },
  colors: ['#00C896'],
  grid: {
    borderColor: '#E1E7E5',
    strokeDashArray: 4,
    yaxis: { lines: { show: true } },
    xaxis: { lines: { show: false } },
  },
  fill: {
    type: 'gradient',
    gradient: {
      shade: 'light', type: 'vertical',
      gradientToColors: ['#009E6B'],
      stops: [0, 100],
    },
  },
  tooltip: {
    theme: 'light',
    y: { formatter: (val) => val.toLocaleString('id-ID') + ' pasien' },
  },
  legend: {
    show: true,
    labels: { colors: '#5C6B67' },
    markers: { fillColors: ['#00C896'] },
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
      <span class="ds-card-title">Bar Chart <strong>Jumlah Pasien Diedukasi</strong> Based Kebutuhan Kamar Pasien</span>
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
