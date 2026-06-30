<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'
import { usePasienStore } from '@/stores/usePasienStore'
import axios from 'axios'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useBatalRanapStore()
const pegawaiStore = usePegawaiStore()
const pasienStore  = usePasienStore()

const form         = ref(initialForm())
const errorMsg     = ref('')
const successMsg   = ref('')
const loadingBeds  = ref(false)
const availableBeds = ref([])

// ── Master options ────────────────────────────────────────────────────────────
const keteranganOpts = [
  'APS Alih RS Lain','APS Rawat Jalan','Saran Alih RS Lain','Saran Konsul Poli',
  'Sisrute Tidak Dapat Kamar','Batal Rawat','Kamar Penuh',
  'Pasien Menolak','DPJP Tidak Setuju','Keluarga Menolak','Kondisi Membaik',
]
const OKOpts       = ['Bedah','Non Bedah']
const ruanganOpts  = [
  'IGD Umum','IGD Bedah','IGD Anak','IGD Kebidanan',
  'Ruang Mawar','Ruang Anggrek','Ruang Dahlia','Ruang Flamboyan',
  'ICU','NICU','HCU','PICU',
]

// ── Search no_reg ─────────────────────────────────────────────────────────────
const noRegSearch  = ref('')
const noRegLoading = ref(false)
let   searchDebounce = null

watch(noRegSearch, (val) => {
  clearTimeout(searchDebounce)
  if (!val || val.length < 2) return
  noRegLoading.value = true
  searchDebounce = setTimeout(async () => {
    await pasienStore.search(val)
    noRegLoading.value = false
  }, 350)
})

watch(() => form.value.no_reg, async (val) => {
  if (!val) return
  const found = pasienStore.results.find(p => p.no_reg === val)
    ?? (await pasienStore.lookup(val))
  if (found) {
    form.value.nama_pasien = found.nama_pasien
    form.value.diagnosa    = found.keterangan ?? ''
  }
})

// Fetch beds saat ruangan dipilih
watch(() => form.value.ruangan, async (ruangan) => {
  form.value.bed_id = null
  availableBeds.value = []
  if (!ruangan) return
  loadingBeds.value = true
  try {
    const { data } = await axios.get('/api/bed-management/beds', { params: { ruangan } })
    availableBeds.value = (data.beds ?? []).map(b => ({ title: b.bed_code ?? b.bed_id, value: b.bed_id }))
  } catch {
    availableBeds.value = [{ title: 'BED-01', value: 'BED-01' },{ title: 'BED-02', value: 'BED-02' }]
  } finally {
    loadingBeds.value = false
  }
})

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value       = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    availableBeds.value = []
    errorMsg.value   = ''
    successMsg.value = ''
    pasienStore.clear()
    noRegSearch.value = ''
    pegawaiStore.fetch()
  }
})

function initialForm() {
  const now = new Date()
  const p   = n => String(n).padStart(2,'0')
  const jam = `${p(now.getHours())}.${p(now.getMinutes())}.${p(now.getSeconds())}`
  const tgl = `${p(now.getDate())}/${p(now.getMonth()+1)}/${now.getFullYear()}, ${jam}`
  return {
    tanggal: tgl, jam_input: jam,
    no_reg: null, nama_pasien: '',
    keterangan_batal: null, status_ok: 'Pending',
    ketersediaan_kamar: '', diagnosa: '',
    note: '', petugas: null,
    ruangan: null, bed_id: null,
  }
}

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg)           { errorMsg.value = 'No. Registrasi wajib dipilih.'; return }
  if (!form.value.keterangan_batal) { errorMsg.value = 'Keterangan batal wajib dipilih.'; return }
  if (!form.value.petugas)          { errorMsg.value = 'Petugas wajib dipilih.'; return }

  if (form.value.status_ok === 'Bedah' && form.value.bed_id) {
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

async function updateBedManagement(ruangan, bedId, status) {
  try {
    await axios.post('/api/bed-management/update-status', { bed_id: bedId, ruangan, status })
  } catch (e) {
    console.warn('Bed Management API error:', e.message)
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
          <p class="text-subtitle-1 font-weight-bold mb-0">{{ editItem ? 'Edit Batal Ranap' : 'Input Pasien Batal Ranap' }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Pencatatan pembatalan rawat inap</p>
        </div>
        <VBtn icon variant="text" size="small" @click="close"><VIcon icon="ri-close-line" /></VBtn>
      </div>
      <VDivider />

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg"   type="error"   variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">{{ successMsg }}</VAlert>

        <VForm @submit.prevent="handleSave">
          <!-- Tanggal + Jam -->
          <VRow dense class="mb-2">
            <VCol cols="7">
              <VTextField v-model="form.tanggal"   label="Tanggal"   variant="outlined" density="compact" readonly prepend-inner-icon="ri-calendar-line" bg-color="grey-100" />
            </VCol>
            <VCol cols="5">
              <VTextField v-model="form.jam_input" label="Jam Input" variant="outlined" density="compact" readonly prepend-inner-icon="ri-time-line" bg-color="grey-100" />
            </VCol>
          </VRow>

          <!-- No. Reg search -->
          <div class="mb-3">
            <VAutocomplete
              v-model="form.no_reg"
              v-model:search="noRegSearch"
              :items="pasienStore.optionList"
              item-title="title"
              item-value="value"
              label="No. Registrasi *"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-search-line"
              clearable
              no-filter
              :loading="noRegLoading || pasienStore.loading"
              placeholder="Ketik No. Reg / No. MR / Nama..."
              no-data-text="Ketik min. 2 karakter untuk mencari..."
            />
          </div>

          <!-- Nama Pasien (auto-fill) -->
          <div class="mb-3">
            <VTextField v-model="form.nama_pasien" label="Nama Pasien" variant="outlined" density="compact" prepend-inner-icon="ri-user-3-line" bg-color="grey-100" readonly />
          </div>

          <!-- Diagnosa -->
          <div class="mb-3">
            <VTextField v-model="form.diagnosa" label="Diagnosa" variant="outlined" density="compact" prepend-inner-icon="ri-stethoscope-line" />
          </div>

          <!-- Keterangan Batal -->
          <div class="mb-3">
            <VSelect v-model="form.keterangan_batal" :items="keteranganOpts" label="Keterangan Batal *" variant="outlined" density="compact" prepend-inner-icon="ri-close-circle-line" clearable />
          </div>

          <!-- Status OK -->
          <div class="mb-3">
            <VSelect v-model="form.status_ok" :items="OKOpts" label="Status OK" variant="outlined" density="compact" clearable />
          </div>

          <VDivider class="mb-4">
            <span class="text-caption text-medium-emphasis px-2">
              <VIcon icon="ri-hospital-line" size="13" class="me-1" />Integrasi Bed Management IGD
            </span>
          </VDivider>

          <!-- Ruangan -->
          <div class="mb-3">
            <VSelect v-model="form.ruangan" :items="ruanganOpts" label="Ruangan / Unit" variant="outlined" density="compact" prepend-inner-icon="ri-building-line" clearable />
          </div>

          <!-- Bed ID -->
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
              no-data-text="Tidak ada bed tersedia"
            />
          </div>

          <!-- Note -->
          <div class="mb-3">
            <VTextField v-model="form.note" label="Note" variant="outlined" density="compact" prepend-inner-icon="ri-sticky-note-line" />
          </div>

          <!-- Petugas -->
          <div class="mb-3">
            <VAutocomplete
              v-model="form.petugas"
              :items="pegawaiStore.namaList"
              label="Petugas *"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-nurse-line"
              clearable
              :loading="pegawaiStore.loading"
              no-data-text="Memuat petugas..."
            />
          </div>
        </VForm>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Batal</VBtn>
        <VBtn color="error" rounded="lg" class="flex-grow-1" prepend-icon="ri-save-line" :loading="store.loading" @click="handleSave">Simpan</VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-error), 0.05); }
</style>
