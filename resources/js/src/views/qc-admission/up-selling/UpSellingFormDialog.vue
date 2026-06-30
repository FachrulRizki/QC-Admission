<script setup>
import { useUpSellingStore } from '@/stores/useUpSellingStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useUpSellingStore()

const form      = ref(initialForm())
const errorMsg  = ref('')
const successMsg = ref('')

const petugasOptions   = ['Nurul', 'AYU Putri Anisa', 'Reskim', 'Mulbagus Koyum', 'Abdul Hayyi']
const kelasList        = ['VIP', 'Kelas 1', 'Kelas 2', 'Kelas 3']
const alasanList       = ['Budget terbatas', 'Tidak ada kamar kelas lebih tinggi', 'Keinginan keluarga', 'Rekomendasi dokter', 'Lainnya']
const noRegOptions     = [
  { title: 'REG001 - ELLY MAYA, NY',       value: 'REG001', nama: 'ELLY MAYA, NY',       jaminan: 'BPJS' },
  { title: 'REG002 - IDH SUBINGSEN, NY',   value: 'REG002', nama: 'IDH SUBINGSEN, NY',   jaminan: 'BPJS' },
  { title: 'REG003 - RUSMINI, NY',         value: 'REG003', nama: 'RUSMINI, NY',         jaminan: 'Umum' },
  { title: 'REG004 - PUSPA SARI, AN',      value: 'REG004', nama: 'PUSPA SARI, AN',      jaminan: 'BPJS' },
]

function initialForm() {
  const now = new Date()
  const pad = n => String(n).padStart(2, '0')
  return {
    tanggal:           `${pad(now.getDate())}/${pad(now.getMonth()+1)}/${now.getFullYear()}, ${pad(now.getHours())}.${pad(now.getMinutes())}.${pad(now.getSeconds())}`,
    jam_input:         `${pad(now.getHours())}.${pad(now.getMinutes())}.${pad(now.getSeconds())}`,
    no_reg:            null,
    nama_pasien:       '',
    jaminan:           '',
    rekomendasi_kelas: null,
    kelas_diambil:     null,
    alasan:            null,
    petugas:           null,
    status:            'Pending',
    note:              '',
  }
}

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value   = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    errorMsg.value = ''
    successMsg.value = ''
  }
})

watch(() => form.value.no_reg, (val) => {
  const found = noRegOptions.find(o => o.value === val)
  if (found) {
    form.value.nama_pasien = found.nama
    form.value.jaminan     = found.jaminan
  }
})

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg)           { errorMsg.value = 'No. Registrasi wajib dipilih.'; return }
  if (!form.value.petugas)          { errorMsg.value = 'Petugas wajib dipilih.'; return }
  if (!form.value.rekomendasi_kelas) { errorMsg.value = 'Kelas rekomendasi wajib dipilih.'; return }

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
  <VDialog :model-value="modelValue" max-width="480" persistent scrollable @update:model-value="close">
    <VCard rounded="lg">
      <div class="dialog-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="success" variant="tonal" size="40" rounded="lg">
          <VIcon icon="ri-shopping-bag-3-line" size="20" />
        </VAvatar>
        <div class="flex-grow-1">
          <p class="text-subtitle-1 font-weight-bold mb-0">{{ editItem ? 'Edit Up Selling' : 'Input Up Selling' }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Penawaran upgrade kelas kamar</p>
        </div>
        <VBtn icon variant="text" size="small" @click="close"><VIcon icon="ri-close-line" /></VBtn>
      </div>
      <VDivider />

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg = ''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">{{ successMsg }}</VAlert>

        <VForm @submit.prevent="handleSave">
          <!-- Tanggal (readonly) -->
          <VTextField v-model="form.tanggal" label="Tanggal Input" variant="outlined" density="compact" readonly prepend-inner-icon="ri-calendar-line" class="mb-3" />

          <!-- No Reg -->
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
            class="mb-3"
          />

          <!-- Nama Pasien + Jaminan -->
          <VRow dense class="mb-2">
            <VCol cols="7">
              <VTextField v-model="form.nama_pasien" label="Nama Pasien" variant="outlined" density="compact" readonly bg-color="grey-lighten-4" />
            </VCol>
            <VCol cols="5">
              <VTextField v-model="form.jaminan" label="Jaminan" variant="outlined" density="compact" readonly bg-color="grey-lighten-4" />
            </VCol>
          </VRow>

          <!-- Kelas -->
          <VRow dense class="mb-2">
            <VCol cols="6">
              <VSelect v-model="form.rekomendasi_kelas" :items="kelasList" label="Rek. Kelas *" variant="outlined" density="compact" clearable />
            </VCol>
            <VCol cols="6">
              <VSelect v-model="form.kelas_diambil" :items="kelasList" label="Kelas Diambil" variant="outlined" density="compact" clearable />
            </VCol>
          </VRow>

          <!-- Alasan -->
          <VSelect v-model="form.alasan" :items="alasanList" label="Alasan" variant="outlined" density="compact" clearable class="mb-3" />

          <!-- Petugas -->
          <VAutocomplete v-model="form.petugas" :items="petugasOptions" label="Petugas *" variant="outlined" density="compact" prepend-inner-icon="ri-nurse-line" clearable class="mb-3" />

          <!-- Status -->
          <div class="mb-3">
            <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">Status</p>
            <VBtnToggle v-model="form.status" mandatory rounded="lg" color="primary" density="compact" class="w-100">
              <VBtn value="Pending" class="flex-grow-1" variant="outlined">Pending</VBtn>
              <VBtn value="Berhasil" class="flex-grow-1" variant="outlined">Berhasil</VBtn>
              <VBtn value="Tidak Berhasil" class="flex-grow-1" variant="outlined">Tidak Berhasil</VBtn>
            </VBtnToggle>
          </div>

          <!-- Note -->
          <VTextField v-model="form.note" label="Note" variant="outlined" density="compact" />
        </VForm>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Batal</VBtn>
        <VBtn color="success" rounded="lg" class="flex-grow-1" :loading="store.loading" prepend-icon="ri-save-line" @click="handleSave">Simpan</VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-success), 0.04); }
</style>
