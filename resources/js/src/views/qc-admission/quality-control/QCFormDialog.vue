<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useQualityControlStore()

// ── form state ────────────────────────────────────────────────────────────────
const now = new Date()
const form = ref(initialForm())

function initialForm() {
  return {
    tanggal: formatDatetime(new Date()),
    jam_input: formatTime(new Date()),
    tgl_daftar: formatDatetime(new Date()),
    jam_daftar: formatDatetime(new Date()),
    no_mr: '',
    no_reg: null,
    nama_pasien: '',
    jaminan: '',
    status_ket: '',
    edukasi_kamar: '',
    durasi_tunggu: '00:00:00',
    note: null,
    petugas: null,
    status: null,           // 'Edukasi' | 'Edukasi lanjutan'
    keluarga_pasien: '',
    ttd_keluarga_pasien: '',
  }
}

// ── mock dropdown data (replace with API) ────────────────────────────────────
const noRegOptions = ref([
  { title: '813500 - ELLY MAYA, NY', value: '813500' },
  { title: '575360 - IDH SUBINGSEN, NY', value: '575360' },
  { title: '087220 - RUSMINI, NY', value: '087220' },
])

const noteOptions = [
  'Sudah menjelaskan kelas',
  'Pasien mengerti',
  'Keluarga hadir',
  'Dirujuk',
]

const petugasOptions = [
  'Nurul',
  'AYU Putri Anisa',
  'Reskim',
  'Mulbagus Koyum',
  'Abdul Hayyi',
]

const signatureLocked = ref(true)
const errorMsg = ref('')
const successMsg = ref('')

// ── watchers ──────────────────────────────────────────────────────────────────
watch(() => props.editItem, (item) => {
  form.value = item ? { ...initialForm(), ...item } : initialForm()
}, { immediate: true })

watch(() => form.value.no_reg, (val) => {
  const found = noRegOptions.value.find(o => o.value === val)
  if (found) {
    // Auto-fill nama & no_mr when noReg is selected
    form.value.no_mr = found.value
    form.value.nama_pasien = found.title.split(' - ')[1] ?? ''
  }
})

// Recalculate durasi_tunggu every second while dialog is open
let timer = null
watch(() => props.modelValue, (open) => {
  if (open) {
    if (!props.editItem) {
      form.value = initialForm()
    }
    timer = setInterval(updateDurasi, 1000)
  } else {
    clearInterval(timer)
  }
})

function updateDurasi() {
  const start = new Date(`${form.value.tgl_daftar}`)
  const diff = Math.floor((Date.now() - start.getTime()) / 1000)
  const h = String(Math.floor(diff / 3600)).padStart(2, '0')
  const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0')
  const s = String(diff % 60).padStart(2, '0')
  form.value.durasi_tunggu = `${h}:${m}:${s}`
}

// ── helpers ───────────────────────────────────────────────────────────────────
function formatDatetime(d) {
  const pad = n => String(n).padStart(2, '0')
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}, ${pad(d.getHours())}.${pad(d.getMinutes())}.${pad(d.getSeconds())}`
}

function formatTime(d) {
  const pad = n => String(n).padStart(2, '0')
  return `${pad(d.getHours())}.${pad(d.getMinutes())}.${pad(d.getSeconds())}`
}

// ── submit ────────────────────────────────────────────────────────────────────
async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg) {
    errorMsg.value = 'NoReg wajib dipilih.'
    return
  }
  if (!form.value.petugas) {
    errorMsg.value = 'Petugas wajib dipilih.'
    return
  }

  const result = props.editItem
    ? await store.update(props.editItem.id, form.value)
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
  clearInterval(timer)
  emit('update:modelValue', false)
  errorMsg.value = ''
  successMsg.value = ''
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="560"
    persistent
    scrollable
    @update:model-value="close"
  >
    <VCard>
      <!-- Header -->
      <VCardTitle class="d-flex align-center justify-space-between pa-4 pb-3">
        <div class="d-flex align-center gap-2">
          <VBtn
            icon
            variant="text"
            size="small"
            @click="close"
          >
            <VIcon icon="ri-close-line" />
          </VBtn>
          <span class="text-h6 font-weight-bold">Quality Control</span>
        </div>
        <div class="d-flex gap-2">
          <VBtn
            variant="outlined"
            size="small"
            @click="close"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            size="small"
            :loading="store.loading"
            @click="handleSave"
          >
            Save
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <VCardText class="pa-4">
        <!-- Alerts -->
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
            <VTextField
              v-model="form.tanggal"
              variant="outlined"
              density="compact"
              readonly
              :append-inner-icon="'ri-calendar-line'"
            />
          </div>

          <!-- Jam Input -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Jam_Input</p>
            <VTextField
              v-model="form.jam_input"
              variant="outlined"
              density="compact"
              readonly
              :append-inner-icon="'ri-time-line'"
            />
          </div>

          <!-- tgldaftar -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">tgldaftar</p>
            <VTextField
              v-model="form.tgl_daftar"
              variant="outlined"
              density="compact"
              readonly
              :append-inner-icon="'ri-calendar-line'"
            />
          </div>

          <!-- jamdaftar -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">jamdaftar</p>
            <VTextField
              v-model="form.jam_daftar"
              variant="outlined"
              density="compact"
              readonly
              :append-inner-icon="'ri-calendar-line'"
            />
          </div>

          <!-- NoMR -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">NoMR</p>
            <VTextField
              v-model="form.no_mr"
              variant="outlined"
              density="compact"
              readonly
              placeholder="Otomatis dari NoReg"
            />
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

          <!-- NamaPasien -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">NamaPasien</p>
            <VTextField
              v-model="form.nama_pasien"
              variant="outlined"
              density="compact"
              readonly
            />
          </div>

          <!-- Jaminan -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Jaminan</p>
            <VTextField
              v-model="form.jaminan"
              variant="outlined"
              density="compact"
              readonly
            />
          </div>

          <!-- status_ket -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">status_ket</p>
            <VTextField
              v-model="form.status_ket"
              variant="outlined"
              density="compact"
              readonly
            />
          </div>

          <!-- Edukasi Kamar -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Edukasi_kamar</p>
            <VTextField
              v-model="form.edukasi_kamar"
              variant="outlined"
              density="compact"
              placeholder="Nama ruangan..."
            />
          </div>

          <!-- DurasiTunggu -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">DurasiTunggu</p>
            <VTextField
              v-model="form.durasi_tunggu"
              variant="outlined"
              density="compact"
              readonly
            />
          </div>

          <!-- Note -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Note</p>
            <VSelect
              v-model="form.note"
              :items="noteOptions"
              variant="outlined"
              density="compact"
              placeholder="Pilih note..."
              clearable
            />
          </div>

          <!-- Petugas -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Petugas</p>
            <VAutocomplete
              v-model="form.petugas"
              :items="petugasOptions"
              variant="outlined"
              density="compact"
              placeholder="Search..."
              clearable
            />
          </div>

          <!-- Status toggle -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Status</p>
            <VBtnToggle
              v-model="form.status"
              mandatory
              rounded="sm"
              color="primary"
              class="w-100"
            >
              <VBtn value="Edukasi" class="flex-grow-1">
                Edukasi
              </VBtn>
              <VBtn value="Edukasi lanjutan" class="flex-grow-1">
                Edukasi lanjutan
              </VBtn>
            </VBtnToggle>
          </div>

          <!-- Keluarga Pasien -->
          <div class="mb-4">
            <p class="text-caption font-weight-medium mb-1">Keluarga Pasien</p>
            <VTextField
              v-model="form.keluarga_pasien"
              variant="outlined"
              density="compact"
            />
          </div>

          <!-- TTD Keluarga Pasien -->
          <div class="mb-2">
            <p class="text-caption font-weight-medium mb-1">ttd_keluargaPasien</p>
            <VCard
              variant="outlined"
              class="pa-4 text-center"
              min-height="120"
            >
              <div
                v-if="signatureLocked"
                class="d-flex flex-column align-center justify-center gap-3"
                style="min-height: 100px;"
              >
                <VIcon icon="ri-lock-2-line" size="36" color="secondary" />
                <VBtn
                  variant="text"
                  size="small"
                  color="secondary"
                  @click="signatureLocked = false"
                >
                  Tap to unlock
                </VBtn>
              </div>
              <div v-else class="text-center">
                <VTextarea
                  v-model="form.ttd_keluarga_pasien"
                  variant="outlined"
                  density="compact"
                  rows="3"
                  placeholder="Tanda tangan / nama lengkap..."
                />
                <VBtn
                  size="x-small"
                  variant="text"
                  class="mt-2"
                  @click="signatureLocked = true"
                >
                  Kunci kembali
                </VBtn>
              </div>
            </VCard>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
