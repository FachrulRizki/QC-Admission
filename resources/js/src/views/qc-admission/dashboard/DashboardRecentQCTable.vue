<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
})

const router = useRouter()

const headers = [
  { title: 'Tanggal',       key: 'tanggal',       sortable: true, width: '155px' },
  { title: 'No. MR',        key: 'no_mr',         sortable: true, width: '90px'  },
  { title: 'Nama Pasien',   key: 'nama_pasien',   sortable: true },
  { title: 'Status',        key: 'status',        sortable: true, align: 'center', width: '150px' },
  { title: 'Petugas',       key: 'petugas',       sortable: true, width: '130px'  },
  { title: 'Durasi Tunggu', key: 'durasi_tunggu', sortable: true, align: 'center', width: '110px' },
  { title: 'Edukasi Kamar', key: 'edukasi_kamar', sortable: false },
]

function statusColor(s) {
  return { 'Edukasi': 'success', 'Edukasi lanjutan': 'warning' }[s] ?? 'secondary'
}

function durasiColor(d) {
  if (!d) return 'success'
  const [h] = d.split(':').map(Number)
  if (h >= 2) return 'error'
  if (h >= 1) return 'warning'
  return 'success'
}
</script>

<template>
  <VCard elevation="0" border rounded="lg">
    <div class="d-flex align-center justify-space-between px-5 py-4 flex-wrap gap-3">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="36" rounded="lg">
          <VIcon icon="ri-shield-check-line" size="18" />
        </VAvatar>
        <div>
          <p class="text-subtitle-2 font-weight-bold mb-0">Quality Control Terbaru</p>
          <p class="text-caption text-medium-emphasis mb-0">Data entry QC hari ini</p>
        </div>
      </div>
      <VBtn
        size="small" variant="tonal" color="primary"
        append-icon="ri-arrow-right-line"
        @click="router.push('/quality-control')"
      >
        Lihat Semua
      </VBtn>
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
      <template #item.nama_pasien="{ item }">
        <div class="d-flex align-center gap-2 py-1">
          <VAvatar color="secondary" variant="tonal" size="28" rounded="lg">
            <span class="text-caption font-weight-bold">{{ item.nama_pasien?.charAt(0) }}</span>
          </VAvatar>
          <span class="text-body-2 font-weight-medium">{{ item.nama_pasien }}</span>
        </div>
      </template>

      <template #item.status="{ item }">
        <VChip :color="statusColor(item.status)" size="small" variant="tonal" label>
          {{ item.status }}
        </VChip>
      </template>

      <template #item.durasi_tunggu="{ item }">
        <VChip :color="durasiColor(item.durasi_tunggu)" size="x-small" variant="tonal">
          <VIcon icon="ri-timer-line" size="11" class="me-1" />
          {{ item.durasi_tunggu }}
        </VChip>
      </template>

      <template #item.edukasi_kamar="{ item }">
        <span v-if="item.edukasi_kamar" class="text-body-2">{{ item.edukasi_kamar }}</span>
        <span v-else class="text-disabled text-caption">—</span>
      </template>

      <template #no-data>
        <div class="text-center py-8 text-medium-emphasis">
          <VIcon icon="ri-shield-check-line" size="36" class="mb-2 opacity-40" />
          <p class="mb-0">Belum ada data QC hari ini</p>
        </div>
      </template>
    </VDataTable>
  </VCard>
</template>
