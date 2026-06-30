<script setup>
import { useUpSellingStore } from '@/stores/useUpSellingStore'
import UpSellingFormDialog from '@/views/qc-admission/up-selling/UpSellingFormDialog.vue'

const store = useUpSellingStore()

const showDialog = ref(false)
const editItem   = ref(null)
const search     = ref('')
const showDeleteConfirm = ref(false)
const deleteTarget      = ref(null)

const mockRecords = ref([
  {
    id: 1,
    tanggal: '06/30/2026 08:00:00 AM', update_at: '06/30/2026 08:00:00 AM',
    tgl_daftar: '06/28/2026 07:56:40 PM',
    no_reg: '569142', no_mr: '569142',
    nama_pasien: 'ONGKI SAPUTRA, TN', ket_bayar: 'BPJS',
    nama_ruang: 'PS ATAS 01', nama_bangsal: 'PAHLAWAN ATAS', kelas: 'KELAS VIP',
    ket_up_selling: 'Tidak Berhasil - Budget', notes: '', nama_petugas: 'Nurul',
    status: 'Tidak Berhasil',
  },
  {
    id: 2,
    tanggal: '06/29/2026 10:30:00 AM', update_at: '06/29/2026 10:30:00 AM',
    tgl_daftar: '06/29/2026 08:00:00 AM',
    no_reg: 'REG001', no_mr: '813500',
    nama_pasien: 'ELLY MAYA, NY', ket_bayar: 'BPJS',
    nama_ruang: 'DAHLIA 2', nama_bangsal: 'DAHLIA', kelas: 'Kelas 1',
    ket_up_selling: 'Berhasil Upgrade', notes: 'Pasien setuju upgrade ke VIP', nama_petugas: 'Reskim',
    status: 'Berhasil',
  },
])

const headers = [
  { title: 'Tanggal',      key: 'update_at',      sortable: true },
  { title: 'NoReg',        key: 'no_reg',          sortable: true },
  { title: 'Nama Pasien',  key: 'nama_pasien',     sortable: true },
  { title: 'Ket. Bayar',   key: 'ket_bayar',       sortable: true },
  { title: 'Nama Ruang',   key: 'nama_ruang',      sortable: true },
  { title: 'Bangsal',      key: 'nama_bangsal',    sortable: true },
  { title: 'Kelas',        key: 'kelas',           sortable: true, align: 'center' },
  { title: 'Ket Up Selling', key: 'ket_up_selling', sortable: true },
  { title: 'Petugas',      key: 'nama_petugas',    sortable: true },
  { title: 'Aksi',         key: 'actions',         sortable: false, align: 'center', width: '80px' },
]

const stats = computed(() => ({
  total:       mockRecords.value.length,
  berhasil:    mockRecords.value.filter(r => r.status === 'Berhasil').length,
  tidakBerhasil: mockRecords.value.filter(r => r.status === 'Tidak Berhasil').length,
  pending:     mockRecords.value.filter(r => r.status === 'Pending').length,
}))

function ketColor(s) {
  if (!s) return 'secondary'
  if (s.startsWith('Berhasil'))       return 'success'
  if (s.startsWith('Tidak Berhasil')) return 'error'
  return 'warning'
}

function openAdd()         { editItem.value = null;        showDialog.value = true }
function openEdit(item)    { editItem.value = { ...item }; showDialog.value = true }
function confirmDelete()   {
  mockRecords.value = mockRecords.value.filter(r => r.id !== deleteTarget.value.id)
  showDeleteConfirm.value = false
}

function onSaved(data) {
  if (editItem.value) {
    const idx = mockRecords.value.findIndex(r => r.id === editItem.value.id)
    if (idx !== -1) mockRecords.value.splice(idx, 1, { ...editItem.value, ...data })
  } else {
    mockRecords.value.unshift({ id: Date.now(), ...data })
  }
  showDialog.value = false
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-5">
      <div>
        <h4 class="page-title">Up Selling</h4>
        <p class="text-body-2 text-medium-emphasis mb-0">Penawaran upgrade kelas kamar pasien rawat inap</p>
      </div>
      <VBtn color="success" rounded="lg" prepend-icon="ri-add-line" @click="openAdd">
        Input Up Selling
      </VBtn>
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
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-success))">{{ stats.berhasil }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Berhasil</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="text-center pa-3">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-error))">{{ stats.tidakBerhasil }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Tidak Berhasil</p>
        </VCard>
      </VCol>
      <VCol cols="6" sm="3">
        <VCard elevation="0" border rounded="lg" class="text-center pa-3">
          <p class="text-h5 font-weight-bold mb-0" style="color:rgb(var(--v-theme-warning))">{{ stats.pending }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Pending</p>
        </VCard>
      </VCol>
    </VRow>

    <!-- Table -->
    <VCard elevation="0" border rounded="lg">
      <div class="d-flex align-center justify-space-between px-4 py-3 flex-wrap gap-3">
        <div class="d-flex align-center gap-2">
          <VAvatar color="success" variant="tonal" size="36" rounded="lg">
            <VIcon icon="ri-arrow-up-circle-line" size="18" />
          </VAvatar>
          <div>
            <p class="text-subtitle-2 font-weight-bold mb-0">Data Up Selling</p>
            <p class="text-caption text-medium-emphasis mb-0">{{ mockRecords.length }} record</p>
          </div>
        </div>
        <VTextField
          v-model="search"
          placeholder="Cari pasien, ruangan..."
          prepend-inner-icon="ri-search-line"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          style="max-width: 260px"
        />
      </div>
      <VDivider />
      <VDataTable
        :headers="headers"
        :items="mockRecords"
        :search="search"
        density="comfortable"
        hover
        :items-per-page="10"
      >
        <template #item.ket_up_selling="{ item }">
          <VChip :color="ketColor(item.ket_up_selling)" size="small" variant="tonal" label>
            {{ item.ket_up_selling || '—' }}
          </VChip>
        </template>

        <template #item.kelas="{ item }">
          <VChip color="primary" size="x-small" variant="tonal">{{ item.kelas }}</VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex gap-1 justify-center">
            <VTooltip text="Edit">
              <template #activator="{ props: tp }">
                <VBtn v-bind="tp" icon size="x-small" variant="text" color="primary" @click="openEdit(item)">
                  <VIcon icon="ri-pencil-line" size="15" />
                </VBtn>
              </template>
            </VTooltip>
            <VTooltip text="Hapus">
              <template #activator="{ props: tp }">
                <VBtn v-bind="tp" icon size="x-small" variant="text" color="error"
                  @click="deleteTarget = item; showDeleteConfirm = true">
                  <VIcon icon="ri-delete-bin-line" size="15" />
                </VBtn>
              </template>
            </VTooltip>
          </div>
        </template>

        <template #no-data>
          <div class="text-center py-10 text-medium-emphasis">
            <VIcon icon="ri-arrow-up-circle-line" size="40" class="mb-2 opacity-40" />
            <p class="mb-0">Belum ada data up selling</p>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <UpSellingFormDialog v-model="showDialog" :edit-item="editItem" @saved="onSaved" />

    <!-- Delete confirm -->
    <VDialog v-model="showDeleteConfirm" max-width="360">
      <VCard rounded="lg">
        <VCardText class="pa-6 text-center">
          <VAvatar color="error" variant="tonal" size="56" rounded="xl" class="mb-4">
            <VIcon icon="ri-delete-bin-2-line" size="28" />
          </VAvatar>
          <h6 class="text-h6 font-weight-bold mb-2">Hapus Data?</h6>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Data <strong>{{ deleteTarget?.nama_pasien }}</strong> akan dihapus permanen.
          </p>
        </VCardText>
        <VCardActions class="px-6 pb-5 pt-0 gap-2">
          <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="showDeleteConfirm = false">Batal</VBtn>
          <VBtn color="error" rounded="lg" class="flex-grow-1" @click="confirmDelete">Hapus</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.page-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 2px; }
</style>
