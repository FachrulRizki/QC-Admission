<script setup>
import { useDashboardStore } from '@/stores/useDashboardStore'
import DashboardStatCards from '@/views/qc-admission/dashboard/DashboardStatCards.vue'
import DashboardPetugasTable from '@/views/qc-admission/dashboard/DashboardPetugasTable.vue'
import DashboardRuanganTable from '@/views/qc-admission/dashboard/DashboardRuanganTable.vue'
import DashboardRecentQCTable from '@/views/qc-admission/dashboard/DashboardRecentQCTable.vue'

const dashboardStore = useDashboardStore()

// Use mock data for UI development; replace with dashboardStore.fetchDashboard() when API is ready
const mockStats = {
  jumlahEksternalPasien: 57334,
  durasiTungguEdukasi: '164.03',
  totalQC: 248,
  totalBatalRanap: 12,
}

const mockPetugas = [
  { petugas: 'Nurul/AYU Putri Anisa', non_prioritas: 3, edukasi: 3, edukasi_lanjutan: 0, masuk: 0, total: 3 },
  { petugas: 'Reskim', non_prioritas: 1, edukasi: 4, edukasi_lanjutan: 1, masuk: 0, total: 4 },
  { petugas: 'Mulbagus Koyum', non_prioritas: 0, edukasi: 3, edukasi_lanjutan: 0, masuk: 1, total: 3 },
]

const mockRuangan = [
  { ruangan: 'Penyakit', kamar_standar: 4, kelas_1: 5, kelas_2: 3, kelas_3: 2, jumlah: 14 },
  { ruangan: 'Bedah', kamar_standar: 2, kelas_1: 3, kelas_2: 1, kelas_3: 1, jumlah: 7 },
  { ruangan: 'Anak', kamar_standar: 1, kelas_1: 2, kelas_2: 0, kelas_3: 1, jumlah: 4 },
]

const mockRecentQC = [
  {
    tanggal: '29 Jun 2026, 19:30:11',
    no_mr: '813500',
    status: 'Edukasi',
    jam_input: null,
    petugas: 'Nurul',
    note: 'Sudah menjelaskan kelas',
    edukasi_kamar: '',
    durasi_tunggu: 134,
  },
  {
    tanggal: '29 Jun 2026, 18:05:22',
    no_mr: '575360',
    status: 'Edukasi lanjutan',
    jam_input: null,
    petugas: 'Reskim',
    note: 'Pasien mengerti',
    edukasi_kamar: 'Ruang Mawar',
    durasi_tunggu: 97,
  },
  {
    tanggal: '29 Jun 2026, 17:44:00',
    no_mr: '087220',
    status: 'Masuk',
    jam_input: null,
    petugas: 'Reskim',
    note: '',
    edukasi_kamar: 'Ruang Anggrek',
    durasi_tunggu: 111,
  },
]

const loading = ref(false)

onMounted(() => {
  // dashboardStore.fetchDashboard()
})
</script>

<template>
  <div>
    <!-- Page Header -->
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h4 class="text-h5 font-weight-bold">Dashboard</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Quality Control Admission &mdash; Ringkasan Harian
        </p>
      </div>
      <VBtn
        variant="tonal"
        color="primary"
        prepend-icon="ri-refresh-line"
        size="small"
        :loading="loading"
        @click="() => {}"
      >
        Refresh
      </VBtn>
    </div>

    <!-- Stat Cards -->
    <DashboardStatCards :stats="mockStats" class="mb-6" />

    <!-- Petugas & Ruangan tables -->
    <VRow class="mb-6">
      <VCol cols="12" lg="6">
        <DashboardPetugasTable :items="mockPetugas" :loading="loading" />
      </VCol>
      <VCol cols="12" lg="6">
        <DashboardRuanganTable :items="mockRuangan" :loading="loading" />
      </VCol>
    </VRow>

    <!-- Recent QC Data Table -->
    <DashboardRecentQCTable :items="mockRecentQC" :loading="loading" />
  </div>
</template>
