<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useBatalRanapStore()

const form      = ref(initialForm())
const errorMsg  = ref('')
const successMsg = ref('')

const petugasOptions       = ['Nurul', 'AYU Putri Anisa', 'Reskim', 'Mulbagus Koyum', 'Abdul Hayyi']
const keteranganBatalOpts  = ['Kamar Penuh', 'Pasien Menolak', 'DPJP Tidak Setuju', 'Keluarga Menolak', 'Kondisi Membaik']
const ruanganOptions       = ['Ruang Mawar', 'Ruang Anggrek', 'Ruang Dahlia', 'ICU', 'NICU', 'HCU']

function initialForm() {
  const now = new Date()
  const pad = n => String(n).padStart(2, '0')
  const tgl = `${pad(now.getDate())}/${pad(now.getMonth()+1)}/${now.getFullYear()}, ${pad(now.getHours())}.${pad(now.getMinutes())}.${pad(now.getSeconds())}`
  const jam = `${pad(now.getHours())}.${pad(now.getMinutes())}.${pad(now.getSeconds())}`
  return {
    tanggal: tgl, jam_input: jam,
    no_reg: '', nama_pasien: '',
    keterangan_batal: null, status_ok: 'Pending',
    ketersediaan_kamar: '', diagnosa: '',
    note: '', petugas: null,
    ruangan: null, bed_id: '',
  }
}

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value   = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    errorMsg.value = ''
    successMsg.value = ''
  }
})

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg?.trim())         { errorMsg.value = 'No. Registrasi wajib diisi.'; return }
  if (!form.value.keterangan_batal)       { errorMsg.value = 'Keterangan batal wajib dipilih.'; return }
  if (!form.value.petugas)                { errorMsg.value = 'Petugas wajib dipilih.'; return }

  const result = props.editItem
    ? await store.update(props.editItem.id, form.value)
    : await store.store(form.value)

  if (result?.success ?? true) {
    successMsg.value = 'Data berhasil disimpan!'
    emit('saved', form.value)
    setTimeout(() => close(), 600)
  } else {
    errorMsg.value = result?.message ?? 'Gagal menyimpan.'
  }
}

function close() { emit('update:modelValue', false) }
</script>

<template>
  <VDialog :model-value="modelValue" max-width="520" persistent scrollable @update:model-value="close">
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
        <VBtn icon variant="text" size="small" @click="close"><VIcon icon="ri-close-line" /></VBtn>
      </div>
      <VDivider />

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg = ''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">{{ successMsg }}</VAlert>

        <VForm @submit.prevent="handleSave">
          <!-- Tanggal + jam (readonly) -->
          <VRow dense class="mb-2">
            <VCol cols="7">
              <VTextField v-model="form.tanggal" label="Tanggal" variant="outlined" density="compact" readonly prepend-inner-icon="ri-calendar-line" />
            </VCol>
            <VCol cols="5">
              <VTextField v-model="form.jam_input" label="Jam" variant="outlined" density="compact" readonly prepend-inner-icon="ri-time-line" />
            </VCol>
          </VRow>

          <!-- No Reg + Nama -->
          <VRow dense class="mb-2">
            <VCol cols="5">
              <VTextField v-model="form.no_reg" label="No. Registrasi *" variant="outlined" density="compact" prepend-inner-icon="ri-id-card-line" />
            </VCol>
            <VCol cols="7">
              <VTextField v-model="form.nama_pasien" label="Nama Pasien" variant="outlined" density="compact" prepend-inner-icon="ri-user-3-line" />
            </VCol>
          </VRow>

          <!-- Keterangan Batal -->
          <div class="mb-3">
            <VSelect
              v-model="form.keterangan_batal"
              :items="keteranganBatalOpts"
              label="Keterangan Batal *"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-close-circle-line"
              clearable
            />
          </div>

          <!-- Diagnosa -->
          <div class="mb-3">
            <VTextField v-model="form.diagnosa" label="Diagnosa" variant="outlined" density="compact" prepend-inner-icon="ri-stethoscope-line" />
          </div>

          <!-- Ruangan + Ketersediaan Kamar -->
          <VRow dense class="mb-2">
            <VCol cols="6">
              <VSelect v-model="form.ruangan" :items="ruanganOptions" label="Ruangan" variant="outlined" density="compact" clearable />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="form.ketersediaan_kamar" label="Ketersediaan Kamar" variant="outlined" density="compact" />
            </VCol>
          </VRow>

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
            <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">Status</p>
            <VBtnToggle v-model="form.status_ok" mandatory rounded="lg" color="primary" density="compact" class="w-100">
              <VBtn value="Pending" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-time-line" class="me-1" size="15" />Pending
              </VBtn>
              <VBtn value="OK" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-check-line" class="me-1" size="15" />OK
              </VBtn>
              <VBtn value="Ditolak" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-close-line" class="me-1" size="15" />Ditolak
              </VBtn>
            </VBtnToggle>
          </div>

          <!-- BED Management info box -->
          <VAlert type="info" variant="tonal" density="compact" class="mt-3" border="start">
            <div class="text-caption">
              <strong>Integrasi Bed Management:</strong>
              Status bed ruangan akan diupdate otomatis via API Bed Management IGD saat status diset ke "OK".
              <span v-if="form.ruangan" class="d-block mt-1">
                Ruangan: <strong>{{ form.ruangan }}</strong>
                <span v-if="form.bed_id"> · Bed: <strong>{{ form.bed_id }}</strong></span>
              </span>
            </div>
          </VAlert>
        </VForm>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Batal</VBtn>
        <VBtn color="error" rounded="lg" class="flex-grow-1" :loading="store.loading" prepend-icon="ri-save-line" @click="handleSave">Simpan</VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-error), 0.04); }
</style>
