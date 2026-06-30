<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import SignaturePad from '@/components/SignaturePad.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useQualityControlStore()

// ── form ──────────────────────────────────────────────────────────────────────
const form    = ref(initialForm())
const sigRef  = ref(null)
const errorMsg  = ref('')
const successMsg = ref('')

function initialForm() {
  const now = new Date()
  return {
    tanggal:             formatDatetime(now),
    jam_input:           formatTime(now),
    tgl_daftar:          formatDatetime(now),
    jam_daftar:          formatTime(now),
    no_mr:               '',
    no_reg:              null,
    nama_pasien:         '',
    jaminan:             '',
    status_ket:          '',
    edukasi_kamar:       '',
    durasi_tunggu:       '00:00:00',
    note:                null,
    petugas:             null,
    status:              'Edukasi',
    keluarga_pasien:     '',
    ttd_keluarga_pasien: '',
  }
}

// ── dropdown options ──────────────────────────────────────────────────────────
const noRegOptions = ref([
  { title: 'REG001 - ELLY MAYA, NY',       value: 'REG001', mr: '813500', nama: 'ELLY MAYA, NY',       jaminan: 'BPJS' },
  { title: 'REG002 - IDH SUBINGSEN, NY',   value: 'REG002', mr: '575360', nama: 'IDH SUBINGSEN, NY',   jaminan: 'BPJS' },
  { title: 'REG003 - RUSMINI, NY',         value: 'REG003', mr: '087220', nama: 'RUSMINI, NY',         jaminan: 'Umum' },
  { title: 'REG004 - PUSPA SARI, AN',      value: 'REG004', mr: '816302', nama: 'PUSPA SARI, AN',      jaminan: 'BPJS' },
  { title: 'REG005 - BUDI SANTOSO, TN',    value: 'REG005', mr: '712405', nama: 'BUDI SANTOSO, TN',    jaminan: 'Asuransi' },
  { title: 'REG006 - SRI WAHYUNI, NY',     value: 'REG006', mr: '654321', nama: 'SRI WAHYUNI, NY',     jaminan: 'BPJS' },
  { title: 'REG007 - AHMAD FAUZI, TN',     value: 'REG007', mr: '789012', nama: 'AHMAD FAUZI, TN',     jaminan: 'Umum' },
  { title: 'REG008 - DEWI RAHAYU, NY',     value: 'REG008', mr: '345678', nama: 'DEWI RAHAYU, NY',     jaminan: 'BPJS' },
  { title: 'REG009 - HENDRA WIJAYA, TN',   value: 'REG009', mr: '901234', nama: 'HENDRA WIJAYA, TN',   jaminan: 'Asuransi' },
  { title: 'REG010 - SITI AMINAH, NY',     value: 'REG010', mr: '567890', nama: 'SITI AMINAH, NY',     jaminan: 'BPJS' },
])

const noteOptions = [
  'Sudah menjelaskan kelas',
  'Pasien mengerti',
  'Keluarga hadir',
  'Dirujuk',
  'Menunggu kamar',
]

const petugasOptions = [
  'Nurul',
  'AYU Putri Anisa',
  'Reskim',
  'Mulbagus Koyum',
  'Abdul Hayyi',
]

const statusKetOptions = [
  'Belum Dapat Kamar',
  'Antri Kamar',
  'Sudah Dapat Kamar',
]

// ── live durasi timer ──────────────────────────────────────────────────────────
let durasiTimer = null

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    errorMsg.value = ''
    successMsg.value = ''
    if (!props.editItem) {
      durasiTimer = setInterval(updateDurasi, 1000)
    }
  } else {
    clearInterval(durasiTimer)
  }
})

function updateDurasi() {
  try {
    const startStr = form.value.tgl_daftar
    if (!startStr) return
    // parse "dd/mm/yyyy, HH.MM.SS"
    const parts   = startStr.split(', ')
    const dateParts = parts[0].split('/')
    const timeParts = (parts[1] ?? '00.00.00').split('.')
    const start = new Date(
      parseInt(dateParts[2]),
      parseInt(dateParts[1]) - 1,
      parseInt(dateParts[0]),
      parseInt(timeParts[0]),
      parseInt(timeParts[1]),
      parseInt(timeParts[2]),
    )
    const diff = Math.max(0, Math.floor((Date.now() - start.getTime()) / 1000))
    const h = String(Math.floor(diff / 3600)).padStart(2, '0')
    const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0')
    const s = String(diff % 60).padStart(2, '0')
    form.value.durasi_tunggu = `${h}:${m}:${s}`
  } catch {}
}

// ── NoReg auto-fill ───────────────────────────────────────────────────────────
watch(() => form.value.no_reg, (val) => {
  const found = noRegOptions.value.find(o => o.value === val)
  if (found) {
    form.value.no_mr       = found.mr
    form.value.nama_pasien = found.nama
    form.value.jaminan     = found.jaminan
  }
})

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
  if (!form.value.no_reg)   { errorMsg.value = 'NoReg wajib dipilih.'; return }
  if (!form.value.petugas)  { errorMsg.value = 'Petugas wajib dipilih.'; return }
  if (!form.value.status)   { errorMsg.value = 'Status wajib dipilih.'; return }

  const payload = { ...form.value }

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

function close() {
  clearInterval(durasiTimer)
  emit('update:modelValue', false)
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
    <VCard rounded="lg">
      <!-- ── Header ──────────────────────────────────────────────────────── -->
      <div class="dialog-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="primary" variant="tonal" size="40" rounded="lg">
          <VIcon icon="ri-shield-check-line" size="20" />
        </VAvatar>
        <div class="flex-grow-1">
          <p class="text-subtitle-1 font-weight-bold mb-0">
            {{ editItem ? 'Edit Quality Control' : 'Input Quality Control' }}
          </p>
          <p class="text-caption text-medium-emphasis mb-0">Form entry data QC admisi</p>
        </div>
        <VBtn icon variant="text" size="small" @click="close">
          <VIcon icon="ri-close-line" />
        </VBtn>
      </div>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Alerts -->
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg = ''">
          {{ errorMsg }}
        </VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">
          <VIcon icon="ri-check-line" class="me-1" /> {{ successMsg }}
        </VAlert>

        <VForm @submit.prevent="handleSave">
          <!-- Tanggal + Jam (readonly auto) -->
          <VRow dense class="mb-1">
            <VCol cols="7">
              <VTextField
                v-model="form.tanggal"
                label="Tanggal Input"
                variant="outlined"
                density="compact"
                readonly
                prepend-inner-icon="ri-calendar-line"
              />
            </VCol>
            <VCol cols="5">
              <VTextField
                v-model="form.jam_input"
                label="Jam Input"
                variant="outlined"
                density="compact"
                readonly
                prepend-inner-icon="ri-time-line"
              />
            </VCol>
          </VRow>

          <!-- NoReg (autocomplete) -->
          <div class="mb-3">
            <VAutocomplete
              v-model="form.no_reg"
              :items="noRegOptions"
              item-title="title"
              item-value="value"
              label="No. Registrasi *"
              variant="outlined"
              density="compact"
              placeholder="Pilih atau ketik NoReg..."
              prepend-inner-icon="ri-search-line"
              clearable
            />
          </div>

          <!-- NoMR + Nama (readonly, auto-filled) -->
          <VRow dense class="mb-1">
            <VCol cols="4">
              <VTextField
                v-model="form.no_mr"
                label="No. MR"
                variant="outlined"
                density="compact"
                readonly
                bg-color="grey-lighten-4"
              />
            </VCol>
            <VCol cols="8">
              <VTextField
                v-model="form.nama_pasien"
                label="Nama Pasien"
                variant="outlined"
                density="compact"
                readonly
                bg-color="grey-lighten-4"
              />
            </VCol>
          </VRow>

          <!-- Jaminan + Status Ket -->
          <VRow dense class="mb-1">
            <VCol cols="5">
              <VTextField
                v-model="form.jaminan"
                label="Jaminan"
                variant="outlined"
                density="compact"
                readonly
                bg-color="grey-lighten-4"
              />
            </VCol>
            <VCol cols="7">
              <VSelect
                v-model="form.status_ket"
                :items="statusKetOptions"
                label="Status Keterangan"
                variant="outlined"
                density="compact"
                clearable
              />
            </VCol>
          </VRow>

          <!-- Edukasi Kamar -->
          <div class="mb-3">
            <VTextField
              v-model="form.edukasi_kamar"
              label="Edukasi Kamar / Ruangan"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-hospital-line"
              placeholder="contoh: Ruang Mawar, ICU..."
            />
          </div>

          <!-- Durasi Tunggu (live timer) -->
          <div class="mb-3">
            <VTextField
              v-model="form.durasi_tunggu"
              label="Durasi Tunggu (auto)"
              variant="outlined"
              density="compact"
              readonly
              prepend-inner-icon="ri-timer-flash-line"
            >
              <template #append-inner>
                <VChip
                  :color="form.durasi_tunggu > '02:00:00' ? 'error' : 'success'"
                  size="x-small"
                  variant="tonal"
                >
                  {{ form.durasi_tunggu > '02:00:00' ? 'Lama' : 'Normal' }}
                </VChip>
              </template>
            </VTextField>
          </div>

          <!-- Note + Petugas -->
          <VRow dense class="mb-1">
            <VCol cols="6">
              <VSelect
                v-model="form.note"
                :items="noteOptions"
                label="Note"
                variant="outlined"
                density="compact"
                clearable
              />
            </VCol>
            <VCol cols="6">
              <VAutocomplete
                v-model="form.petugas"
                :items="petugasOptions"
                label="Petugas *"
                variant="outlined"
                density="compact"
                prepend-inner-icon="ri-nurse-line"
                clearable
              />
            </VCol>
          </VRow>

          <!-- Status toggle -->
          <div class="mb-4">
            <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">Status *</p>
            <VBtnToggle
              v-model="form.status"
              mandatory
              rounded="lg"
              color="primary"
              density="compact"
              class="w-100 status-toggle"
            >
              <VBtn value="Edukasi" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-book-2-line" class="me-1" size="16" />
                Edukasi
              </VBtn>
              <VBtn value="Edukasi lanjutan" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-book-open-line" class="me-1" size="16" />
                Edukasi Lanjutan
              </VBtn>
              <VBtn value="Masuk" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-hospital-line" class="me-1" size="16" />
                Masuk
              </VBtn>
            </VBtnToggle>
          </div>

          <VDivider class="mb-4" />

          <!-- Keluarga Pasien -->
          <div class="mb-3">
            <VTextField
              v-model="form.keluarga_pasien"
              label="Nama Keluarga Pasien"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-group-line"
              placeholder="Nama penanggungjawab..."
            />
          </div>

          <!-- TTD Keluarga Pasien — Signature Pad -->
          <SignaturePad
            v-model="form.ttd_keluarga_pasien"
            ref="sigRef"
            label="Tanda Tangan Keluarga Pasien"
            :height="160"
          />
        </VForm>
      </VCardText>

      <VDivider />

      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" class="flex-grow-1" @click="close">
          Batal
        </VBtn>
        <VBtn
          color="primary"
          class="flex-grow-1"
          :loading="store.loading"
          prepend-icon="ri-save-line"
          @click="handleSave"
        >
          Simpan
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header {
  background: linear-gradient(
    135deg,
    rgba(var(--v-theme-primary), 0.05) 0%,
    rgba(var(--v-theme-surface), 1) 100%
  );
}

.status-toggle :deep(.v-btn) {
  font-size: 0.75rem;
}
</style>
