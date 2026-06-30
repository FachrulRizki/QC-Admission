<script setup>
/**
 * UpSellingFormDialog — sesuai gambar:
 * updateAt, tgldaftar, NoMR, NoReg (autocomplete), NamaPasien,
 * ketBayar, NamaRuang, NamaBangsal, Kelas (readonly dari data pasien),
 * Ket_Up_Selling (dropdown), notes, nama_petugas (dropdown)
 */
import { useUpSellingStore } from '@/stores/useUpSellingStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  editItem:   { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useUpSellingStore()

const form       = ref(initialForm())
const errorMsg   = ref('')
const successMsg = ref('')

// ── Master options ────────────────────────────────────────────────────────────
const petugasOptions = ['Nurul', 'AYU Putri Anisa', 'Reskim', 'Mulbagus Koyum', 'Abdul Hayyi']

const ketUpSellingOptions = [
  'Naik Kelas',
  'Perubahan Jaminan',
]

// Mock data pasien dari sistem (normalnya dari API /api/pasien atau bed management)
const noRegOptions = ref([
  {
    title: '569142 - ONGKI SAPUTRA, TN',
    value: '569142',
    no_mr: '569142', nama: 'ONGKI SAPUTRA, TN', jaminan: 'BPJS',
    nama_ruang: 'PS ATAS 01', nama_bangsal: 'PAHLAWAN ATAS', kelas: 'KELAS VIP',
    tgl_daftar: '06/28/2026 07:56:40 PM',
  },
  {
    title: 'REG001 - ELLY MAYA, NY',
    value: 'REG001',
    no_mr: '813500', nama: 'ELLY MAYA, NY', jaminan: 'BPJS',
    nama_ruang: 'DAHLIA 2', nama_bangsal: 'DAHLIA', kelas: 'Kelas 1',
    tgl_daftar: '06/29/2026 08:00:00 AM',
  },
  {
    title: 'REG002 - IDH SUBINGSEN, NY',
    value: 'REG002',
    no_mr: '575360', nama: 'IDH SUBINGSEN, NY', jaminan: 'BPJS',
    nama_ruang: 'MAWAR 3', nama_bangsal: 'MAWAR', kelas: 'Kelas 2',
    tgl_daftar: '06/29/2026 10:30:00 AM',
  },
  {
    title: 'REG003 - RUSMINI, NY',
    value: 'REG003',
    no_mr: '087220', nama: 'RUSMINI, NY', jaminan: 'Umum',
    nama_ruang: 'ANGGREK 1', nama_bangsal: 'ANGGREK', kelas: 'Kelas 3',
    tgl_daftar: '06/28/2026 02:15:00 PM',
  },
  {
    title: 'REG004 - PUSPA SARI, AN',
    value: 'REG004',
    no_mr: '816302', nama: 'PUSPA SARI, AN', jaminan: 'BPJS',
    nama_ruang: 'ICU 2', nama_bangsal: 'ICU', kelas: 'VIP',
    tgl_daftar: '06/27/2026 09:00:00 AM',
  },
])

function initialForm() {
  const now = new Date()
  const fmt = d => d.toLocaleString('en-US', { month:'2-digit', day:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit' })
  return {
    update_at:         fmt(now),
    tgl_daftar:        '',
    no_mr:             '',
    no_reg:            null,
    nama_pasien:       '',
    ket_bayar:         '',   // jaminan
    nama_ruang:        '',
    nama_bangsal:      '',
    kelas:             '',
    ket_up_selling:    null,
    notes:             '',
    nama_petugas:      null,
  }
}

watch(() => props.modelValue, open => {
  if (open) {
    if (props.editItem) {
      form.value = { ...initialForm(), ...props.editItem }
    } else {
      form.value = initialForm()
    }
    errorMsg.value = ''
    successMsg.value = ''
  }
})

// Auto-fill dari NoReg
watch(() => form.value.no_reg, val => {
  const found = noRegOptions.value.find(o => o.value === val)
  if (found) {
    form.value.no_mr        = found.no_mr
    form.value.nama_pasien  = found.nama
    form.value.ket_bayar    = found.jaminan
    form.value.nama_ruang   = found.nama_ruang
    form.value.nama_bangsal = found.nama_bangsal
    form.value.kelas        = found.kelas
    form.value.tgl_daftar   = found.tgl_daftar
  }
})

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.no_reg)         { errorMsg.value = 'NoReg wajib dipilih.'; return }
  if (!form.value.nama_petugas)   { errorMsg.value = 'Nama Petugas wajib dipilih.'; return }
  if (!form.value.ket_up_selling) { errorMsg.value = 'Keterangan Up Selling wajib dipilih.'; return }

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
    status:            form.value.ket_up_selling?.startsWith('Berhasil') ? 'Berhasil'
                     : form.value.ket_up_selling === 'Pending' ? 'Pending'
                     : 'Tidak Berhasil',
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
          <p class="text-subtitle-1 font-weight-bold mb-0">
            {{ editItem ? 'Edit Up Selling' : 'Input Up Selling' }}
          </p>
          <p class="text-caption text-medium-emphasis mb-0">Penawaran upgrade kelas kamar pasien</p>
        </div>
        <VBtn icon variant="text" size="small" @click="close">
          <VIcon icon="ri-close-line" />
        </VBtn>
      </div>

      <VDivider />

      <VCardText class="pa-5">
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg=''">{{ errorMsg }}</VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">{{ successMsg }}</VAlert>

        <VForm @submit.prevent="handleSave">

          <!-- updateAt (readonly auto) -->
          <div class="mb-3">
            <VTextField
              v-model="form.update_at"
              label="updateAt"
              variant="outlined"
              density="compact"
              readonly
              prepend-inner-icon="ri-calendar-line"
              bg-color="grey-100"
            />
          </div>

          <!-- NoReg — autocomplete utama -->
          <div class="mb-3">
            <VAutocomplete
              v-model="form.no_reg"
              :items="noRegOptions"
              item-title="title"
              item-value="value"
              label="NoReg *"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-search-line"
              clearable
            />
          </div>

          <!-- tgldaftar (auto dari NoReg) -->
          <div class="mb-3">
            <VTextField
              v-model="form.tgl_daftar"
              variant="outlined"
              density="compact"
              readonly
              prepend-inner-icon="ri-calendar-check-line"
              bg-color="grey-100"
              placeholder="Otomatis dari NoReg..."
            />
          </div>

          <!-- NoMR (auto dari NoReg) -->
          <div class="mb-3">
            <VTextField
              v-model="form.no_mr"
              label="NoMR"
              variant="outlined"
              density="compact"
              readonly
              bg-color="grey-100"
            />
          </div>

          <!-- NamaPasien (auto) -->
          <div class="mb-3">
            <VTextField
              v-model="form.nama_pasien"
              label="NamaPasien"
              variant="outlined"
              density="compact"
              readonly
              bg-color="grey-100"
            />
          </div>

          <!-- ketBayar / Jaminan (auto) -->
          <div class="mb-3">
            <VTextField
              v-model="form.ket_bayar"
              label="ketBayar"
              variant="outlined"
              density="compact"
              readonly
              bg-color="grey-100"
            />
          </div>

          <!-- NamaRuang (auto dari bed management) -->
          <div class="mb-3">
            <VTextField
              v-model="form.nama_ruang"
              label="NamaRuang"
              variant="outlined"
              density="compact"
              readonly
              bg-color="grey-100"
            />
          </div>

          <!-- NamaBangsal (auto) -->
          <div class="mb-3">
            <VTextField
              v-model="form.nama_bangsal"
              label="NamaBangsal"
              variant="outlined"
              density="compact"
              readonly
              bg-color="grey-100"
            />
          </div>

          <!-- Kelas (auto) -->
          <div class="mb-3">
            <VTextField
              v-model="form.kelas"
              label="Kelas"
              variant="outlined"
              density="compact"
              readonly
              bg-color="grey-100"
            />
          </div>

          <VDivider class="mb-4" />

          <!-- Ket_Up_Selling (dropdown — diisi petugas) -->
          <div class="mb-3">
            <VSelect
              v-model="form.ket_up_selling"
              :items="ketUpSellingOptions"
              label="Ket_Up_Selling *"
              variant="outlined"
              density="compact"
              clearable
            />
          </div>

          <!-- notes -->
          <div class="mb-3">
            <VTextField
              v-model="form.notes"
              label="notes"
              variant="outlined"
              density="compact"
            />
          </div>

          <!-- nama_petugas -->
          <div class="mb-1">
            <VAutocomplete
              v-model="form.nama_petugas"
              :items="petugasOptions"
              label="nama_petugas *"
              variant="outlined"
              density="compact"
              prepend-inner-icon="ri-nurse-line"
              clearable
            />
          </div>

        </VForm>
      </VCardText>

      <VDivider />
      <div class="d-flex gap-3 px-5 py-4">
        <VBtn variant="outlined" rounded="lg" class="flex-grow-1" @click="close">Batal</VBtn>
        <VBtn color="success" rounded="lg" class="flex-grow-1" :loading="store.loading" prepend-icon="ri-save-line" @click="handleSave">
          Simpan
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-success), 0.04); }
</style>
