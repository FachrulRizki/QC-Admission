<script setup>
import { useBatalRanapStore } from '@/stores/useBatalRanapStore'
import { usePegawaiStore }    from '@/stores/usePegawaiStore'
import { usePasienStore }     from '@/stores/usePasienStore'
import axios from 'axios'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useBatalRanapStore()
const pegawaiStore = usePegawaiStore()
const pasienStore  = usePasienStore()

const form          = ref(initialForm())
const errorMsg      = ref('')
const successMsg    = ref('')
const loadingBeds   = ref(false)
const availableBeds = ref([])

const keteranganOpts = [
  'APS Alih RS Lain', 'APS Rawat Jalan', 'Saran Alih RS Lain', 'Saran Konsul Poli',
  'Sisrute Tidak Dapat Kamar', 'Batal Rawat', 'Kamar Penuh',
  'Pasien Menolak', 'DPJP Tidak Setuju', 'Keluarga Menolak', 'Kondisi Membaik',
]

const OKOpts = ['Bedah', 'Non Bedah']

const ruanganOpts = [
  'IGD Umum', 'IGD Bedah', 'IGD Anak', 'IGD Kebidanan',
  'Ruang Mawar', 'Ruang Anggrek', 'Ruang Dahlia', 'Ruang Flamboyan',
  'ICU', 'NICU', 'HCU', 'PICU',
]

// Pasien search
const noRegSearch  = ref('')
const noRegLoading = ref(false)
let searchTimer = null

watch(noRegSearch, (val) => {
  clearTimeout(searchTimer)
  if (!val || val.trim().length < 2) {
    pasienStore.clear()
    return
  }
  noRegLoading.value = true
  searchTimer = setTimeout(async () => {
    await pasienStore.search(val.trim())
    noRegLoading.value = false
  }, 300)
})

watch(() => form.value.no_reg, async (val) => {
  if (!val) return
  const hit = pasienStore.results.find(p => p.no_reg === val)
    ?? await pasienStore.lookup(val)
  if (hit) {
    form.value.nama_pasien       = hit.nama_pasien ?? ''
    form.value.tgl_jam_daftar    = (hit.tgl_daftar ?? '') + (hit.jam_daftar ? ' ' + hit.jam_daftar : '')
    form.value.keterangan_pasien = hit.keterangan  ?? ''
    // diagnosa dari field keterangan DB — tidak perlu isi manual
    form.value.diagnosa          = hit.diagnosa  ?? ''
  }
})

// Fetch beds saat ruangan berubah
watch(() => form.value.ruangan, async (ruangan) => {
  form.value.bed_id   = null
  availableBeds.value = []
  if (!ruangan) return
  loadingBeds.value = true
  try {
    const { data } = await axios.get('/api/bed-management/beds', { params: { ruangan } })
    availableBeds.value = (data.beds ?? []).map(b => ({
      title: (b.bed_code ?? b.bed_id) + (b.status ? ' [' + b.status + ']' : ''),
      value: b.bed_id,
    }))
  } catch {
    availableBeds.value = []
  } finally {
    loadingBeds.value = false
  }
})

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value          = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    availableBeds.value = []
    errorMsg.value      = ''
    successMsg.value    = ''
    pasienStore.clear()
    noRegSearch.value   = ''
    pegawaiStore.fetch()
  }
})

function initialForm() {
  const now = new Date()
  const p   = n => String(n).padStart(2, '0')
  const jam = p(now.getHours()) + '.' + p(now.getMinutes()) + '.' + p(now.getSeconds())
  const tgl = p(now.getDate()) + '/' + p(now.getMonth() + 1) + '/' + now.getFullYear() + ', ' + jam
  return {
    tanggal: tgl,
    jam_input: jam,
    no_reg: null,
    nama_pasien: '',
    tgl_jam_daftar: '',
    keterangan_pasien: '',
    keterangan_batal: null,
    status_ok: null,
    ketersediaan_kamar: '',
    diagnosa: '',
    note: '',
    petugas: null,
    ruangan: null,
    bed_id: null,
  }
}

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg)           { errorMsg.value = 'No. Registrasi wajib dipilih.'; return }
  if (!form.value.keterangan_batal) { errorMsg.value = 'Keterangan batal wajib dipilih.'; return }
  if (!form.value.petugas)          { errorMsg.value = 'Petugas wajib dipilih.'; return }

  if (form.value.status_ok === 'Bedah' && form.value.bed_id) {
    await updateBed(form.value.ruangan, form.value.bed_id, 'available')
  }

  const result = props.editItem
    ? await store.update(props.editItem.id, form.value)
    : await store.store(form.value)

  if (result && result.success !== false) {
    successMsg.value = 'Data berhasil disimpan!'
    emit('saved', form.value)
    setTimeout(() => close(), 500)
  } else {
    errorMsg.value = (result && result.message) ? result.message : 'Gagal menyimpan.'
  }
}

async function updateBed(ruangan, bedId, status) {
  try {
    await axios.post('/api/bed-management/update-status', { bed_id: bedId, ruangan, status })
  } catch (e) {
    console.warn('Bed Management:', e.message)
  }
}

function close() { emit('update:modelValue', false) }
</script>

<template>
  <VDialog :model-value="modelValue" max-width="580" persistent scrollable @update:model-value="close">
    <VCard rounded="xl">
      <!-- Header -->
      <div class="dlg-header d-flex align-center gap-3 px-5 py-4">
        <VAvatar color="error" variant="tonal" size="42" rounded="lg">
          <VIcon icon="ri-close-circle-line" size="22" />
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
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg = ''">
          {{ errorMsg }}
        </VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">
          {{ successMsg }}
        </VAlert>

        <!-- Waktu Input -->
        <div class="fsec mb-4">
          <p class="fsec-label">Waktu Input</p>
          <VRow dense>
            <VCol cols="7">
              <VTextField v-model="form.tanggal" label="Tanggal" variant="outlined" density="compact" readonly prepend-inner-icon="ri-calendar-line" bg-color="grey-lighten-5" />
            </VCol>
            <VCol cols="5">
              <VTextField v-model="form.jam_input" label="Jam" variant="outlined" density="compact" readonly prepend-inner-icon="ri-time-line" bg-color="grey-lighten-5" />
            </VCol>
          </VRow>
        </div>

        <!-- Data Pasien -->
        <div class="fsec mb-4">
          <p class="fsec-label">Data Pasien</p>

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
                  <VAvatar color="error" variant="tonal" size="30" rounded="lg" class="me-2">
                    <span style="font-size:11px;font-weight:700">{{ item.raw?.data?.nama_pasien?.charAt(0) ?? '?' }}</span>
                  </VAvatar>
                </template>
                <VListItemTitle class="text-body-2 font-weight-medium">{{ item.raw?.data?.nama_pasien }}</VListItemTitle>
                <VListItemSubtitle class="text-caption">
                  {{ item.raw?.data?.no_reg }} · {{ item.raw?.data?.ket_bayar }} · {{ item.raw?.data?.nama_bangsal }}
                </VListItemSubtitle>
              </VListItem>
            </template>
          </VAutocomplete>

          <VTextField
            v-model="form.nama_pasien"
            label="Nama Pasien"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-user-3-line"
            bg-color="grey-lighten-5" readonly
          />
        </div>

        <!-- Keterangan Batal -->
        <div class="fsec mb-4">
          <p class="fsec-label">Keterangan Batal</p>

          <VSelect
            v-model="form.keterangan_batal"
            :items="keteranganOpts"
            label="Keterangan Batal *"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-close-circle-line"
            clearable class="mb-3"
          />

          <!-- Tgl & Jam Daftar + Status Keterangan (readonly dari DB) -->
          <VRow dense class="mb-3">
            <VCol cols="7">
              <VTextField
                v-model="form.tgl_jam_daftar"
                label="Tgl & Jam Daftar Pasien"
                variant="outlined" density="compact"
                readonly bg-color="grey-lighten-5"
                prepend-inner-icon="ri-calendar-check-line"
                placeholder="Dari data RSUS..."
              />
            </VCol>
            <VCol cols="5">
              <VTextField
                v-model="form.keterangan_pasien"
                label="Ket. Status Pasien"
                variant="outlined" density="compact"
                readonly bg-color="grey-lighten-5"
                prepend-inner-icon="ri-information-line"
              />
            </VCol>
          </VRow>

          <VTextField
            v-model="form.diagnosa"
            label="Diagnosa (dari data pasien)"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-stethoscope-line"
            bg-color="grey-lighten-5" readonly
            class="mb-3"
          />

          <VRow dense>
            <VCol cols="6">
              <VSelect
                v-model="form.status_ok"
                :items="OKOpts"
                label="Status OK"
                variant="outlined" density="compact"
                clearable
              />
            </VCol>
            <VCol cols="6">
              <VTextField
                v-model="form.ketersediaan_kamar"
                label="Ketersediaan Kamar"
                variant="outlined" density="compact"
              />
            </VCol>
          </VRow>
        </div>

        <!-- Bed Management IGD -->
        <div class="fsec mb-4">
          <p class="fsec-label">Bed Management IGD (RSUS BI_Bed_Igd)</p>
          <VRow dense>
            <VCol cols="6">
              <VSelect
                v-model="form.ruangan"
                :items="ruanganOpts"
                label="Ruangan / Unit"
                variant="outlined" density="compact"
                prepend-inner-icon="ri-building-line"
                clearable
              />
            </VCol>
            <VCol cols="6">
              <VSelect
                v-model="form.bed_id"
                :items="availableBeds"
                item-title="title"
                item-value="value"
                label="No. Bed"
                variant="outlined" density="compact"
                prepend-inner-icon="ri-hotel-bed-line"
                clearable
                :loading="loadingBeds"
                :disabled="!form.ruangan"
                :placeholder="form.ruangan ? 'Pilih bed...' : 'Pilih ruangan dulu'"
                no-data-text="Tidak ada bed tersedia"
              />
            </VCol>
          </VRow>
        </div>

        <!-- Petugas & Note -->
        <div class="fsec">
          <p class="fsec-label">Petugas</p>
          <VAutocomplete
            v-model="form.petugas"
            :items="pegawaiStore.namaList"
            label="Petugas *"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-nurse-line"
            clearable
            :loading="pegawaiStore.loading"
            no-data-text="Memuat petugas..."
            class="mb-3"
          />
          <VTextField
            v-model="form.note"
            label="Catatan (opsional)"
            variant="outlined" density="compact"
            prepend-inner-icon="ri-sticky-note-line"
          />
        </div>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Batal</VBtn>
        <VBtn color="error" rounded="xl" class="flex-grow-1" prepend-icon="ri-save-line" :loading="store.loading" @click="handleSave">
          {{ editItem ? 'Simpan Perubahan' : 'Simpan Data' }}
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dlg-header { background: rgba(var(--v-theme-error), 0.04); }
.fsec-label {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgba(var(--v-theme-on-surface), 0.5);
  margin-bottom: 8px;
}
.fsec {
  padding: 12px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
