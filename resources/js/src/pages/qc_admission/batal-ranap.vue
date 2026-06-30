<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import BatalRanapFormDialog from '@/views/qc-admission/batal-ranap/BatalRanapFormDialog.vue'

const store = useBatalRanapStore()

const tabs = [
  { label: 'Input Ps Batal Ranap',  icon: 'ri-add-circle-line',      key: 'input' },
  { label: 'Verif Ps Batal Ranap',  icon: 'ri-checkbox-circle-line', key: 'verif' },
  { label: 'View Data Input',        icon: 'ri-table-line',           key: 'view'  },
]

const activeTab = ref(null) // null = show sub-menu list

// ── Data ──────────────────────────────────────────────────────────────────────
const mockRecords = ref([
  {
    id: 1,
    tanggal: '29/06/2026, 19.43.45', jam_input: '19.43.45',
    no_reg: 'REG001BR', nama_pasien: 'ELLY MAYA, NY',
    keterangan_batal: 'Kamar Penuh', status_ok: 'Pending',
    ketersediaan_kamar: '2 kamar tersedia', diagnosa: 'Hipertensi',
    note: '', petugas: 'Nurul', ruangan: 'Ruang Mawar',
  },
  {
    id: 2,
    tanggal: '28/06/2026, 10.20.00', jam_input: '10.20.00',
    no_reg: 'REG003BR', nama_pasien: 'RUSMINI, NY',
    keterangan_batal: 'Pasien Menolak', status_ok: 'OK',
    ketersediaan_kamar: '', diagnosa: 'Diabetes',
    note: 'Sudah konfirmasi', petugas: 'Reskim', ruangan: 'Ruang Anggrek',
  },
])

const showFormDialog = ref(false)
const editItem       = ref(null)
const viewSearch     = ref('')
const selectedVerif  = ref(null)
const showVerifDialog = ref(false)
const verifStatus    = ref('OK')
const verifNote      = ref('')

function openInput() {
  editItem.value = null
  showFormDialog.value = true
}

function openVerif(item) {
  selectedVerif.value = { ...item }
  verifStatus.value   = item.status_ok === 'OK' ? 'OK' : 'Pending'
  verifNote.value     = item.note ?? ''
  showVerifDialog.value = true
}

function confirmVerif() {
  if (selectedVerif.value) {
    const idx = mockRecords.value.findIndex(r => r.id === selectedVerif.value.id)
    if (idx !== -1) {
      mockRecords.value[idx].status_ok = verifStatus.value
      mockRecords.value[idx].note = verifNote.value
    }
  }
  showVerifDialog.value = false
}

function onSaved(data) {
  if (editItem.value) {
    const idx = mockRecords.value.findIndex(r => r.id === editItem.value.id)
    if (idx !== -1) mockRecords.value.splice(idx, 1, { ...editItem.value, ...data })
  } else {
    mockRecords.value.unshift({ id: Date.now(), ...data })
  }
  showFormDialog.value = false
}

const viewHeaders = [
  { title: 'Tanggal',           key: 'tanggal',           sortable: true },
  { title: 'No. Reg',           key: 'no_reg',            sortable: true },
  { title: 'Nama Pasien',       key: 'nama_pasien',       sortable: true },
  { title: 'Keterangan Batal',  key: 'keterangan_batal',  sortable: true },
  { title: 'Ruangan',           key: 'ruangan',           sortable: true },
  { title: 'Status OK',         key: 'status_ok',         sortable: true, align: 'center' },
  { title: 'Diagnosa',          key: 'diagnosa',          sortable: true },
  { title: 'Petugas',           key: 'petugas',           sortable: true },
  { title: 'Aksi',              key: 'actions',           sortable: false, align: 'center' },
]

function statusColor(s) {
  return { OK: 'success', Pending: 'warning', Ditolak: 'error' }[s] ?? 'secondary'
}

const stats = computed(() => ({
  total:    mockRecords.value.length,
  pending:  mockRecords.value.filter(r => r.status_ok === 'Pending').length,
  ok:       mockRecords.value.filter(r => r.status_ok === 'OK').length,
  ditolak:  mockRecords.value.filter(r => r.status_ok === 'Ditolak').length,
}))

onMounted(() => { /* store.fetchRecords() */ })
</script>

<template>
  <div>
    <!-- Sub-view open -->
    <template v-if="activeTab !== null">
      <div class="d-flex align-center gap-3 mb-5">
        <VBtn icon variant="text" size="small" @click="activeTab = null">
          <VIcon icon="ri-arrow-left-line" />
        </VBtn>
        <div class="flex-grow-1">
          <h4 class="page-title">{{ tabs.find(t => t.key === activeTab)?.label }}</h4>
          <p class="text-body-2 text-medium-emphasis mb-0">Batal Ranap</p>
        </div>
        <VBtn v-if="activeTab === 'input'" color="error" rounded="lg" prepend-icon="ri-add-line" @click="openInput">
          Input Baru
        </VBtn>
      </div>

      <!-- ── Input Ps ─────────────────────────────────────────────────────── -->
      <template v-if="activeTab === 'input'">
        <VCard elevation="0" border rounded="lg">
          <VCardText class="text-center py-10">
            <VAvatar color="error" variant="tonal" size="64" rounded="xl" class="mb-4">
              <VIcon icon="ri-add-circle-line" size="32" />
            </VAvatar>
            <h6 class="text-h6 mb-2">Input Pasien Batal Ranap</h6>
            <p class="text-body-2 text-medium-emphasis mb-4">
              Catat data pasien yang membatalkan rawat inap
            </p>
            <VBtn color="error" rounded="lg" prepend-icon="ri-add-line" @click="openInput">
              Input Ps Batal Ranap
            </VBtn>
          </VCardText>
        </VCard>
      </template>

      <!-- ── Verif Ps ─────────────────────────────────────────────────────── -->
      <template v-if="activeTab === 'verif'">
        <VCard elevation="0" border rounded="lg">
          <div class="px-4 py-3 d-flex align-center justify-space-between">
            <p class="text-subtitle-2 font-weight-bold mb-0">Daftar Pasien Batal Ranap</p>
            <VChip color="warning" variant="tonal" size="small">
              {{ stats.pending }} Pending
            </VChip>
          </div>
          <VDivider />
          <VList lines="two">
            <template v-for="(item, idx) in mockRecords" :key="item.id">
              <VListItem class="py-3">
                <template #prepend>
                  <VAvatar :color="statusColor(item.status_ok)" variant="tonal" size="44" rounded="lg">
                    <VIcon icon="ri-hospital-line" size="20" />
                  </VAvatar>
                </template>

                <VListItemTitle class="font-weight-semibold text-body-2">
                  {{ item.nama_pasien || item.no_reg }}
                </VListItemTitle>
                <VListItemSubtitle>
                  {{ item.tanggal }} · {{ item.keterangan_batal }}
                  <span v-if="item.ruangan" class="ms-1">· {{ item.ruangan }}</span>
                </VListItemSubtitle>

                <template #append>
                  <div class="d-flex align-center gap-2">
                    <VChip :color="statusColor(item.status_ok)" size="small" variant="tonal" label>
                      {{ item.status_ok }}
                    </VChip>
                    <VBtn
                      icon
                      size="small"
                      variant="text"
                      color="primary"
                      @click="openVerif(item)"
                    >
                      <VIcon icon="ri-checkbox-circle-line" />
                    </VBtn>
                  </div>
                </template>
              </VListItem>
              <VDivider v-if="idx < mockRecords.length - 1" />
            </template>
            <div v-if="!mockRecords.length" class="text-center py-10 text-medium-emphasis">
              <VIcon icon="ri-inbox-line" size="40" class="mb-2 opacity-40" />
              <p class="mb-0">Tidak ada data</p>
            </div>
          </VList>
        </VCard>
      </template>

      <!-- ── View Data ─────────────────────────────────────────────────────── -->
      <template v-if="activeTab === 'view'">
        <VCard elevation="0" border rounded="lg">
          <div class="d-flex align-center justify-space-between px-4 py-3 flex-wrap gap-2">
            <p class="text-subtitle-2 font-weight-bold mb-0">
              <VIcon icon="ri-table-line" color="info" size="16" class="me-1" />
              View Data Input Batal Ranap
            </p>
            <VTextField
              v-model="viewSearch"
              placeholder="Cari..."
              prepend-inner-icon="ri-search-line"
              variant="outlined"
              density="compact"
              hide-details
              clearable
              style="max-width: 220px"
            />
          </div>
          <VDivider />
          <VDataTable
            :headers="viewHeaders"
            :items="mockRecords"
            :search="viewSearch"
            density="compact"
            hover
          >
            <template #item.status_ok="{ item }">
              <VChip :color="statusColor(item.status_ok)" size="small" variant="tonal" label>
                {{ item.status_ok }}
              </VChip>
            </template>
            <template #item.actions="{ item }">
              <div class="d-flex gap-1 justify-center">
                <VBtn icon size="x-small" variant="text" color="primary" @click="openVerif(item)">
                  <VIcon icon="ri-checkbox-circle-line" size="15" />
                </VBtn>
              </div>
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

    <!-- ── Sub-menu list (default) ──────────────────────────────────────────── -->
    <template v-else>
      <div class="d-flex align-center justify-space-between mb-5">
        <div>
          <h4 class="page-title">Batal Ranap</h4>
          <p class="text-body-2 text-medium-emphasis mb-0">Kelola data pasien batal rawat inap</p>
        </div>
      </div>

      <!-- Stats -->
      <VRow dense class="mb-5">
        <VCol cols="6" sm="3">
          <VCard elevation="0" border rounded="lg" class="text-center pa-3">
            <p class="text-h5 font-weight-bold text-primary mb-0">{{ stats.total }}</p>
            <p class="text-caption text-medium-emphasis mb-0">Total</p>
          </VCard>
        </VCol>
        <VCol cols="6" sm="3">
          <VCard elevation="0" border rounded="lg" class="text-center pa-3">
            <p class="text-h5 font-weight-bold mb-0" style="color: rgb(var(--v-theme-warning))">{{ stats.pending }}</p>
            <p class="text-caption text-medium-emphasis mb-0">Pending</p>
          </VCard>
        </VCol>
        <VCol cols="6" sm="3">
          <VCard elevation="0" border rounded="lg" class="text-center pa-3">
            <p class="text-h5 font-weight-bold mb-0" style="color: rgb(var(--v-theme-success))">{{ stats.ok }}</p>
            <p class="text-caption text-medium-emphasis mb-0">OK</p>
          </VCard>
        </VCol>
        <VCol cols="6" sm="3">
          <VCard elevation="0" border rounded="lg" class="text-center pa-3">
            <p class="text-h5 font-weight-bold mb-0" style="color: rgb(var(--v-theme-error))">{{ stats.ditolak }}</p>
            <p class="text-caption text-medium-emphasis mb-0">Ditolak</p>
          </VCard>
        </VCol>
      </VRow>

      <!-- BED management info -->
      <VAlert type="info" variant="tonal" density="compact" border="start" class="mb-5">
        <div class="text-caption">
          <strong>Integrasi Bed Management IGD:</strong>
          Ketika status diset ke "OK", sistem akan melakukan update status bed pada aplikasi Bed Management IGD melalui API.
          Untuk koneksi SSO Keycloak, akses login dapat menggunakan akun LDAP/SSO rumah sakit.
        </div>
      </VAlert>

      <!-- Menu cards -->
      <VRow dense>
        <VCol v-for="tab in tabs" :key="tab.key" cols="12" sm="4">
          <VCard
            elevation="0"
            border
            rounded="lg"
            class="menu-card cursor-pointer pa-4"
            @click="activeTab = tab.key"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar color="error" variant="tonal" size="48" rounded="lg">
                <VIcon :icon="tab.icon" size="22" />
              </VAvatar>
              <div class="flex-grow-1">
                <p class="text-subtitle-2 font-weight-bold mb-0">{{ tab.label }}</p>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{
                    tab.key === 'input'  ? 'Tambah data batal ranap baru' :
                    tab.key === 'verif'  ? 'Verifikasi dan update status' :
                    'Lihat semua data input'
                  }}
                </p>
              </div>
              <VIcon icon="ri-arrow-right-s-line" color="secondary" />
            </div>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <!-- Form Dialog -->
    <BatalRanapFormDialog v-model="showFormDialog" :edit-item="editItem" @saved="onSaved" />

    <!-- Verif Dialog -->
    <VDialog v-model="showVerifDialog" max-width="400">
      <VCard v-if="selectedVerif" rounded="lg">
        <div class="d-flex align-center gap-3 px-5 py-4">
          <VAvatar color="primary" variant="tonal" size="40" rounded="lg">
            <VIcon icon="ri-checkbox-circle-line" size="20" />
          </VAvatar>
          <div class="flex-grow-1">
            <p class="text-subtitle-1 font-weight-bold mb-0">Verifikasi Batal Ranap</p>
            <p class="text-caption text-medium-emphasis mb-0">{{ selectedVerif.nama_pasien || selectedVerif.no_reg }}</p>
          </div>
          <VBtn icon variant="text" size="small" @click="showVerifDialog = false">
            <VIcon icon="ri-close-line" />
          </VBtn>
        </div>
        <VDivider />
        <VCardText class="pa-5">
          <div class="mb-4">
            <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">Status Verifikasi</p>
            <VBtnToggle v-model="verifStatus" mandatory rounded="lg" color="primary" density="compact" class="w-100">
              <VBtn value="Pending" class="flex-grow-1" variant="outlined">Pending</VBtn>
              <VBtn value="OK" class="flex-grow-1" variant="outlined">OK</VBtn>
              <VBtn value="Ditolak" class="flex-grow-1" variant="outlined">Ditolak</VBtn>
            </VBtnToggle>
          </div>
          <VTextField v-model="verifNote" label="Note" variant="outlined" density="compact" />
          <VAlert v-if="verifStatus === 'OK'" type="success" variant="tonal" density="compact" class="mt-3">
            Status bed di ruangan <strong>{{ selectedVerif.ruangan || '—' }}</strong> akan diupdate ke "Tersedia" via Bed Management API.
          </VAlert>
        </VCardText>
        <VDivider />
        <div class="d-flex gap-3 px-5 py-4">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showVerifDialog = false">Batal</VBtn>
          <VBtn color="primary" rounded="lg" class="flex-grow-1" prepend-icon="ri-save-line" @click="confirmVerif">Simpan</VBtn>
        </div>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.page-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 2px; }
.menu-card { transition: box-shadow 0.2s, transform 0.15s; }
.menu-card:hover { box-shadow: 0 4px 16px rgba(var(--v-shadow-key-umbra-color), 0.1) !important; transform: translateY(-1px); }
</style>
