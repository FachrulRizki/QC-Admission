<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useBatalRanapStore()

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
    keterangan_batal: null,
    status_ok: null,
    ketersediaan_kamar: '',
    diagnosa: '',
    note: '',
    petugas: null,
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

const keteranganBatalOptions = [
  'Pasien Meninggal',
  'Pasien Menolak Ranap',
  'Kamar Penuh',
  'Dirujuk ke RS Lain',
  'Lainnya',
]

const statusOkOptions = ['OK', 'Pending', 'Ditolak']

const petugasOptions = ['Nurul', 'AYU Putri Anisa', 'Reskim', 'Mulbagus Koyum']

watch(() => props.editItem, (item) => {
  form.value = item ? { ...initialForm(), ...item } : initialForm()
}, { immediate: true })

watch(() => props.modelValue, (open) => {
  if (open && !props.editItem) form.value = initialForm()
})

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg) {
    errorMsg.value = 'NoReg wajib dipilih.'
    return
  }

  const result = props.editItem
    ? await store.verifikasi(props.editItem.id, form.value)
    : await store.store(form.value)

  if (result.success) {
    successMsg.value = 'Data berhasil disimpan.'
    emit('saved')
    setTimeout(() => close(), 800)
  } else {
    errorMsg.value = result.message
  }
}

function close() {
  emit('update:modelValue', false)
  errorMsg.value = ''
  successMsg.value = ''
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="520"
    persistent
    scrollable
    @update:model-value="close"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-4 pb-3">
        <div class="d-flex align-center gap-2">
          <VBtn icon variant="text" size="small" @click="close">
            <VIcon icon="ri-close-line" />
          </VBtn>
          <span class="text-h6 font-weight-bold">Input Ps Batal Ranap</span>
        </div>
        <div class="d-flex gap-2">
          <VBtn variant="outlined" size="small" @click="close">Cancel</VBtn>
          <VBtn color="primary" size="small" :loading="store.loading" @click="handleSave">Save</VBtn>
        </div>
      </VCardTitle>
      <VDivider />

      <VCardText class="pa-4">
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg = ''">
          {{ errorMsg }}
        </VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">
          {{ successMsg }}
        </VAlert>

        <VForm @submit.prevent="handleSave">
          <!-- Tanggal -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Tanggal</p>
            <VTextField v-model="form.tanggal" variant="outlined" density="compact" readonly append-inner-icon="ri-calendar-line" />
          </div>

          <!-- Jam Input -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Jam_Input</p>
            <VTextField v-model="form.jam_input" variant="outlined" density="compact" readonly append-inner-icon="ri-time-line" />
          </div>

          <!-- NoReg -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">NoReg</p>
            <VAutocomplete
              v-model="form.no_reg"
              :items="noRegOptions"
              item-title="title"
              item-value="value"
              variant="outlined"
              density="compact"
              placeholder="Pilih NoReg..."
              clearable
            />
          </div>

          <!-- Keterangan Batal -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Keterangan_Batal</p>
            <VSelect
              v-model="form.keterangan_batal"
              :items="keteranganBatalOptions"
              variant="outlined"
              density="compact"
              placeholder="Pilih keterangan..."
              clearable
            />
          </div>

          <!-- Status OK -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Status OK</p>
            <VSelect
              v-model="form.status_ok"
              :items="statusOkOptions"
              variant="outlined"
              density="compact"
              placeholder="Pilih status..."
              clearable
            />
          </div>

          <!-- Ketersediaan Kamar Saat Ini -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Ketersediaan Kamar Saat Ini</p>
            <VTextField v-model="form.ketersediaan_kamar" variant="outlined" density="compact" />
          </div>

          <!-- Diagnosa -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Diagnosa</p>
            <VTextField v-model="form.diagnosa" variant="outlined" density="compact" />
          </div>

          <!-- Note -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Note</p>
            <VTextField v-model="form.note" variant="outlined" density="compact" />
          </div>

          <!-- Petugas -->
          <div class="mb-2">
            <p class="text-caption font-weight-medium mb-1">Petugas</p>
            <VAutocomplete
              v-model="form.petugas"
              :items="petugasOptions"
              variant="outlined"
              density="compact"
              placeholder="Pilih petugas..."
              clearable
            />
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
