<script setup>
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'
import EdukasiPatientCard from '@/views/qc-admission/edukasi-lanjutan/EdukasiPatientCard.vue'

const store = useEdukasiLanjutanStore()

// Mock data — replace with store.fetchRecords()
const mockRecords = ref([
  { id: 1, no_mr: '813500', nama_pasien: 'ELLY MAYA, NY', bulan: 'JUNI', tanggal: '29/06/2026' },
  { id: 2, no_mr: '575360', nama_pasien: 'IDH SUBINGSEN, NY', bulan: 'JUNI', tanggal: '29/06/2026' },
  { id: 3, no_mr: '087220', nama_pasien: 'RUSMINI, NY', bulan: 'JUNI', tanggal: '28/06/2026' },
  { id: 4, no_mr: '816302', nama_pasien: 'PUSPA SARI, AN', bulan: 'JUNI', tanggal: '27/06/2026' },
])

const activeFilter = ref('All')

const bulanList = computed(() => {
  const months = [...new Set(mockRecords.value.map(r => r.bulan))]
  return ['All', ...months]
})

const filteredRecords = computed(() => {
  if (activeFilter.value === 'All') return mockRecords.value
  return mockRecords.value.filter(r => r.bulan === activeFilter.value)
})

const bulanCounts = computed(() => {
  const counts = {}
  mockRecords.value.forEach(r => {
    counts[r.bulan] = (counts[r.bulan] ?? 0) + 1
  })
  return counts
})

// Detail / menu state
const detailDialog = ref(false)
const selectedPatient = ref(null)
const menuItems = [
  { title: 'Lihat Detail', icon: 'ri-eye-line', action: 'view' },
  { title: 'Hapus', icon: 'ri-delete-bin-line', action: 'delete', color: 'error' },
]

function openDetail(patient) {
  selectedPatient.value = patient
  detailDialog.value = true
}

onMounted(() => {
  // store.fetchRecords()
})
</script>

<template>
  <div class="d-flex" style="gap: 0; min-height: calc(100vh - 100px);">
    <!-- Sidebar Filter -->
    <VNavigationDrawer
      :rail="false"
      permanent
      width="160"
      class="edukasi-sidebar"
      border="end"
      elevation="0"
    >
      <VList density="compact" nav class="pa-2 pt-4">
        <VListItem
          v-for="bulan in bulanList"
          :key="bulan"
          :active="activeFilter === bulan"
          :value="bulan"
          active-color="primary"
          rounded="sm"
          class="mb-1"
          @click="activeFilter = bulan"
        >
          <VListItemTitle class="text-body-2">
            {{ bulan }}
            <span
              v-if="bulan !== 'All' && bulanCounts[bulan]"
              class="text-caption text-medium-emphasis ms-1"
            >
              {{ bulanCounts[bulan] }}
            </span>
          </VListItemTitle>
        </VListItem>
      </VList>
    </VNavigationDrawer>

    <!-- Main Content -->
    <div class="flex-grow-1 pa-4">
      <!-- Header -->
      <div class="d-flex align-center justify-space-between mb-4">
        <h5 class="text-h6 font-weight-bold">Edukasi Lanjutan</h5>
        <VBtn icon variant="text" size="small">
          <VIcon icon="ri-filter-3-line" />
        </VBtn>
      </div>

      <!-- Patient Cards Grid -->
      <VRow v-if="filteredRecords.length">
        <VCol
          v-for="patient in filteredRecords"
          :key="patient.id"
          cols="12"
          sm="6"
          md="4"
          lg="3"
        >
          <EdukasiPatientCard
            :patient="patient"
            @view="openDetail"
            @menu="openDetail"
          />
        </VCol>
      </VRow>

      <!-- Empty state -->
      <div v-else class="text-center py-12 text-medium-emphasis">
        <VIcon icon="ri-book-open-line" size="48" class="mb-3" />
        <p class="text-body-1">Belum ada data edukasi lanjutan</p>
      </div>
    </div>
  </div>

  <!-- Detail Dialog -->
  <VDialog v-model="detailDialog" max-width="480">
    <VCard v-if="selectedPatient">
      <VCardTitle class="d-flex align-center justify-space-between pa-4">
        <span class="text-h6">Detail Edukasi Lanjutan</span>
        <VBtn icon variant="text" size="small" @click="detailDialog = false">
          <VIcon icon="ri-close-line" />
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-4">
        <VList density="compact">
          <VListItem>
            <VListItemTitle class="text-caption text-medium-emphasis">No. MR</VListItemTitle>
            <VListItemSubtitle class="text-body-2 font-weight-bold">
              {{ selectedPatient.no_mr }}
            </VListItemSubtitle>
          </VListItem>
          <VListItem>
            <VListItemTitle class="text-caption text-medium-emphasis">Nama Pasien</VListItemTitle>
            <VListItemSubtitle class="text-body-2">{{ selectedPatient.nama_pasien }}</VListItemSubtitle>
          </VListItem>
          <VListItem>
            <VListItemTitle class="text-caption text-medium-emphasis">Bulan</VListItemTitle>
            <VListItemSubtitle class="text-body-2">{{ selectedPatient.bulan }}</VListItemSubtitle>
          </VListItem>
          <VListItem>
            <VListItemTitle class="text-caption text-medium-emphasis">Tanggal</VListItemTitle>
            <VListItemSubtitle class="text-body-2">{{ selectedPatient.tanggal }}</VListItemSubtitle>
          </VListItem>
        </VList>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.edukasi-sidebar {
  position: static !important;
  height: auto !important;
}
</style>
