<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'
import { usePasienStore } from '@/stores/usePasienStore'
import SignaturePad from '@/components/SignaturePad.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useQualityControlStore()
const pegawaiStore = usePegawaiStore()
const pasienStore  = usePasienStore()

const form       = ref(initialForm())
const sigRef     = ref(null)
const errorMsg   = ref('')
const successMsg = ref('')

// ── Search no_reg ─────────────────────────────────────────────────────────────
const noRegSearch   = ref('')
const noRegLoading  = ref(false)
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

// Auto-fill saat no_reg dipilih
watch(() => form.value.no_reg, async (val) => {
  if (!val) return
  const found = pasienStore.results.find(p => p.no_reg === val)
    ?? (await pasienStore.lookup(val))
  if (found) {
    form.value.no_mr       = found.no_mr
    form.value.nama_pasien = found.nama_pasien
    form.value.jaminan     = found.ket_bayar
  }
})

// ── Master options dari store ─────────────────────────────────────────────────
const noteOptions = [
  'Kelas 1 Bedah Laki-laki','Kelas 2 Bedah Laki-laki','Kelas 3 Bedah Laki-laki',
  'Kelas 1 Bedah Perempuan','Kelas 2 Bedah Perempuan','Kelas 3 Bedah Perempuan',
  'Kelas 1 Internis Laki-laki','Kelas 2 Internis Laki-laki','Kelas 3 Internis Laki-laki',
  'Kelas 1 Internis Perempuan','Kelas 2 Internis Perempuan','Kelas 3 Internis Perempuan',
  'Kelas 1 Onkologi Laki-laki','Kelas 2 Onkologi Laki-laki','Kelas 3 Onkologi Laki-laki',
  'Kelas 1 Onkologi Perempuan','Kelas 2 Onkologi Perempuan','Kelas 3 Onkologi Perempuan',
  'Kelas 1 Kebidanan','Kelas 2 Kebidanan','Kelas 3 Kebidanan',
  'Kelas 1 Anak','Kelas 2 Anak','Kelas 3 Anak','Kelas VIP',
]
const statusKetOptions = ['Belum Dapat Kamar','Antri Kamar','Sudah Dapat Kamar']

// ── Timer durasi tunggu ───────────────────────────────────────────────────────
let durasiTimer = null

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value   = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    errorMsg.value   = ''
    successMsg.value = ''
    pasienStore.clear()
    noRegSearch.value = ''
    pegawaiStore.fetch()
    if (!props.editItem) durasiTimer = setInterval(updateDurasi, 1000)
  } else {
    clearInterval(durasiTimer)
  }
})

function updateDurasi() {
  try {
    const startStr  = form.value.tgl_daftar
    if (!startStr) return
    const parts     = startStr.split(', ')
    const dateParts = parts[0].split('/')
    const timeParts = (parts[1] ?? '00.00.00').split('.')
    const start = new Date(
      parseInt(dateParts[2]), parseInt(dateParts[1]) - 1, parseInt(dateParts[0]),
      parseInt(timeParts[0]), parseInt(timeParts[1]), parseInt(timeParts[2]),
    )
    const diff = Math.max(0, Math.floor((Date.now() - start.getTime()) / 1000))
    const h = String(Math.floor(diff / 3600)).padStart(2,'0')
    const m = String(Math.floor((diff % 3600) / 60)).padStart(2,'0')
    const s = String(diff % 60).padStart(2,'0')
    form.value.durasi_tunggu = `${h}:${m}:${s}`
  } catch {}
}

function initialForm() {
  const now = new Date()
  return {
    tanggal:'', jam_input:'', tgl_daftar: formatDatetime(now), jam_daftar: formatTime(now),
    no_mr:'', no_reg:null, nama_pasien:'', jaminan:'', status_ket:'', edukasi_kamar:'',
    durasi_tunggu:'00:00:00', note:null, petugas:null, status:'Edukasi',
    keluarga_pasien:'', ttd_keluarga_pasien:'',
  }
}

function formatDatetime(d) {
  const p = n => String(n).padStart(2,'0')
  return `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}
function formatTime(d) {
  const p = n => String(n).padStart(2,'0')
  return `${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg)  { errorMsg.value = 'NoReg wajib dipilih.'; return }
  if (!form.value.petugas) { errorMsg.value = 'Petugas wajib dipilih.'; return }
  if (!form.value.status)  { errorMsg.value = 'Status wajib dipilih.'; return }

  const now = new Date()
  const payload = {
    ...form.value,
    tanggal:   formatDatetime(now),
    jam_input: formatTime(now),
  }

  const result = props.editItem
    ? await store.update(props.editItem.id, payload)
    : await store.store(payload)

  if (result.success) {
    successMsg.value = 'Data berhasil disimpan!'
    emit('saved', payload)
    setTimeout(() => close(), 600)
  } else {
    errorMsg.value = result.message ?? 'Gagal menyimpan data.'
  }
}

function close() { clearInterval(durasiTimer); emit('update:modelValue', false) }
</script>

<template>
  <VDialog :model-value="modelValue" max-width="560" persistent scrollable @update:model-value="close">
    <VCard rounded="lg">
      <!-- Header -->
      <div class="dialog-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="primary" variant="tonal" size="40" rounded="lg">
          <VIcon icon="ri-shield-check-line" size="20" />
        </VAvatar>
        <div class="flex-grow-1">
          <p class="text-subtitle-1 font-weight-bold mb-0">{{ editItem ? 'Edit Quality Control' : 'Input Quality Control' }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Form entry data QC admisi</p>
        </div>
        <VBtn icon variant="text" size="small" @click="close"><VIcon icon="ri-close-line" /></VBtn>
      </div>
      <VDivider />

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">{{ successMsg }}</VAlert>

        <VForm @submit.prevent="handleSave">
          <!-- Tanggal + Jam -->
          <VRow dense class="mb-1">
            <VCol cols="7">
              <VTextField v-model="form.tgl_daftar" label="Tanggal Daftar" variant="outlined" density="compact" readonly prepend-inner-icon="ri-calendar-line" />
            </VCol>
            <VCol cols="5">
              <VTextField v-model="form.jam_daftar" label="Jam" variant="outlined" density="compact" readonly prepend-inner-icon="ri-time-line" />
            </VCol>
          </VRow>

          <!-- No. Reg — search real time -->
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
              placeholder="Ketik No. Reg / No. MR / Nama Pasien..."
              no-data-text="Ketik min. 2 karakter untuk mencari..."
            />
          </div>

          <!-- No MR + Nama -->
          <VRow dense class="mb-1">
            <VCol cols="4">
              <VTextField v-model="form.no_mr" label="No. MR" variant="outlined" density="compact" readonly bg-color="grey-lighten-4" />
            </VCol>
            <VCol cols="8">
              <VTextField v-model="form.nama_pasien" label="Nama Pasien" variant="outlined" density="compact" readonly bg-color="grey-lighten-4" />
            </VCol>
          </VRow>

          <!-- Jaminan + Status Ket -->
          <VRow dense class="mb-1">
            <VCol cols="5">
              <VTextField v-model="form.jaminan" label="Jaminan" variant="outlined" density="compact" readonly bg-color="grey-lighten-4" />
            </VCol>
            <VCol cols="7">
              <VSelect v-model="form.status_ket" :items="statusKetOptions" label="Status Keterangan" variant="outlined" density="compact" clearable />
            </VCol>
          </VRow>

          <!-- Edukasi Kamar -->
          <div class="mb-3">
            <VTextField v-model="form.edukasi_kamar" label="Edukasi Kamar / Ruangan" variant="outlined" density="compact" prepend-inner-icon="ri-hospital-line" />
          </div>

          <!-- Durasi Tunggu -->
          <div class="mb-3">
            <VTextField v-model="form.durasi_tunggu" label="Durasi Tunggu (auto)" variant="outlined" density="compact" readonly prepend-inner-icon="ri-timer-flash-line">
              <template #append-inner>
                <VChip :color="form.durasi_tunggu > '02:00:00' ? 'error' : 'success'" size="x-small" variant="tonal">
                  {{ form.durasi_tunggu > '02:00:00' ? 'Lama' : 'Normal' }}
                </VChip>
              </template>
            </VTextField>
          </div>

          <!-- Note + Petugas -->
          <VRow dense class="mb-1">
            <VCol cols="6">
              <VSelect v-model="form.note" :items="noteOptions" label="Note" variant="outlined" density="compact" clearable />
            </VCol>
            <VCol cols="6">
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
            </VCol>
          </VRow>

          <!-- Status -->
          <div class="mb-4">
            <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">Status</p>
            <VBtnToggle v-model="form.status" mandatory rounded="lg" color="primary" density="compact" class="w-100">
              <VBtn value="Edukasi" class="flex-grow-1" variant="outlined">Edukasi</VBtn>
              <VBtn value="Edukasi lanjutan" class="flex-grow-1" variant="outlined">Edukasi Lanjutan</VBtn>
              <VBtn value="Masuk" class="flex-grow-1" variant="outlined">Masuk</VBtn>
            </VBtnToggle>
          </div>

          <VDivider class="mb-4" />

          <!-- Keluarga + TTD -->
          <div class="mb-3">
            <VTextField v-model="form.keluarga_pasien" label="Nama Keluarga Pasien" variant="outlined" density="compact" prepend-inner-icon="ri-group-line" />
          </div>
          <SignaturePad v-model="form.ttd_keluarga_pasien" label="Tanda Tangan Keluarga Pasien" :height="160" />
        </VForm>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Batal</VBtn>
        <VBtn color="primary" rounded="lg" class="flex-grow-1" prepend-icon="ri-save-line" :loading="store.loading" @click="handleSave">Simpan</VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-primary), 0.05); }
</style>
