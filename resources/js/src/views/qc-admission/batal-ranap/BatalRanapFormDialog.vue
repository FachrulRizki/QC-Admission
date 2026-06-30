<script setup>
/**
 * BatalRanapFormDialog
 *
 * Konsep integrasi Bed Management IGD:
 * ─────────────────────────────────────
 * Sistem Bed Management IGD yang existing biasanya punya endpoint REST atau
 * database yang bisa di-query. Ada 3 pendekatan:
 *
 * A) REST API (direkomendasikan):
 *    GET  /bed-management/api/beds?status=available&ruangan=IGD
 *    POST /bed-management/api/beds/{bed_id}/status  { status: 'occupied'|'available' }
 *    → Saat Batal Ranap diverifikasi OK, kita hit endpoint ini untuk set bed jadi 'available'
 *
 * B) Database langsung (jika satu server):
 *    Laravel bisa pakai multiple DB connections → query tabel bed IGD langsung
 *    config/database.php: tambah connection 'bed_igd'
 *
 * C) Webhook / Queue:
 *    Kirim event ke queue → worker update bed management async
 *
 * Di form ini: pilih ruangan → sistem fetch daftar bed yang occupied dari Bed Management
 * Saat status OK → trigger API update bed jadi available
 */
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useBatalRanapStore()

const form       = ref(initialForm())
const errorMsg   = ref('')
const successMsg = ref('')

// Mock daftar bed dari Bed Management IGD (nanti dari API)
const loadingBeds    = ref(false)
const availableBeds  = ref([])

const petugasOptions     = ['Nurul', 'AYU Putri Anisa', 'Reskim', 'Mulbagus Koyum', 'Abdul Hayyi']
const keteranganOpts     = 
[
'APS Alih RS Lain', 
'APS Rawat Jalan', 
'Saran Alih RS Lain', 
'Saran Konsul Poli', 
'Sisrute Tidak Dapat Kamar', 
'Batal Rawat',
'Kamar Penuh'
]
const OKOpts = ['Bedah','Non Bedah']
const ruanganIGDOpts     = ['IGD Umum', 'IGD Bedah', 'IGD Anak', 'IGD Kebidanan', 'Ruang Mawar', 'Ruang Anggrek', 'Ruang Dahlia', 'ICU', 'NICU', 'HCU']

// Mock bed data per ruangan (nanti dari GET /bed-management/api/beds)
const bedMockData = {
  'IGD Umum':    ['BED-IGD-01', 'BED-IGD-02', 'BED-IGD-03'],
  'ICU':         ['ICU-01', 'ICU-02'],
  'Ruang Mawar': ['MWR-01', 'MWR-02', 'MWR-03', 'MWR-04'],
  'Ruang Anggrek': ['AGR-01', 'AGR-02'],
  'NICU':        ['NICU-01', 'NICU-02'],
}

// NoReg options (nanti dari API pasien)
const noRegOptions = ref([
  { title: 'REG001 - ELLY MAYA, NY',      value: 'REG001', nama: 'ELLY MAYA, NY',     diagnosa: 'Hipertensi' },
  { title: 'REG002 - IDH SUBINGSEN, NY',  value: 'REG002', nama: 'IDH SUBINGSEN, NY', diagnosa: 'Diabetes' },
  { title: 'REG003 - RUSMINI, NY',        value: 'REG003', nama: 'RUSMINI, NY',       diagnosa: 'Stroke' },
  { title: 'REG004 - PUSPA SARI, AN',     value: 'REG004', nama: 'PUSPA SARI, AN',    diagnosa: 'ISPA' },
  { title: 'REG005 - BUDI SANTOSO, TN',   value: 'REG005', nama: 'BUDI SANTOSO, TN',  diagnosa: 'Gagal Jantung' },
])

function initialForm() {
  const now = new Date()
  const pad = n => String(n).padStart(2, '0')
  const jam = `${pad(now.getHours())}.${pad(now.getMinutes())}.${pad(now.getSeconds())}`
  const tgl = `${pad(now.getDate())}/${pad(now.getMonth()+1)}/${now.getFullYear()}, ${jam}`
  return {
    tanggal: tgl, jam_input: jam,
    no_reg: null, nama_pasien: '',
    keterangan_batal: null, status_ok: 'Pending',
    ketersediaan_kamar: '', diagnosa: '',
    note: '', petugas: null,
    ruangan: null, bed_id: null,
  }
}

watch(() => props.modelValue, open => {
  if (open) {
    form.value = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    availableBeds.value = []
    errorMsg.value = ''
    successMsg.value = ''
  }
})

// Auto-fill dari NoReg
watch(() => form.value.no_reg, val => {
  const found = noRegOptions.value.find(o => o.value === val)
  if (found) {
    form.value.nama_pasien = found.nama
    form.value.diagnosa    = found.diagnosa
  }
})

// Fetch bed dari Bed Management saat ruangan dipilih
watch(() => form.value.ruangan, async ruangan => {
  form.value.bed_id = null
  availableBeds.value = []
  if (!ruangan) return

  loadingBeds.value = true
  // TODO: Ganti dengan real API call:
  // const res = await axios.get(`/api/bed-management/beds`, { params: { ruangan, status: 'occupied' } })
  // availableBeds.value = res.data.beds.map(b => ({ title: `${b.bed_code} - ${b.pasien}`, value: b.bed_id }))

  // Mock simulasi delay API
  await new Promise(r => setTimeout(r, 400))
  const mockBeds = bedMockData[ruangan] ?? ['BED-01', 'BED-02']
  availableBeds.value = mockBeds.map(b => ({ title: b, value: b }))
  loadingBeds.value = false
})

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg)            { errorMsg.value = 'No. Registrasi wajib dipilih.'; return }
  if (!form.value.keterangan_batal)  { errorMsg.value = 'Keterangan batal wajib dipilih.'; return }
  if (!form.value.petugas)           { errorMsg.value = 'Petugas wajib dipilih.'; return }

  // Jika status OK dan ada bed_id → trigger update Bed Management
  if (form.value.status_ok === 'OK' && form.value.bed_id) {
    await updateBedManagement(form.value.ruangan, form.value.bed_id, 'available')
  }

  const result = props.editItem
    ? await store.update(props.editItem.id, form.value)
    : await store.store(form.value)

  if (result?.success ?? true) {
    successMsg.value = 'Data berhasil disimpan!'
    emit('saved', form.value)
    setTimeout(() => close(), 500)
  } else {
    errorMsg.value = result?.message ?? 'Gagal menyimpan.'
  }
}

/**
 * Update status bed di Bed Management IGD.
 * Saat ini mock — ganti dengan real API call.
 *
 * Opsi integrasi:
 * 1. Direct API: POST ke endpoint Bed Management existing
 * 2. Via Laravel backend: POST /api/bed-management/update-status
 *    (Laravel forward ke Bed Management, dengan auth token tersimpan di config)
 */
async function updateBedManagement(ruangan, bedId, newStatus) {
  try {
    // TODO: uncomment saat API Bed Management tersedia
    // await axios.post('/api/bed-management/update-status', {
    //   bed_id: bedId,
    //   ruangan: ruangan,
    //   status: newStatus,   // 'available' | 'occupied'
    //   timestamp: new Date().toISOString(),
    // })
    console.log(`[Bed Management] ${ruangan} - ${bedId} → ${newStatus}`)
  } catch (e) {
    console.warn('Bed Management API tidak dapat dihubungi:', e.message)
    // Tidak block user — log saja, data tetap disimpan lokal
  }
}

function close() { emit('update:modelValue', false) }
</script>

<template>
  <VDialog :model-value="modelValue" max-width="540" persistent scrollable @update:model-value="close">
    <VCard rounded="lg">

      <!-- Header -->
      <div class="dialog-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="error" variant="tonal" size="40" rounded="lg">
          <VIcon icon="ri-close-circle-line" size="20" />
        </VAvatar>
        <div class="flex-grow-1">
          <p class="text-subtitle-1 font-weight-bold mb-0">
            {{ editItem ? 'Edit Batal Ranap' : 'Input Pasien Batal Ranap' }}
          </p>
          <p class="text-caption text-medium-emphasis mb-0">Pencatatan pembatalan rawat inap</p>
        </div>
        <VBtn icon variant="text" size="small" @click="close">
          <VIcon icon="ri-close-line" />
        </VBtn>
      </div>
      <VDivider />

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">{{ successMsg }}</VAlert>

        <VForm @submit.prevent="handleSave">

          <!-- Tanggal + Jam (readonly) -->
          <VRow dense class="mb-2">
            <VCol cols="7">
              <VTextField v-model="form.tanggal" label="Tanggal" variant="outlined" density="compact" readonly prepend-inner-icon="ri-calendar-line" bg-color="grey-100" />
            </VCol>
            <VCol cols="5">
              <VTextField v-model="form.jam_input" label="Jam Input" variant="outlined" density="compact" readonly prepend-inner-icon="ri-time-line" bg-color="grey-100" />
            </VCol>
          </VRow>

          <!-- NoReg autocomplete -->
          <div class="mb-3">
            <VAutocomplete
              v-model="form.no_reg"
              :items="noRegOptions"
              item-title="title"
              item-value="value"
              label="No. Registrasi *"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-search-line"
              clearable
            />
          </div>

          <!-- Nama Pasien (auto-fill) -->
          <div class="mb-3">
            <VTextField v-model="form.nama_pasien" label="Nama Pasien" variant="outlined" density="compact" prepend-inner-icon="ri-user-3-line" bg-color="grey-100" readonly />
          </div>

          <!-- Diagnosa (auto-fill atau manual) -->
          <div class="mb-3">
            <VTextField v-model="form.diagnosa" label="Diagnosa" variant="outlined" density="compact" prepend-inner-icon="ri-stethoscope-line" />
          </div>

          <!-- Keterangan Batal -->
          <div class="mb-3">
            <VSelect
              v-model="form.keterangan_batal"
              :items="keteranganOpts"
              label="Keterangan Batal *"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-close-circle-line"
              clearable
            />
          </div>

          <!-- Status OK -->
          <div class="mb-3">
            <VSelect
              v-model="form.statusOK"
              :items="OKOpts"
              label="Status OK *"
              variant="outlined"
              density="compact"
              clearable
            />
          </div>

          <VDivider class="mb-4">
            <span class="text-caption text-medium-emphasis px-2">
              <VIcon icon="ri-hospital-line" size="13" class="me-1" />
              Integrasi Bed Management IGD
            </span>
          </VDivider>

          <!-- Ruangan (trigger fetch bed) -->
          <div class="mb-3">
            <VSelect
              v-model="form.ruangan"
              :items="ruanganIGDOpts"
              label="Ruangan / Unit"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-building-line"
              clearable
            />
          </div>

          <!-- Bed ID (dari Bed Management) -->
          <div class="mb-3">
            <VSelect
              v-model="form.bed_id"
              :items="availableBeds"
              item-title="title"
              item-value="value"
              label="No. Bed (dari Bed Management IGD)"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-hotel-bed-line"
              clearable
              :loading="loadingBeds"
              :disabled="!form.ruangan || loadingBeds"
              :placeholder="form.ruangan ? 'Pilih bed...' : 'Pilih ruangan dulu'"
              no-data-text="Tidak ada bed tersedia di ruangan ini"
            />
          </div>

          <!-- Info Bed Management -->
          <VAlert
            v-if="form.ruangan"
            :type="form.bed_id ? 'success' : 'info'"
            variant="tonal"
            density="compact"
            class="mb-3"
          >
            <div class="text-caption">
              <template v-if="form.bed_id">
                <strong>{{ form.ruangan }} · {{ form.bed_id }}</strong> — saat status diset OK, bed ini akan diupdate ke <strong>Tersedia</strong> di sistem Bed Management IGD.
              </template>
              <template v-else>
                Pilih No. Bed untuk menghubungkan dengan Bed Management IGD.
                Data bed di-fetch dari endpoint: <code>GET /bed-management/api/beds?ruangan={{ form.ruangan }}</code>
              </template>
            </div>
          </VAlert>

          <!-- Ketersediaan Kamar -->
          <div class="mb-3">
            <VTextField v-model="form.ketersediaan_kamar" label="Ketersediaan Kamar" variant="outlined" density="compact" />
          </div>

          <!-- Note -->
          <div class="mb-3">
            <VTextField v-model="form.note" label="Note" variant="outlined" density="compact" prepend-inner-icon="ri-sticky-note-line" />
          </div>

          <!-- Petugas -->
          <div class="mb-3">
            <VAutocomplete v-model="form.petugas" :items="petugasOptions" label="Petugas *" variant="outlined" density="compact" prepend-inner-icon="ri-nurse-line" clearable />
          </div>

          <!-- Status OK -->
          <div class="mb-1">
            <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">Status Verifikasi</p>
            <VBtnToggle v-model="form.status_ok" mandatory rounded="lg" color="primary" density="compact" class="w-100">
              <VBtn value="Pending" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-time-line" size="14" class="me-1" />Pending
              </VBtn>
              <VBtn value="OK" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-check-line" size="14" class="me-1" />OK
              </VBtn>
              <VBtn value="Ditolak" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-close-line" size="14" class="me-1" />Ditolak
              </VBtn>
            </VBtnToggle>
          </div>

          <!-- Peringatan jika OK tapi tidak ada bed -->
          <VAlert
            v-if="form.status_ok === 'OK' && !form.bed_id"
            type="warning"
            variant="tonal"
            density="compact"
            class="mt-3"
          >
            <div class="text-caption">
              Status OK tanpa memilih bed — update Bed Management tidak akan dikirim. Pilih ruangan dan bed untuk sinkronisasi otomatis.
            </div>
          </VAlert>

        </VForm>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Batal</VBtn>
        <VBtn color="error" rounded="lg" class="flex-grow-1" :loading="store.loading" prepend-icon="ri-save-line" @click="handleSave">
          Simpan
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-error), 0.04); }
</style>
