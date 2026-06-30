<script setup>
import { useDashboardStore } from '@/stores/useDashboardStore'
import { useAuthStore }      from '@/stores/useAuthStore'
import DashboardStatCards    from '@/views/qc-admission/dashboard/DashboardStatCards.vue'
import DashboardPetugasTable from '@/views/qc-admission/dashboard/DashboardPetugasTable.vue'
import DashboardRuanganTable from '@/views/qc-admission/dashboard/DashboardRuanganTable.vue'
import DashboardRecentQCTable from '@/views/qc-admission/dashboard/DashboardRecentQCTable.vue'

const store = useDashboardStore()
const auth  = useAuthStore()

const now        = ref(new Date())
const lastRefresh = ref(null)

const greeting = computed(() => {
  const h = now.value.getHours()
  if (h < 11) return 'Selamat Pagi'
  if (h < 15) return 'Selamat Siang'
  if (h < 18) return 'Selamat Sore'
  return 'Selamat Malam'
})

const formattedDate = computed(() =>
  now.value.toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' })
)

const formattedTime = computed(() =>
  now.value.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' })
)

// Mock data — ganti dengan store.fetchDashboard() saat API ready
const mockStats = {
  jumlahEksternalPasien: 57334,
  durasiTungguEdukasi: '164',
  totalQC: 248,
  totalBatalRanap: 12,
  totalEdukasiLanjutan: 34,
  totalUpSelling: 8,
}

const mockPetugas = [
  { petugas: 'Nurul',           non_prioritas: 3, edukasi: 3, edukasi_lanjutan: 2, masuk: 0, total: 8 },
  { petugas: 'AYU Putri Anisa', non_prioritas: 1, edukasi: 4, edukasi_lanjutan: 1, masuk: 2, total: 8 },
  { petugas: 'Reskim',          non_prioritas: 2, edukasi: 5, edukasi_lanjutan: 0, masuk: 1, total: 8 },
  { petugas: 'Mulbagus Koyum',  non_prioritas: 0, edukasi: 3, edukasi_lanjutan: 1, masuk: 1, total: 5 },
  { petugas: 'Abdul Hayyi',     non_prioritas: 1, edukasi: 2, edukasi_lanjutan: 0, masuk: 0, total: 3 },
]

const mockRuangan = [
  { ruangan: 'Penyakit Dalam', kamar_standar: 4, kelas_1: 5, kelas_2: 3, kelas_3: 2, jumlah: 14 },
  { ruangan: 'Bedah',          kamar_standar: 2, kelas_1: 3, kelas_2: 1, kelas_3: 1, jumlah: 7  },
  { ruangan: 'Anak',           kamar_standar: 1, kelas_1: 2, kelas_2: 0, kelas_3: 1, jumlah: 4  },
  { ruangan: 'Kebidanan',      kamar_standar: 3, kelas_1: 2, kelas_2: 2, kelas_3: 1, jumlah: 8  },
  { ruangan: 'ICU',            kamar_standar: 4, kelas_1: 0, kelas_2: 0, kelas_3: 0, jumlah: 4  },
]

const mockRecentQC = [
  { tanggal:'29 Jun 2026, 19:30', no_mr:'813500', nama_pasien:'ELLY MAYA, NY',    status:'Edukasi',          petugas:'Nurul',  durasi_tunggu:'01:34', edukasi_kamar:'' },
  { tanggal:'29 Jun 2026, 18:05', no_mr:'575360', nama_pasien:'IDH SUBINGSEN, NY',status:'Edukasi lanjutan', petugas:'Reskim', durasi_tunggu:'02:17', edukasi_kamar:'Ruang Mawar' },
  { tanggal:'29 Jun 2026, 17:44', no_mr:'087220', nama_pasien:'RUSMINI, NY',      status:'Edukasi',          petugas:'Nurul',  durasi_tunggu:'00:48', edukasi_kamar:'Ruang Anggrek' },
  { tanggal:'28 Jun 2026, 14:10', no_mr:'816302', nama_pasien:'PUSPA SARI, AN',   status:'Edukasi lanjutan', petugas:'AYU Putri Anisa', durasi_tunggu:'03:20', edukasi_kamar:'ICU' },
  { tanggal:'28 Jun 2026, 11:30', no_mr:'712405', nama_pasien:'BUDI SANTOSO, TN', status:'Edukasi',          petugas:'Reskim', durasi_tunggu:'01:05', edukasi_kamar:'' },
]

// Quick stats mini
const quickCards = computed(() => [
  { label:'Quality Control',     value: mockStats.totalQC,             icon:'ri-shield-check-line',    color:'primary',  to:'/quality-control'  },
  { label:'Edukasi Lanjutan',    value: mockStats.totalEdukasiLanjutan,icon:'ri-book-open-line',       color:'warning',  to:'/edukasi-lanjutan' },
  { label:'Batal Ranap',         value: mockStats.totalBatalRanap,     icon:'ri-close-circle-line',    color:'error',    to:'/batal-ranap'      },
  { label:'Up Selling',          value: mockStats.totalUpSelling,      icon:'ri-arrow-up-circle-line', color:'success',  to:'/up-selling'       },
])

// Update clock every minute
let clockTimer
onMounted(() => {
  clockTimer = setInterval(() => { now.value = new Date() }, 60_000)
  lastRefresh.value = new Date()
  // store.fetchDashboard()
})
onUnmounted(() => clearInterval(clockTimer))

async function doRefresh() {
  await store.fetchDashboard()
  lastRefresh.value = new Date()
}
</script>

<template>
  <div>
    <!-- ── Hero Banner ─────────────────────────────────────────────────────── -->
    <div class="page-hero page-hero--dashboard mb-6">
      <div class="page-hero__content">
        <div class="page-hero__badge">
          <VIcon icon="ri-dashboard-line" size="13" />
          Dashboard
        </div>
        <h1 class="page-hero__title">{{ greeting }}, {{ auth.currentUser?.name?.split(' ')[0] ?? 'Petugas' }} 👋</h1>
        <p class="page-hero__subtitle">{{ formattedDate }} · {{ formattedTime }}</p>
      </div>
      <div class="d-flex flex-column align-end gap-2" style="position:relative;z-index:2">
        <VBtn
          variant="elevated"
          color="white"
          size="small"
          rounded="lg"
          prepend-icon="ri-refresh-line"
          style="color: #1a1a2e"
          :loading="store.loading"
          @click="doRefresh"
        >
          Refresh Data
        </VBtn>
        <span v-if="lastRefresh" class="text-caption" style="color:rgba(255,255,255,0.65)">
          Update: {{ lastRefresh.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' }) }}
        </span>
      </div>
      <VIcon icon="ri-bar-chart-box-line" class="page-hero__icon" />
    </div>

    <!-- ── Quick nav cards ─────────────────────────────────────────────────── -->
    <VRow dense class="mb-5">
      <VCol v-for="c in quickCards" :key="c.label" cols="6" sm="3">
        <RouterLink :to="c.to" style="text-decoration:none">
          <VCard elevation="0" border rounded="lg" class="quick-card pa-4 h-100" :class="`quick-card--${c.color}`">
            <div class="d-flex align-center gap-3">
              <VAvatar :color="c.color" variant="tonal" size="44" rounded="lg">
                <VIcon :icon="c.icon" size="22" />
              </VAvatar>
              <div>
                <p class="text-h5 font-weight-bold mb-0">{{ c.value }}</p>
                <p class="text-caption text-medium-emphasis mb-0">{{ c.label }}</p>
              </div>
            </div>
          </VCard>
        </RouterLink>
      </VCol>
    </VRow>

    <!-- ── Main stat cards ─────────────────────────────────────────────────── -->
    <DashboardStatCards :stats="mockStats" class="mb-6" />

    <!-- ── Petugas & Ruangan ───────────────────────────────────────────────── -->
    <VRow class="mb-6">
      <VCol cols="12" lg="7">
        <DashboardPetugasTable :items="mockPetugas" :loading="store.loading" />
      </VCol>
      <VCol cols="12" lg="5">
        <DashboardRuanganTable :items="mockRuangan" :loading="store.loading" />
      </VCol>
    </VRow>

    <!-- ── Recent QC ───────────────────────────────────────────────────────── -->
    <DashboardRecentQCTable :items="mockRecentQC" :loading="store.loading" />
  </div>
</template>

<style scoped>
.quick-card {
  transition: box-shadow 0.2s, transform 0.15s;
  cursor: pointer;
}
.quick-card:hover {
  box-shadow: 0 4px 20px rgba(var(--v-shadow-key-umbra-color), 0.12) !important;
  transform: translateY(-2px);
}
</style>
