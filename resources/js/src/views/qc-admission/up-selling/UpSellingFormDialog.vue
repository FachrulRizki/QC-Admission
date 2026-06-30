<script setup>
import { useUpSellingStore } from '@/stores/useUpSellingStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useUpSellingStore()

function formatDatetime(d) {
  const p = n => String(n).padStart(2, '0')
  return `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}
function formatTime(d) {
  const p = n => String(n).padStart(2, '0')
  return `${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}

function initialForm() {
  const now = new Date()
  return {
    tanggal: formatDatetime(now),
    jam_input: formatTime(now),
    no_reg: null,
    nama_pasien: '',
    jaminan: '',
    rekomendasi_kelas: null,
    kelas_diambil: null,
    alasan: '',
    petugas: null,
    note: '',
    status: null,
  }
}

const form = ref(initialForm())
const errorMsg = ref('')
const successMsg = ref('')

const noRegOptions = [
  { title: '813500 - ELLY MAYA, NY', value: '813500' },
  { title: '575360 - IDH SUBINGSEN, NY', value: '575360' },
  { title: '087220 - RUSMINI, NY', value: '087220' },
]

const kelasOptions = ['Kelas 1', 'Kelas 2', 'Kelas 3', 'VIP', 'VVIP']
const petugasOptions = ['Nurul', 'AYU Putri Anisa', 'Reskim', 'Mulbagus Koyum']
const statusOptions = ['Berhasil', 'Tidak Berhasil', 'Pending']

watch(() => props.editItem, (item) => {
  form.value = item ? { ...initialForm(), ...item } : initialForm()
}, { immediate: true })

watch(() => form.value.no_reg, (val) => {
  const found = noRegOptions.find(o => o.value === val)
  if (found) form.value.nama_pasien = found.title.split(' - ')[1] ?? ''
})

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg) { errorMsg.value = 'NoReg wajib dipilih.'; return }
  if (!form.value.petugas) { errorMsg.value = 'Petugas wajib dipilih.'; return }

  const result = props.editItem
    ? await store.update?.(props.editItem.id, form.value)
    : await store.store(form.value)

  if (result?.success) {
    successMsg.value = 'Data berhasil disimpan.'
    emit('saved')
    setTimeout(() => close(), 800)
  } else {
    errorMsg.value = result?.message ?? 'Gagal menyimpan data'
  }
}

function close() {
  emit('update:modelValue', false)
  errorMsg.value = ''
  successMsg.value = ''
}
</script>

<template>
  <VDialog :model-value="modelValue" max-width="520" persistent scrollable @update:model-value="close">
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-4 pb-3">
        <div class="d-flex align-center gap-2">
          <VBtn icon variant="text" size="small" @click="close">
            <VIcon icon="ri-close-line" />
          </VBtn>
          <span class="text-h6 font-weight-bold">Input Up Selling</span>
        </div>
        <div class="d-flex gap-2">
          <VBtn variant="outlined" size="small" @click="close">Cancel</VBtn>
          <VBtn color="primary" size="small" :loading="store.loading" @click="handleSave">Save</VBtn>
        </div>
      </VCardTitle>
      <VDivider />

      <VCardText class="pa-4">
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg = ''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">{{ successMsg }}</VAlert>

        <VForm @submit.prevent="handleSave">
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Tanggal</p>
            <VTextField v-model="form.tanggal" variant="outlined" density="compact" readonly append-inner-icon="ri-calendar-line" />
          </div>
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Jam Input</p>
            <VTextField v-model="form.jam_input" variant="outlined" density="compact" readonly append-inner-icon="ri-time-line" />
          </div>
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">NoReg</p>
            <VAutocomplete v-model="form.no_reg" :items="noRegOptions" item-title="title" item-value="value" variant="outlined" density="compact" placeholder="Pilih NoReg..." clearable />
          </div>
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Nama Pasien</p>
            <VTextField v-model="form.nama_pasien" variant="outlined" density="compact" readonly />
          </div>
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Jaminan</p>
            <VTextField v-model="form.jaminan" variant="outlined" density="compact" />
          </div>
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Rekomendasi Kelas</p>
            <VSelect v-model="form.rekomendasi_kelas" :items="kelasOptions" variant="outlined" density="compact" clearable />
          </div>
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Kelas Diambil</p>
            <VSelect v-model="form.kelas_diambil" :items="kelasOptions" variant="outlined" density="compact" clearable />
          </div>
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Alasan</p>
            <VTextarea v-model="form.alasan" variant="outlined" density="compact" rows="2" />
          </div>
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Petugas</p>
            <VAutocomplete v-model="form.petugas" :items="petugasOptions" variant="outlined" density="compact" placeholder="Pilih petugas..." clearable />
          </div>
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Status</p>
            <VSelect v-model="form.status" :items="statusOptions" variant="outlined" density="compact" clearable />
          </div>
          <div class="mb-2">
            <p class="text-caption font-weight-medium mb-1">Note</p>
            <VTextField v-model="form.note" variant="outlined" density="compact" />
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
