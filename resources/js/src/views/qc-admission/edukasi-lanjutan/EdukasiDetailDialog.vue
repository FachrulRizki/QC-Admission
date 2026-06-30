<script setup>
import SignaturePad from '@/components/SignaturePad.vue'
import { useEdukasiLanjutanStore } from '@/stores/useEdukasiLanjutanStore'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  patient:    { type: Object, default: null },
  mode:       { type: String, default: 'view' }, // 'view' | 'edit'
})
const emit = defineEmits(['update:modelValue', 'saved'])

const store = useEdukasiLanjutanStore()

const isEdit    = ref(false)
const form      = ref({})
const errorMsg  = ref('')
const successMsg = ref('')

watch(() => props.modelValue, (open) => {
  if (open && props.patient) {
    form.value = { ...props.patient }
    isEdit.value = props.mode === 'edit'
    errorMsg.value = ''
    successMsg.value = ''
  }
})

watch(() => props.mode, (m) => {
  isEdit.value = m === 'edit'
})

const petugasOptions = [
  'Nurul', 'AYU Putri Anisa', 'Reskim', 'Mulbagus Koyum', 'Abdul Hayyi',
]

const statusOptions = ['Menunggu', 'Selesai']

async function handleSave() {
  errorMsg.value = ''
  if (!form.value.keluarga_pasien?.trim()) {
    errorMsg.value = 'Nama keluarga pasien wajib diisi.'
    return
  }
  if (!form.value.petugas) {
    errorMsg.value = 'Petugas wajib dipilih.'
    return
  }

  const result = await store.update(form.value.id, {
    edukasi_kamar:       form.value.edukasi_kamar,
    note:                form.value.note,
    petugas:             form.value.petugas,
    keluarga_pasien:     form.value.keluarga_pasien,
    ttd_keluarga_pasien: form.value.ttd_keluarga_pasien,
    status:              form.value.status,
    bulan:               form.value.bulan,
  })

  if (result?.success ?? true) {
    successMsg.value = 'Data berhasil disimpan!'
    emit('saved', form.value)
    setTimeout(() => close(), 600)
  } else {
    errorMsg.value = result?.message ?? 'Gagal menyimpan.'
  }
}

function close() {
  emit('update:modelValue', false)
}

function hasTtd() {
  return !!form.value.ttd_keluarga_pasien
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="540"
    scrollable
    @update:model-value="close"
  >
    <VCard v-if="patient" rounded="lg">
      <!-- Header -->
      <div class="dialog-header px-5 pt-5 pb-4">
        <div class="d-flex align-center gap-3">
          <VAvatar color="warning" variant="tonal" size="48" rounded="lg">
            <VIcon icon="ri-user-heart-line" size="24" />
          </VAvatar>
          <div class="flex-grow-1 min-width-0">
            <p class="text-h6 font-weight-bold mb-0 text-truncate">{{ patient.nama_pasien }}</p>
            <p class="text-caption text-medium-emphasis mb-0">
              NoMR: <strong>{{ patient.no_mr }}</strong>
              · {{ patient.bulan }}
            </p>
          </div>
          <VBtn icon variant="text" size="small" @click="close">
            <VIcon icon="ri-close-line" />
          </VBtn>
        </div>
      </div>

      <VDivider />

      <VCardText class="pa-5">
        <!-- Alerts -->
        <VAlert v-if="errorMsg" type="error" variant="tonal" density="compact" class="mb-4" closable @click:close="errorMsg = ''">
          {{ errorMsg }}
        </VAlert>
        <VAlert v-if="successMsg" type="success" variant="tonal" density="compact" class="mb-4">
          <VIcon icon="ri-check-line" class="me-1" />{{ successMsg }}
        </VAlert>

        <!-- Info grid (always visible) -->
        <div class="info-grid pa-3 rounded-lg mb-4">
          <VRow dense>
            <VCol cols="6">
              <p class="info-label">Tanggal</p>
              <p class="info-value">{{ form.tanggal }}</p>
            </VCol>
            <VCol cols="6">
              <p class="info-label">No. Reg</p>
              <p class="info-value">{{ form.no_reg || '—' }}</p>
            </VCol>
            <VCol cols="6">
              <p class="info-label">Jaminan</p>
              <p class="info-value">{{ form.jaminan || '—' }}</p>
            </VCol>
            <VCol cols="6">
              <p class="info-label">Bulan</p>
              <p class="info-value">{{ form.bulan }}</p>
            </VCol>
          </VRow>
        </div>

        <!-- Edukasi Kamar -->
        <div class="mb-3">
          <VTextField
            v-model="form.edukasi_kamar"
            label="Edukasi Kamar"
            variant="outlined"
            density="compact"
            prepend-inner-icon="ri-hospital-line"
            :readonly="!isEdit"
            :bg-color="!isEdit ? undefined : undefined"
          />
        </div>

        <!-- Note -->
        <div class="mb-3">
          <VTextarea
            v-model="form.note"
            label="Note / Catatan"
            variant="outlined"
            density="compact"
            rows="3"
            :readonly="!isEdit"
            auto-grow
          />
        </div>

        <!-- Petugas -->
        <div class="mb-3">
          <VAutocomplete
            v-model="form.petugas"
            :items="petugasOptions"
            label="Petugas"
            variant="outlined"
            density="compact"
            prepend-inner-icon="ri-nurse-line"
            :readonly="!isEdit"
            clearable
          />
        </div>

        <!-- Status -->
        <div class="mb-4">
          <VSelect
            v-model="form.status"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            density="compact"
            :readonly="!isEdit"
          >
            <template #selection="{ item }">
              <VChip
                :color="item.value === 'Selesai' ? 'success' : 'warning'"
                size="small"
                variant="tonal"
              >
                {{ item.value }}
              </VChip>
            </template>
          </VSelect>
        </div>

        <VDivider class="mb-4" />

        <!-- Keluarga Pasien -->
        <div class="mb-3">
          <VTextField
            v-model="form.keluarga_pasien"
            label="Nama Keluarga Pasien *"
            variant="outlined"
            density="compact"
            prepend-inner-icon="ri-group-line"
            :readonly="!isEdit"
          />
        </div>

        <!-- TTD Keluarga Pasien -->
        <template v-if="isEdit">
          <SignaturePad
            v-model="form.ttd_keluarga_pasien"
            label="Tanda Tangan Keluarga Pasien"
            :height="150"
          />
        </template>
        <template v-else>
          <div class="ttd-section pa-3 rounded-lg">
            <p class="text-caption font-weight-semibold text-medium-emphasis mb-2">
              <VIcon icon="ri-pen-nib-line" size="14" class="me-1" />
              Tanda Tangan Keluarga Pasien
            </p>
            <div v-if="hasTtd()" class="ttd-preview rounded-lg overflow-hidden">
              <img
                :src="form.ttd_keluarga_pasien"
                alt="TTD"
                style="width:100%; max-height:120px; object-fit:contain; background:#fff;"
              />
            </div>
            <div v-else class="ttd-empty text-center py-4">
              <VIcon icon="ri-pen-nib-line" size="28" color="secondary" class="mb-1" />
              <p class="text-caption text-medium-emphasis mb-0">Belum ada tanda tangan</p>
            </div>
          </div>
        </template>
      </VCardText>

      <VDivider />

      <div class="d-flex gap-3 px-5 py-4">
        <template v-if="isEdit">
          <VBtn variant="outlined" class="flex-grow-1" @click="isEdit = false">Batal</VBtn>
          <VBtn
            color="primary"
            class="flex-grow-1"
            prepend-icon="ri-save-line"
            :loading="store.loading"
            @click="handleSave"
          >
            Simpan
          </VBtn>
        </template>
        <template v-else>
          <VBtn variant="outlined" class="flex-grow-1" @click="close">Tutup</VBtn>
          <VBtn
            color="primary"
            class="flex-grow-1"
            prepend-icon="ri-pencil-line"
            @click="isEdit = true"
          >
            Edit / Isi TTD
          </VBtn>
        </template>
      </div>
    </VCard>
  </VDialog>
</template>

<style scoped>
.dialog-header { background: rgba(var(--v-theme-warning), 0.05); }
.info-grid { background: rgba(var(--v-theme-on-surface), 0.03); }
.info-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  margin-bottom: 2px;
}
.info-value { font-size: 0.875rem; font-weight: 500; margin-bottom: 0; }
.ttd-section { background: rgba(var(--v-theme-on-surface), 0.03); }
.ttd-preview { border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)); }
.ttd-empty {
  border: 1.5px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
</style>
