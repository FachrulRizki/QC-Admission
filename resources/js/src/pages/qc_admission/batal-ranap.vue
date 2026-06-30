<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import BatalRanapFormDialog from '@/views/qc-admission/batal-ranap/BatalRanapFormDialog.vue'
import BatalRanapVerifTable from '@/views/qc-admission/batal-ranap/BatalRanapVerifTable.vue'

const store = useBatalRanapStore()

// Sub-menu tabs matching the AppSheet layout
const tabs = [
  { label: 'Input Ps Batal Ranap', icon: 'ri-add-circle-line' },
  { label: 'Verif Ps Batal Ranap', icon: 'ri-checkbox-circle-line' },
  { label: 'View Data Input', icon: 'ri-table-line' },
]

const activeTab = ref(null) // null = show sub-menu list, set to open a tab

// Mock data
const mockRecords = ref([
  {
    id: 1,
    tanggal: '29/06/2026, 19.43.45',
    jam_input: '19.43.45',
    no_reg: '813500',
    keterangan_batal: 'Kamar Penuh',
    status_ok: 'Pending',
    ketersediaan_kamar: '2 kamar tersedia',
    diagnosa: 'Hipertensi',
    note: '',
    petugas: 'Nurul',
  },
])

const showFormDialog = ref(false)
const editItem = ref(null)

function openInput() {
  editItem.value = null
  showFormDialog.value = true
}

function openVerif(item) {
  editItem.value = { ...item }
  showFormDialog.value = true
}

function onSaved() {
  showFormDialog.value = false
  // store.fetchRecords()
}

// View Data search
const viewSearch = ref('')
const viewHeaders = [
  { title: 'Tanggal', key: 'tanggal', sortable: true },
  { title: 'NoReg', key: 'no_reg', sortable: true },
  { title: 'Keterangan Batal', key: 'keterangan_batal', sortable: true },
  { title: 'Status OK', key: 'status_ok', sortable: true, align: 'center' },
  { title: 'Diagnosa', key: 'diagnosa', sortable: true },
  { title: 'Petugas', key: 'petugas', sortable: true },
  { title: 'Note', key: 'note', sortable: false },
]

function statusColor(s) {
  return { OK: 'success', Pending: 'warning', Ditolak: 'error' }[s] ?? 'secondary'
}

onMounted(() => {
  // store.fetchRecords()
})
</script>

<template>
  <div>
    <!-- If a specific sub-view is open -->
    <template v-if="activeTab !== null">
      <!-- Back button + title -->
      <div class="d-flex align-center gap-3 mb-6">
        <VBtn icon variant="text" size="small" @click="activeTab = null">
          <VIcon icon="ri-arrow-left-line" />
        </VBtn>
        <div>
          <h4 class="text-h5 font-weight-bold">{{ tabs[activeTab].label }}</h4>
          <p class="text-body-2 text-medium-emphasis mb-0">Batal Ranap</p>
        </div>
        <VSpacer />
        <!-- Add button for Input tab -->
        <VBtn
          v-if="activeTab === 0"
          color="primary"
          prepend-icon="ri-add-line"
          @click="openInput"
        >
          Input Baru
        </VBtn>
      </div>

      <!-- Tab: Input Ps Batal Ranap -->
      <template v-if="activeTab === 0">
        <VCard>
          <VCardText class="text-center py-10">
            <VIcon icon="ri-add-circle-line" size="48" color="primary" class="mb-3" />
            <h6 class="text-h6 mb-2">Input Pasien Batal Ranap</h6>
            <p class="text-body-2 text-medium-emphasis mb-4">
              Klik tombol di bawah untuk menambah data pasien batal ranap.
            </p>
            <VBtn color="primary" prepend-icon="ri-add-line" @click="openInput">
              Input Ps Batal Ranap
            </VBtn>
          </VCardText>
        </VCard>
      </template>

      <!-- Tab: Verif Ps Batal Ranap -->
      <template v-if="activeTab === 1">
        <BatalRanapVerifTable
          :items="mockRecords"
          :loading="store.loading"
          @verif="openVerif"
          @delete="(item) => mockRecords = mockRecords.filter(r => r.id !== item.id)"
        />
      </template>

      <!-- Tab: View Data Input -->
      <template v-if="activeTab === 2">
        <VCard>
          <VCardItem>
            <VCardTitle class="d-flex align-center gap-2">
              <VIcon icon="ri-table-line" color="info" />
              View Data Input Batal Ranap
            </VCardTitle>
            <template #append>
              <VTextField
                v-model="viewSearch"
                placeholder="Cari..."
                prepend-inner-icon="ri-search-line"
                variant="outlined"
                density="compact"
                hide-details
                style="min-width: 200px;"
              />
            </template>
          </VCardItem>
          <VDivider />
          <VDataTable
            :headers="viewHeaders"
            :items="mockRecords"
            :loading="store.loading"
            :search="viewSearch"
            density="compact"
            hover
          >
            <template #item.status_ok="{ item }">
              <VChip :color="statusColor(item.status_ok)" size="small" variant="tonal" label>
                {{ item.status_ok ?? 'Pending' }}
              </VChip>
            </template>
            <template #no-data>
              <div class="text-center py-8 text-medium-emphasis">
                <p class="mb-0">Belum ada data</p>
              </div>
            </template>
          </VDataTable>
        </VCard>
      </template>
    </template>

    <!-- Sub-menu List (default view — matches AppSheet layout) -->
    <template v-else>
      <div class="d-flex align-center justify-space-between mb-6">
        <div>
          <h4 class="text-h5 font-weight-bold">Batal Ranap</h4>
          <p class="text-body-2 text-medium-emphasis mb-0">Kelola data pasien batal rawat inap</p>
        </div>
      </div>

      <VCard variant="outlined">
        <VList lines="two">
          <template v-for="(tab, index) in tabs" :key="tab.label">
            <VListItem
              :prepend-icon="tab.icon"
              :title="tab.label"
              class="cursor-pointer py-4"
              @click="activeTab = index"
            >
              <template #append>
                <VIcon icon="ri-arrow-right-s-line" color="secondary" />
              </template>
            </VListItem>
            <VDivider v-if="index < tabs.length - 1" />
          </template>
        </VList>
      </VCard>
    </template>

    <!-- Form Dialog -->
    <BatalRanapFormDialog
      v-model="showFormDialog"
      :edit-item="editItem"
      @saved="onSaved"
    />
  </div>
</template>
