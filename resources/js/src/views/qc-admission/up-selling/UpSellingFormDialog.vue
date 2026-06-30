<script setup>
import { useUpSellingStore } from '@/stores/useUpSellingStore'
import { usePegawaiStore } from '@/stores/usePegawaiStore'
import { usePasienStore } from '@/stores/usePasienStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store        = useUpSellingStore()
const pegawaiStore = usePegawaiStore()
const pasienStore  = usePasienStore()

const form       = ref(initialForm())
const errorMsg   = ref('')
const successMsg = ref('')

// ── Master options ────────────────────────────────────────────────────────────
const ketUpSellingOptions = ['Naik Kelas', 'Perubahan Jaminan']

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

// Auto-fill saat no_reg dipilih
watch(() => form.value.no_reg, async (val) => {
  if (!val) return
  const found = pasienStore.results.find(p => p.no_reg === val)
    ?? (await pasienStore.lookup(val))
  if (found) {
    form.value.no_mr        = found.no_mr
    form.value.nama_pasien  = found.nama_pasien
    form.value.ket_bayar    = found.ket_bayar
    form.value.nama_ruang   = found.nama_ruang   ?? ''
    form.value.nama_bangsal = found.nama_bangsal ?? ''
    form.value.kelas        = found.nama_kelas   ?? ''
    form.value.tgl_daftar   = found.tgl_daftar   ?? ''
  }
})

watch(() => props.modelValue, (open) => {
  if (open) {
    form.value       = props.editItem ? { ...initialForm(), ...props.editItem } : initialForm()
    errorMsg.value   = ''
    successMsg.value = ''
    pasienStore.clear()
    noRegSearch.value = ''
    pegawaiStore.fetch()
  }
})

function initialForm() {
  const now = new Date()
  const fmt = d => d.toLocaleString('id-ID', { dateStyle:'short', timeStyle:'medium' })
  return {
    update_at:      fmt(now),
    tgl_daftar:     '',
    no_mr:          '',
    no_reg:         null,
    nama_pasien:    '',
    ket_bayar:      '',
    nama_ruang:     '',
    nama_bangsal:   '',
    kelas:          '',
    ket_up_selling: null,
    notes:          '',
    nama_petugas:   null,
  }
}

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg)          { errorMsg.value = 'NoReg wajib dipilih.'; return }
  if (!form.value.nama_petugas)    { errorMsg.value = 'Nama Petugas wajib dipilih.'; return }
  if (!form.value.ket_up_selling)  { errorMsg.value = 'Keterangan Up Selling wajib dipilih.'; return }

  const payload = {
    tanggal:           form.value.update_at,
    jam_input:         new Date().toTimeString().slice(0, 8),
    no_reg:            form.value.no_reg,
    nama_pasien:       form.value.nama_pasien,
    jaminan:           form.value.ket_bayar,
    rekomendasi_kelas: form.value.kelas,
    kelas_diambil:     form.value.kelas,
    alasan:            form.value.ket_up_selling,
    petugas:           form.value.nama_petugas,
    status:            form.value.ket_up_selling === 'Naik Kelas' ? 'Berhasil' : 'Pending',
    note:              form.value.notes,
  }

  const result = props.editItem
    ? await store.update(props.editItem.id, payload)
    : await store.store(payload)

  if (result?.success ?? true) {
    successMsg.value = 'Data berhasil disimpan!'
    emit('saved', { ...form.value, ...payload })
    setTimeout(() => close(), 500)
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
        <VAvatar color="success" variant="tonal" size="40" rounded="lg">
          <VIcon icon="ri-arrow-up-circle-line" size="20" />
        </VAvatar>
        <div class="flex-grow-1">
          <p class="text-subtitle-1 font-weight-bold mb-0">{{ editItem ? 'Edit Up Selling' : 'Input Up Selling' }}</p>
          <p class="text-caption text-medium-emphasis mb-0">Penawaran upgrade kelas kamar pasien</p>
        </div>
        <VBtn icon variant="text" size="small" @click="close"><VIcon icon="ri-close-line" /></VBtn>
      </div>
      <VDivider />

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg"   type="error"   variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">{{ successMsg }}</VAlert>

        <VForm @submit.prevent="handleSave">
          <!-- updateAt -->
          <div class="mb-3">
            <VTextField v-model="form.update_at" label="updateAt" variant="outlined" density="compact" readonly prepend-inner-icon="ri-calendar-line" bg-color="grey-100" />
          </div>

          <!-- NoReg — search real time -->
          <div class="mb-3">
            <VAutocomplete
              v-model="form.no_reg"
              v-model:search="noRegSearch"
              :items="pasienStore.optionList"
              item-title="title"
              item-value="value"
              label="NoReg *"
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

          <!-- Tgl Daftar (auto) -->
          <div class="mb-3">
            <VTextField v-model="form.tgl_daftar" label="Tgl Daftar" variant="outlined" density="compact" readonly prepend-inner-icon="ri-calendar-check-line" bg-color="grey-100" placeholder="Otomatis dari NoReg..." />
          </div>

          <!-- NoMR -->
          <div class="mb-3">
            <VTextField v-model="form.no_mr" label="NoMR" variant="outlined" density="compact" readonly bg-color="grey-100" />
          </div>

          <!-- NamaPasien -->
          <div class="mb-3">
            <VTextField v-model="form.nama_pasien" label="NamaPasien" variant="outlined" density="compact" readonly bg-color="grey-100" />
          </div>

          <!-- ketBayar -->
          <div class="mb-3">
            <VTextField v-model="form.ket_bayar" label="ketBayar" variant="outlined" density="compact" readonly bg-color="grey-100" />
          </div>

          <!-- NamaRuang + NamaBangsal -->
          <VRow dense class="mb-1">
            <VCol cols="6">
              <VTextField v-model="form.nama_ruang" label="NamaRuang" variant="outlined" density="compact" readonly bg-color="grey-100" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="form.nama_bangsal" label="NamaBangsal" variant="outlined" density="compact" readonly bg-color="grey-100" />
            </VCol>
          </VRow>

          <!-- Kelas -->
          <div class="mb-3">
            <VTextField v-model="form.kelas" label="Kelas" variant="outlined" density="compact" readonly bg-color="grey-100" />
          </div>

          <!-- Ket Up Selling -->
          <div class="mb-3">
            <VSelect v-model="form.ket_up_selling" :items="ketUpSellingOptions" label="Keterangan Up Selling *" variant="outlined" density="compact" prepend-inner-icon="ri-arrow-up-circle-line" clearable />
          </div>

          <!-- Notes -->
          <div class="mb-3">
            <VTextField v-model="form.notes" label="Notes" variant="outlined" density="compact" prepend-inner-icon="ri-sticky-note-line" />
          </div>

          <!-- Nama Petugas — dari KPI API -->
          <div class="mb-3">
            <VAutocomplete
              v-model="form.nama_petugas"
              :items="pegawaiStore.namaList"
              label="Nama Petugas *"
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
        <VBtn color="success" rounded="lg" class="flex-grow-1" prepend-icon="ri-save-line" :loading="store.loading" @click="handleSave">Simpan</VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-success), 0.05); }
</style>
