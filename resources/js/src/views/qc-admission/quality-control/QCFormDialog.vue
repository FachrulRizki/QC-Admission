<script setup>
import { useQualityControlStore } from '@/stores/useQualityControlStore'
import { usePegawaiStore }        from '@/stores/usePegawaiStore'
import { usePasienStore }         from '@/stores/usePasienStore'
import SignaturePad               from '@/components/SignaturePad.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useQualityControlStore()
const pegawaiStore = usePegawaiStore()
const pasienStore  = usePasienStore()

const form       = ref(initialForm())
const errorMsg   = ref('')
const successMsg = ref('')

// ── Pasien search ─────────────────────────────────────────────────────────────
const noRegSearch  = ref('')
const noRegLoading = ref(false)
let   _debounce    = null

// Watch teks yang diketik di VAutocomplete
watch(noRegSearch, (val) => {
  clearTimeout(_debounce)
  if (!val || val.trim().length < 2) {
    pasienStore.clear()
    return
  }
  noRegLoading.value = true
  _debounce = setTimeout(async () => {
    await pasienStore.search(val.trim())
    noRegLoading.value = false
  }, 300)
})

// Saat pilih no_reg dari dropdown → autofill
watch(() => form.value.no_reg, async (val) => {
  if (!val) return
  // Cari dari hasil search dulu, baru lookup ke API
  const hit = pasienStore.results.find(p => p.no_reg === val)
           ?? await pasienStore.lookup(val)
  if (hit) {
    form.value.no_mr       = hit.no_mr       ?? ''
    form.value.nama_pasien = hit.nama_pasien ?? ''
    form.value.jaminan     = hit.ket_bayar   ?? ''
    // status_ket dari field Keterangan DB (bukan dropdown)
    form.value.status_ket  = hit.keterangan  ?? ''
    form.value.tgl_daftar  = hit.tgl_daftar  ?? fmt(new Date())
    form.value.jam_daftar  = hit.jam_daftar  ?? fmtTime(new Date())
  }
})

// ── Master options ────────────────────────────────────────────────────────────
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
// Durasi dihitung sejak form dibuka (bukan dari tgl_daftar pasien yg bisa error)
let durasiTimer = null
let durasiStart = null  // timestamp saat form dibuka

// Live clock untuk section Waktu Input
const nowDisplay = ref('')
let clockTimer = null

function tickClock() {
  const d = new Date()
  const p = n => String(n).padStart(2, '0')
  nowDisplay.value = p(d.getDate()) + '/' + p(d.getMonth()+1) + '/' + d.getFullYear()
    + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds())
}

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value       = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    errorMsg.value   = ''
    successMsg.value = ''
    pasienStore.clear()
    noRegSearch.value = ''
    pegawaiStore.fetch()
    tickClock()
    clockTimer = setInterval(tickClock, 1000)
    if (!props.editItem) {
      durasiStart = Date.now()
      durasiTimer = setInterval(updateDurasi, 1000)
    }
  } else {
    clearInterval(durasiTimer)
    clearInterval(clockTimer)
    durasiStart = null
  }
})

function updateDurasi() {
  if (!durasiStart) return
  const diff = Math.max(0, Math.floor((Date.now() - durasiStart) / 1000))
  const h = String(Math.floor(diff / 3600)).padStart(2, '0')
  const m = String(Math.floor((diff % 3600) / 60)).padStart(2, '0')
  const s = String(diff % 60).padStart(2, '0')
  form.value.durasi_tunggu = h + ':' + m + ':' + s
}

function initialForm() {
  const now = new Date()
  return {
    tanggal: '', jam_input: '',
    // tgl_daftar & jam_daftar = dari pasien DB (readonly display)
    tgl_daftar: '', jam_daftar: '',
    no_mr: '', no_reg: null, nama_pasien: '', jaminan: '',
    status_ket: '', edukasi_kamar: '',
    durasi_tunggu: '00:00:00', note: null, petugas: null,
    status: 'Edukasi',
    keluarga_pasien: '', ttd_keluarga_pasien: '',
  }
}

function fmt(d) {
  const p = n => String(n).padStart(2, '0')
  return `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}, ${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}
function fmtTime(d) {
  const p = n => String(n).padStart(2, '0')
  return `${p(d.getHours())}.${p(d.getMinutes())}.${p(d.getSeconds())}`
}

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg)  { errorMsg.value = 'No. Registrasi wajib dipilih.'; return }
  if (!form.value.petugas) { errorMsg.value = 'Petugas wajib dipilih.'; return }
  if (!form.value.status)  { errorMsg.value = 'Status wajib dipilih.'; return }

  const now     = new Date()
  const payload = { ...form.value, tanggal: fmt(now), jam_input: fmtTime(now) }

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
  <VDialog :model-value="modelValue" max-width="580" persistent scrollable @update:model-value="close">
    <VCard rounded="xl">
      <!-- Header -->
      <div class="dialog-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="primary" variant="tonal" size="42" rounded="lg">
          <VIcon icon="ri-shield-check-line" size="22" />
        </VAvatar>
        <div class="flex-grow-1">
          <p class="text-subtitle-1 font-weight-bold mb-0">{{ editItem ? 'Edit Quality Control' : 'Input Quality Control' }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Form entry data QC admisi · auto Edukasi Lanjutan ≥ 2 jam</p>
        </div>
        <VBtn icon variant="text" size="small" @click="close"><VIcon icon="ri-close-line" /></VBtn>
      </div>
      <VDivider />

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">
          <VIcon icon="ri-error-warning-line" class="me-1" />{{ errorMsg }}
        </VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">
          <VIcon icon="ri-check-line" class="me-1" />{{ successMsg }}
        </VAlert>

        <!-- Section: Waktu -->
        <div class="form-section mb-4">
          <p class="form-section-label">Waktu Input QC</p>
          <VTextField
            :model-value="nowDisplay"
            label="Tanggal & Jam Input (auto)"
            variant="outlined" density="compact" readonly
            prepend-inner-icon="ri-calendar-line"
            bg-color="grey-lighten-5"
          />
        </div>

        <!-- Section: Data Pasien -->
        <div class="form-section mb-4">
          <p class="form-section-label">Data Pasien</p>

          <!-- No. Reg — search dengan autocomplete -->
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
            class="mb-3"
          >
            <template #item="{ item, props: iProps }">
              <VListItem v-bind="iProps">
                <template #prepend>
                  <VAvatar color="primary" variant="tonal" size="32" rounded="lg" class="me-2">
                    <span style="font-size:11px;font-weight:700">{{ item.raw?.data?.nama_pasien?.charAt(0) ?? '?' }}</span>
                  </VAvatar>
                </template>
                <VListItemTitle class="text-body-2 font-weight-medium">{{ item.raw?.data?.nama_pasien }}</VListItemTitle>
                <VListItemSubtitle class="text-caption">{{ item.raw?.data?.no_reg }} · {{ item.raw?.data?.ket_bayar }} · {{ item.raw?.data?.nama_bangsal }}</VListItemSubtitle>
              </VListItem>
            </template>
          </VAutocomplete>

          <VRow dense>
            <VCol cols="4">
              <VTextField v-model="form.no_mr" label="No. MR" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" />
            </VCol>
            <VCol cols="8">
              <VTextField v-model="form.nama_pasien" label="Nama Pasien" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" />
            </VCol>
          </VRow>

          <VRow dense class="mt-2">
            <VCol cols="5">
              <VTextField v-model="form.jaminan" label="Jaminan" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" />
            </VCol>
            <VCol cols="7">
              <VTextField v-model="form.status_ket" label="Status Keterangan" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" prepend-inner-icon="ri-information-line" placeholder="Otomatis dari data pasien..." />
            </VCol>
          </VRow>
          <!-- Tgl & Jam Daftar Pasien dari DB RSUS -->
          <VRow dense class="mt-2">
            <VCol cols="6">
              <VTextField v-model="form.tgl_daftar" label="Tgl. Daftar Pasien" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" prepend-inner-icon="ri-calendar-check-line" placeholder="Pilih No. Reg..." />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="form.jam_daftar" label="Jam Daftar" variant="outlined" density="compact" readonly bg-color="grey-lighten-5" prepend-inner-icon="ri-time-line" placeholder="—" />
            </VCol>
          </VRow>
        </div>

        <!-- Section: QC Data -->
        <div class="form-section mb-4">
          <p class="form-section-label">Data Quality Control</p>

          <VTextField v-model="form.edukasi_kamar" label="Edukasi Kamar / Ruangan" variant="outlined" density="compact" prepend-inner-icon="ri-hospital-line" class="mb-3" />

          <!-- Durasi Tunggu -->
          <VTextField v-model="form.durasi_tunggu" label="Durasi Tunggu (auto)" variant="outlined" density="compact" readonly prepend-inner-icon="ri-timer-flash-line" class="mb-3">
            <template #append-inner>
              <VChip :color="form.durasi_tunggu >= '02:00:00' ? 'error' : 'success'" size="x-small" variant="tonal">
                {{ form.durasi_tunggu >= '02:00:00' ? '≥ 2 jam' : 'Normal' }}
              </VChip>
            </template>
          </VTextField>

          <VRow dense>
            <VCol cols="6">
              <VSelect v-model="form.note" :items="noteOptions" label="Note Kamar" variant="outlined" density="compact" clearable />
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

          <!-- Status Toggle -->
          <div class="mt-3">
            <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">Status QC</p>
            <VBtnToggle v-model="form.status" mandatory rounded="lg" color="primary" density="compact" class="w-100">
              <VBtn value="Edukasi" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-book-line" size="15" class="me-1" />Edukasi
              </VBtn>
              <VBtn value="Edukasi lanjutan" class="flex-grow-1" variant="outlined">
                <VIcon icon="ri-book-open-line" size="15" class="me-1" />Edukasi Lanjutan
              </VBtn>
            </VBtnToggle>
            <p v-if="form.status === 'Edukasi lanjutan'" class="text-caption text-warning mt-1 mb-0">
              <VIcon icon="ri-information-line" size="12" class="me-1" />Jika durasi ≥ 2 jam, data otomatis dibuat di Edukasi Lanjutan
            </p>
          </div>
        </div>

        <!-- Section: Keluarga & TTD -->
        <VDivider class="mb-4" />
        <div class="form-section">
          <p class="form-section-label">Keluarga & Tanda Tangan</p>
          <VTextField v-model="form.keluarga_pasien" label="Nama Keluarga Pasien" variant="outlined" density="compact" prepend-inner-icon="ri-group-line" class="mb-3" />
          <SignaturePad v-model="form.ttd_keluarga_pasien" label="Tanda Tangan Keluarga Pasien" :height="150" />
        </div>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Batal</VBtn>
        <VBtn color="primary" rounded="xl" class="flex-grow-1" prepend-icon="ri-save-line" :loading="store.loading" @click="handleSave">
          {{ editItem ? 'Simpan Perubahan' : 'Simpan Data' }}
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-primary), 0.05); }
.form-section-label {
  font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.08em; color: rgba(var(--v-theme-on-surface), 0.5);
  margin-bottom: 10px;
}
.form-section { padding: 12px; border-radius: 10px; background: rgba(var(--v-theme-on-surface), 0.02); border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
</style>
